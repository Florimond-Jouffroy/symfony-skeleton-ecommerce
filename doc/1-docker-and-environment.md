# 🐳 1. Docker & environnement

Ce guide décrit l'infrastructure locale du skeleton : les conteneurs, le
reverse-proxy, les fichiers d'environnement (il y en a plusieurs, et l'ordre de
priorité compte) et le Makefile qui pilote tout. À lire pour comprendre **comment
tourne le projet en local** et **pourquoi telle variable est où**.

---

## 1. Vue d'ensemble

Le projet tourne dans **Docker Compose** (convention moderne `compose.yaml`, pas
`docker-compose.yml`). Deux fichiers :

- [`compose.yaml`](../compose.yaml) — la définition principale
- [`compose.override.yaml`](../compose.override.yaml) — surcharge auto-chargée par
  Docker Compose (expose des ports en local)

Il s'appuie sur une **infrastructure globale externe** (`_infra`, un dépôt séparé)
qui fournit le reverse-proxy **Traefik** et le réseau Docker partagé `gateway`.
C'est ce que veut dire le README quand il demande que « ton infra globale soit
lancée » avant de démarrer le projet. On accède à l'app via
`http://<projet>.localhost` (routé par Traefik), pas via un port `localhost:8000`.

> ⚠️ Toutes les commandes passent par **`make`**, jamais `docker` ou `bin/console`
> à la main — le Makefile injecte le bon `--env-file` et le bon nom de projet.

---

## 2. Les conteneurs (`compose.yaml`)

Les conteneurs sont préfixés par `${PROJECT_NAME}` (défini dans
`.env.docker.local`, ex. `symfony-app` → `symfony-app-app`, `symfony-app-db`…).

| Service | Image | Rôle | Réseau |
|---------|-------|------|--------|
| **app** | build `docker/Dockerfile` (`php:8.4-apache`) | PHP + **Apache/mod_php** (pas de PHP-FPM/nginx) — sert `public/` | `gateway` + `internal` |
| **database** | `mariadb:10.11` | Base applicative + logs (`app_main`, `app_log`) — et leurs jumelles `_test` | `internal` + `gateway` |
| **mailer** | `axllent/mailpit` | Capture des emails en dev, UI web | `gateway` + `internal` |

Points à connaître :

- **Pas de service worker Messenger.** Le worker se lance à la main, en premier
  plan, via `make worker` (voir [guide backend §7](./2-backend-architecture.md)).
- Le service `app` monte le code (`.:/var/www/html`), la conf Apache
  (`docker/apache/site-enabled/vhost.conf`, DocumentRoot `/var/www/html/public`),
  `docker/config/php.ini` (timezone) et l'horloge de l'hôte.
- **Il n'y a pas de base de test séparée** : en environnement `test`, Doctrine
  suffixe les deux bases (`app_main_test`, `app_log_test`) via `dbname_suffix`
  (voir `config/packages/doctrine.yaml`). `make db-test-setup` les (re)construit.
- `app` **n'expose aucun port** : l'accès passe uniquement par Traefik (labels
  `traefik.*` → `Host(\`${PROJECT_NAME}.localhost\`)`). Le mailer est exposé sur
  `http://mail.${PROJECT_NAME}.localhost`.
- `database` a un **healthcheck** ; `app` attend `service_healthy` avant de
  démarrer. `make install` attend en plus que MariaDB accepte les connexions
  (cible interne `wait-db`) avant la première commande Doctrine.
- `compose.override.yaml` contient ce qui **dépend de ta machine** : ports hôte
  aléatoires pour `database` (3306) et le mailer (1025 SMTP / 8025 UI), et un
  montage commenté des sources d'un bundle privé (`../log-bundle`) pour le
  développer en parallèle de l'application.

### L'image (`docker/Dockerfile`)

`FROM php:8.4-apache`. Contient : extensions PHP `intl pdo_mysql zip opcache soap
gd` (gd avec freetype/jpeg), `a2enmod rewrite`, Composer (depuis `composer:latest`),
**Node.js 22** + npm, `zsh` + `starship` pour le prompt dev. Document root effectif
= `public/`, fourni par le vhost monté.

---

## 3. Les fichiers d'environnement

C'est la partie la plus source de confusion : **plusieurs `.env`**, avec un ordre
de priorité. En dev, **`.env.local` fait foi** (il écrase `.env`).

| Fichier | Commité ? | Rôle |
|---------|-----------|------|
| `.env` | ✅ | Valeurs par défaut génériques (host `database`, DSN, `APP_ENV=dev`) |
| `.env.local` | ❌ (généré) | **Config dev réelle** — écrase `.env`. Host = nom de conteneur (`${projet}-db`), `DATABASE_URL`, `LOG_DATABASE_URL`, plus les secrets tirés au sort au setup (`APP_SECRET`, pepper du log-bundle) |
| `.env.docker.local` | ❌ (généré) | Variables **Docker/Traefik** : `PROJECT_NAME`, domaine local, `GITHUB_COMPOSER_TOKEN`. **C'est le fichier chargé par le Makefile** (`--env-file`) |
| `.env.test` | ✅ | Surcharges de test : `MAILER_DSN=null://null`, `LOG_DATABASE_URL`, secret de test |
| `.env.local.example` | ✅ | Modèle de `.env.local` (référence) |

Variables clés :

- **Bases** : `DATABASE_URL` (→ `app_main`, connexion `default`) et
  `LOG_DATABASE_URL` (→ `app_log`, connexion `log`). Le pourquoi des deux bases est
  détaillé dans le [guide 5](./5-multi-database-and-migrations.md).
- **Async** : `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0`.
- **Mail** : `MAILER_DSN=smtp://${PROJECT_NAME}-mailer:1025` en dev, `null://null`
  en test.
- **Paiement** : ⚠️ **aucune clé Stripe/PayPal/Mollie dans les `.env`.** Les
  providers lisent leur configuration depuis les `AppSetting` en base (activation +
  clés), pas depuis l'environnement. Voir
  [guide backend §5](./2-backend-architecture.md).

> 🔐 `.env.docker.local` contient un **token GitHub (PAT)** en clair, nécessaire à
> Composer pour installer les bundles privés `florimond/*`. Ce fichier n'est pas
> commité — ne jamais le versionner ni copier son contenu ailleurs.

---

## 4. Première installation

**Une seule commande**, depuis un clone frais :

```bash
make install
```

Le projet n'étant pas encore configuré, `make install` lance d'abord
`setup.sh`, puis **se relance lui-même** — indispensable, car les variables du
fichier d'environnement (`PROJECT_NAME`…) sont lues au chargement du Makefile,
donc bien avant que la recette ne tourne.

**Phase 1 — configuration** (`setup.sh`, aussi accessible via `make setup`).
Elle ne fait qu'écrire des fichiers, elle n'installe rien :
1. demande le **nom de projet** (slug, défaut `symfony-shop`) ;
2. demande un **GitHub PAT** (saisie masquée) — obligatoire, il donne accès en
   lecture aux 3 dépôts privés `florimond/*` ;
3. propose d'activer la **CI GitHub Actions** (sinon le workflow est supprimé) ;
   si `gh` est authentifié, le secret `COMPOSER_GITHUB_TOKEN` est posé sur le dépôt ;
4. écrit `.env.docker.local` et `.env.local`, en **tirant au sort les secrets
   propres au projet** (`APP_SECRET`, pepper de pseudonymisation des IP).

**Phase 2 — installation** (`make install`), idempotente et relançable :
images Docker → `composer install` → `npm install` + build dev → attente de la
base → `db-install` (création + migrations + table Messenger) → `db-test-setup`
→ `cache:clear`. Elle se termine en affichant les URLs et les deux commandes
qui restent à ta main : `make create-admin` et `make fixtures`.

> `make setup` seul reste utile pour (re)configurer sans installer. Il refuse de
> tourner si `.env.docker.local` existe déjà — supprime-le pour repartir de zéro.

---

## 5. Le Makefile au quotidien

Le Makefile enveloppe tout. En interne :
`DOCKER_COMPOSE = docker compose --env-file .env.docker.local -p $(PROJECT_NAME)`,
`CONSOLE = $(DOCKER_COMPOSE) exec -u <uid>:<gid> app bin/console` (l'`-u` aligne les
droits pour éviter les fichiers root sous WSL2).

> Si `.env.docker.local` est absent, toutes les cibles (sauf `setup`/`help`)
> échouent avec un message — lance `make setup` d'abord.

### Cycle de vie & accès

```bash
make up / down / stop / restart / ps    # conteneurs (make down cmd=-v purge les volumes)
make build                              # rebuild images sans cache
make logs            /  logs-mailer     # logs temps réel
make shell                              # zsh (sinon bash) dans le conteneur app
make sf cmd="debug:router"              # console Symfony
make cmd cmd="ls -la"                   # commande shell libre dans app
make cc                                 # cache:clear
```

### Composer & dépendances

```bash
make vendor                             # composer install
make composer cmd="require foo/bar"     # composer libre
```

### Base de données

```bash
make db-install          # crée les bases manquantes + applique les migrations (NON destructif)
make db-setup            # (bouton panic) supprime les deux bases et rejoue tout
make db-main-migration   # génère une migration main (déjà patchée CustomMigration)
make db-main-migrate     # applique les migrations main
make db-log-migration / db-log-migrate
make fixtures / fixtures-reset
```

> 📌 Détails et pièges des migrations → [guide 5](./5-multi-database-and-migrations.md).

### Frontend

```bash
make watch        # encore dev --watch (à laisser tourner en dev)
make npm-build    # build production
make npm cmd="install foo"
```

### Async, admin, qualité

```bash
make worker                              # worker Messenger (emails), premier plan
make worker-failed / worker-retry id=42
make create-admin email=x password=x
make qa                                  # cs + stan + test (voir guide 4)
make perm                                # répare les permissions var/ (WSL2)
```

---

## 6. Pièges & écarts connus

- **En dev, `.env.local` écrase `.env`.** Si une variable ne « prend pas », vérifie
  d'abord `.env.local`, pas `.env`.
- **Deux commandes du README sont obsolètes** : `make make-migration` et
  `make migrate` **n'existent pas**. Les bonnes cibles sont `db-main-migration` /
  `db-log-migration` et `db-main-migrate` / `db-log-migrate`.
- Les bundles privés `florimond/*` sont dans le `require` du `composer.json` :
  c'est `composer install` qui les installe, donc **une install sans PAT valide
  échoue**. La cible interne `check-migrations-bundle` le dit explicitement au
  lieu de laisser passer une `CommandNotFoundException`.
- **Node 22** est effectivement installé, même si un `ARG NODE_VERSION=20` traîne
  dans le Dockerfile (non utilisé).
- Tout PHP tourne **dans le conteneur `app`** — il n'y a pas de PHP sur l'hôte WSL.
  D'où `make shell` / `make sf` pour toute commande.
