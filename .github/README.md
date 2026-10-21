# SPIP, côté Symfony — version SPIP

Version SPIP du guide « SPIP, côté Symfony — une introduction en douceur » :
six étapes pour découvrir les composants Symfony de SPIP 5, avec un lexique,
des démonstrations à manipuler et une progression mémorisée dans le navigateur.

## Installation

Prérequis : PHP 8.1+ avec `pdo_sqlite`, `git`, `composer`, `curl`, `unzip`.

```bash
git clone git@github.com:tech-nova/site-tuto-transition-vers-spip5.git site-tuto
cd site-tuto
bin/install.sh
```

Le script :

1. clone spip-cli avec la [MR 91](https://git.spip.net/spip-contrib-outils/spip-cli/-/merge_requests/91)
   dans `.outils/spip-cli` (sauf si `SPIP_CLI` désigne un spip-cli existant) ;
2. télécharge SPIP 4.4 (zip officiel : `spip dl` refuse un répertoire non vide) ;
3. installe SPIP en SQLite et crée un compte administrateur (`admin` par défaut,
   avec un mot de passe aléatoire affiché à la fin si `ADMIN_PASS` n'est pas fourni) ;
4. télécharge par SVP `tiptap_spip`, `coloration_code`, `inserer_modeles`,
   `article_accueil` et leurs dépendances, puis active le plugin du site ;
5. crée le contenu avec `spip tuto:seed`.

Il peut être relancé : chaque étape déjà faite est sautée.

Variables utiles : `SPIP_CLI` (exécutable spip-cli existant), `ADRESSE_SITE`,
`ADMIN_LOGIN`, `ADMIN_PASS`, `ADMIN_EMAIL`. Par exemple :

```bash
ADMIN_LOGIN=moi ADMIN_PASS='un mot de passe' bin/install.sh
```

Le serveur web écrit dans `config/`, `IMG/`, `local/`, `tmp/` et `plugins/auto/` :
le script ouvre ces répertoires en écriture à tous.

## Organisation du contenu

| Dans le guide | Dans SPIP |
|---|---|
| Page d'accueil | Article d'accueil du secteur « Le parcours » |
| Une étape | Sous-rubrique du parcours et son article d'accueil |
| Titre court, résumé | Titre et descriptif de la rubrique |
| Thème, titre, accroche | Surtitre, titre, chapô de l'article |
| « S'il ne fallait retenir qu'une idée » | Post-scriptum de l'article |
| « Pour aller plus loin » | Sites référencés de la rubrique |
| Lexique | Mots du groupe « Lexique » liés aux rubriques (définition dans le descriptif, rapprochement dans le texte, champ « synonymes » pour la recherche) |
| Sources & méthode | Secteur « Sources & méthode » et son article d'accueil |

Dans un texte, `[?service#lexique]` signale un terme du lexique : un clic ouvre
le lexique sur ce mot.

## Modèles

Chaque modèle a un formulaire pour le plugin Insérer modèles, utilisé par
l'éditeur tiptap.

| Modèle | Rôle |
|---|---|
| `<colonnes\|…>` | 2 ou 3 colonnes : comparaison, définitions, rôles numérotés |
| `<encadre\|…>` | Encadré avec étiquette, violet ou sable |
| `<composants\|…>` | Bandeau « SPIP 5 + composants » |
| `<lecture\|…>` | Lien de lecture introduit par une question ; plusieurs à la suite forment une liste |
| `<depliant\|titre=…>` … `<depliant\|fin>` | Bloc dépliant autour du texte compris entre les deux |
| `<code_bascule\|onglet=…>` … `<code_bascule\|fin>` | Blocs de code en onglets |
| `<legende_code\|titre=…\|legende=…>` | Titre et légende du bloc de code qui suit |
| `<manipuler_service\|>` | Simulateur : changer le journal injecté |
| `<manipuler_evenements\|>` | Simulateur : déclencher un pipeline |
| `<manipuler_trajet\|>` | Schéma pas à pas du trajet d'une page |
| `<fiche_reperes\|repere1=…>` | Idées à cocher, mémorisées dans le navigateur |
| `<sources_doc\|>` | Liste des pages de documentation citées dans le parcours |
| `<reinitialiser_progression\|>` | Bouton d'effacement de la progression |

Dépliant, onglets et légende de code posent des marqueurs : le texte qu'ils
encadrent reste un texte SPIP ordinaire, modifiable dans l'éditeur. Le filtre
`|tuto_assembler` les assemble à l'affichage.

Dans un paramètre de modèle, `>` s'écrit `&gt;`.

## Le seed

`seed/` contient tout le contenu : `site.yaml` (réglages), `rubriques.yaml`
(arborescence, articles, liens), `lexique.yaml`, un fichier `textes/*.spip` par
article, et `IMG/<clé>.png` pour un éventuel logo de rubrique.

```bash
php .outils/spip-cli/bin/spip tuto:seed        # crée ce qui manque
php .outils/spip-cli/bin/spip tuto:seed --maj  # reprend aussi le contenu du seed
```

Sans `--maj`, les modifications faites dans l'espace privé sont conservées.

## Organisation du code

- `plugins/tuto/` : le plugin du site (squelettes, modèles, CSS, JS, commande
  `spip tuto:seed`).
- `seed/` : le contenu initial.
- `bin/install.sh` : installation depuis zéro.

Le cœur SPIP, les plugins téléchargés et la base de données ne sont pas
versionnés.
