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

Le script télécharge SPIP 4.4, l'installe en SQLite avec le compte
`gilles` / `adminadmin`, récupère les plugins sur SPIP Zone avec spip-cli,
puis crée le contenu. Il peut être relancé sans risque.

Variables utiles : `SPIP_CLI` (exécutable spip-cli existant), `ADRESSE_SITE`,
`ADMIN_LOGIN`, `ADMIN_PASS`, `ADMIN_EMAIL`.

## Organisation

- `plugins/tuto/` : le plugin du site (squelettes, modèles, CSS, JS, commande
  `spip tuto:seed`).
- `seed/` : le contenu initial (rubriques, articles, lexique, liens).
- `bin/install.sh` : installation depuis zéro.

Le cœur SPIP, les plugins téléchargés et la base de données ne sont pas
versionnés.
