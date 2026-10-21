#!/usr/bin/env bash
#
# Installe le site depuis zéro :
#   1. spip-cli (avec la MR 91 qui répare plugins:svp:telecharger)
#   2. cœur SPIP 4.4
#   3. installation SQLite et compte administrateur
#   4. plugins depuis SPIP Zone, puis activation du plugin du site
#   5. contenu initial (seed)
#
# Chaque étape est sautée si elle a déjà été faite : le script peut être relancé.
#
# Variables d'environnement :
#   SPIP_CLI      exécutable spip-cli à utiliser (sinon clone dans .outils/)
#   ADRESSE_SITE  URL publique du site (défaut : http://localhost:8760/)
#   ADMIN_LOGIN, ADMIN_PASS, ADMIN_EMAIL : compte administrateur

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SPIP_ZIP_URL="https://files.spip.net/spip/stable/spip-4.4.zip"
SPIP_CLI_GIT="https://git.spip.net/spip-contrib-outils/spip-cli.git"
SPIP_CLI_REF="refs/merge-requests/91/merge"
DEPOT_SVP="https://files.spip.net/spip-zone/archives.xml"
PLUGINS=(tiptap_spip coloration_code inserer_modeles article_accueil)

ADRESSE_SITE="${ADRESSE_SITE:-http://localhost:8760/}"
ADMIN_LOGIN="${ADMIN_LOGIN:-gilles}"
ADMIN_PASS="${ADMIN_PASS:-adminadmin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-gilles@localhost}"

etape() { printf '\n\033[1;35m==> %s\033[0m\n' "$*"; }

# Répertoires où PHP (www-data) et la ligne de commande écrivent tous les deux
ouvrir_droits() {
	mkdir -p config IMG local tmp plugins/auto
	find config IMG local tmp plugins/auto -user "$(id -u)" -exec chmod a+rwX {} +
}

etape "spip-cli"
if [[ -z "${SPIP_CLI:-}" ]]; then
	SPIP_CLI_DIR="$ROOT/.outils/spip-cli"
	if [[ ! -d "$SPIP_CLI_DIR" ]]; then
		git clone -q "$SPIP_CLI_GIT" "$SPIP_CLI_DIR"
		git -C "$SPIP_CLI_DIR" fetch -q origin "$SPIP_CLI_REF"
		git -C "$SPIP_CLI_DIR" checkout -q FETCH_HEAD
		composer install -q --no-dev -d "$SPIP_CLI_DIR"
	fi
	SPIP_CLI="$SPIP_CLI_DIR/bin/spip"
fi
spip() { php "$SPIP_CLI" "$@"; }
spip --version

etape "Cœur SPIP 4.4"
if [[ ! -f ecrire/inc_version.php ]]; then
	# « spip dl » refuse un répertoire non vide : on décompresse le zip
	# officiel par-dessus le dépôt, sans écraser ses fichiers Git.
	zip="$(mktemp --suffix=.zip)"
	curl -fsSL "$SPIP_ZIP_URL" -o "$zip"
	unzip -q -o "$zip" -x .gitignore .gitattributes
	rm -f "$zip"
fi
ouvrir_droits
grep -m1 'spip_version_branche =' ecrire/inc_version.php

etape "Installation (SQLite, compte $ADMIN_LOGIN)"
if [[ ! -f config/connect.php ]]; then
	spip core:installer \
		--db-server=sqlite3 \
		--db-database=spip \
		--admin-nom="$ADMIN_LOGIN" \
		--admin-login="$ADMIN_LOGIN" \
		--admin-pass="$ADMIN_PASS" \
		--admin-email="$ADMIN_EMAIL" \
		--adresse-site="$ADRESSE_SITE"
fi

etape "Plugins"
if [[ "$(spip php:eval 'echo sql_countsel("spip_depots");')" == "0" ]]; then
	spip plugins:svp:depoter "$DEPOT_SVP"
fi
for prefixe in "${PLUGINS[@]}"; do
	if ! ls -d plugins/auto/"$prefixe"* >/dev/null 2>&1; then
		spip plugins:svp:telecharger "$prefixe" -y
	fi
done
spip plugins:activer "${PLUGINS[@]}" tuto -y
spip plugins:maj:bdd
ouvrir_droits

etape "Contenu initial"
spip tuto:seed
spip cache:vider
ouvrir_droits

etape "Terminé : $ADRESSE_SITE (admin : $ADMIN_LOGIN)"
