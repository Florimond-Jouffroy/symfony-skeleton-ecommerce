# --- VARIABLES & ENVIRONNEMENT ---
ENV_FILE := .env.docker.local
SAFE_TARGETS := setup help install

# Chargement automatique du fichier d'environnement s'il existe
ifneq (,$(wildcard ./$(ENV_FILE)))
    include $(ENV_FILE)
    export
else
    # Bloque poliment si le fichier est manquant pour les commandes critiques
    ifneq (,$(filter-out $(SAFE_TARGETS),$(MAKECMDGOALS)))
        $(error ⚠️ Le fichier $(ENV_FILE) est manquant. Lancez 'make setup' pour le configurer)
    endif
endif

# Utilisation du nom de projet dynamique (-p) pour l'isolation multi-projets
DOCKER_COMPOSE = docker compose --env-file $(ENV_FILE) -p $(PROJECT_NAME)

# --- CONTEXTES D'EXÉCUTION (Alignement des droits WSL2) ---
PHP_CONT       = $(DOCKER_COMPOSE) exec app
NPM_CONT       = $(DOCKER_COMPOSE) exec -u $(shell id -u):$(shell id -g) app
COMPOSER_CONT  = $(DOCKER_COMPOSE) exec -u $(shell id -u):$(shell id -g) app
CONSOLE        = $(COMPOSER_CONT) bin/console

# Couleurs pour le "make help"
HELP_COLOR = \033[36m
NO_COLOR   = \033[0m

.DEFAULT_GOAL := help
.PHONY: help setup install wait-db up down stop restart build ps logs sh shell cmd cs vendor sf cc cc-hard db-main-create db-main-migration db-main-migrate db-main-drop db-main-reset db-log-create db-log-migration db-log-migrate db-log-drop db-log-reset check-migrations-bundle db-install db-setup db-test-setup messenger-setup stan perm composer composer-rm npm npm-rm npm-setup qa test create-admin fixtures fixtures-reset worker worker-failed worker-retry

## —— SYSTEM & CONFIGURATION ⚙️ ————————————————————————————————————————————————

setup: ## Lance l'assistant de configuration (.env.docker.local, .env.local, CI)
	@if [ -f $(ENV_FILE) ]; then \
	   echo "⚠️ Le fichier $(ENV_FILE) existe déjà — supprime-le pour reconfigurer."; \
	else \
	   ./setup.sh; \
	fi

# Installation complète, en deux temps quand le projet n'est pas encore
# configuré : `setup.sh` écrit $(ENV_FILE), puis on RELANCE make. Ce second make
# est indispensable — les variables du fichier d'environnement (PROJECT_NAME…)
# sont lues à la lecture du Makefile, donc bien avant que la recette ne tourne.
ifeq (,$(wildcard ./$(ENV_FILE)))
install:
	@./setup.sh
	@$(MAKE) --no-print-directory install
else
install: ## Installe le projet de A à Z (configuration, Docker, dépendances, bases, assets)
	$(DOCKER_COMPOSE) up -d --build
	$(COMPOSER_CONT) composer install
	$(COMPOSER_CONT) composer recipes:install symfony/apache-pack --force
	$(NPM_CONT) npm install
	$(NPM_CONT) npm run dev
	$(MAKE) wait-db
	$(MAKE) db-install
	$(MAKE) db-test-setup
	$(MAKE) cc
	@printf "\n\033[0;32m🚀 L'application est prête sur http://$(PROJECT_NAME).localhost\033[0m\n"
	@printf "   Webmail (Mailpit)  http://mail.$(PROJECT_NAME).localhost\n\n"
	@printf "   Pour ouvrir le back-office, crée un compte :\n"
	@printf "     \033[36mmake create-admin email=admin@exemple.fr password=motdepasse\033[0m\n"
	@printf "   Et pour un catalogue de démonstration : \033[36mmake fixtures\033[0m\n\n"
endif

# Garde interne (pas de `##` : volontairement absente de `make help`).
#
# `up -d` rend la main dès que les conteneurs sont créés ; MariaDB, lui, met
# quelques secondes à accepter les connexions. Sans cette attente, la première
# commande Doctrine de `make install` tombe sur un serveur qui n'écoute pas
# encore.
wait-db:
	@printf "⏳ Attente de la base de données"
	@for i in $$(seq 1 60); do \
	   if $(DOCKER_COMPOSE) exec -T database healthcheck.sh --connect >/dev/null 2>&1; then \
	      printf " ✅\n"; exit 0; \
	   fi; \
	   printf "."; sleep 2; \
	done; \
	printf "\n\033[0;31m❌ La base n'a pas répondu au bout de 120 s (make logs pour voir pourquoi).\033[0m\n"; \
	exit 1

help: ## Affiche cette aide organisée par catégories
	@printf "\n$(HELP_COLOR)Usage:$(NO_COLOR)\n  make [commande]\n\n"
	@awk ' \
	   BEGIN {FS = ":.*?## "} \
	   /^[a-zA-Z_-]+:.*?## / { \
	      if (current_cat != "") { \
	         printf "  $(HELP_COLOR)%-20s$(NO_COLOR) %s\n", $$1, $$2; \
	      } \
	   } \
	   /^## ——/ { \
	      current_cat = substr($$0, 4); \
	      printf "\n\033[1;35m%s\033[0m\n", current_cat; \
	   } \
	' Makefile
	@printf "\n"

## —— DOCKER CONTENEURS 🐳 —————————————————————————————————————————————————————

up: ## Démarre les conteneurs en arrière-plan
	$(DOCKER_COMPOSE) up -d

down: ## Arrête et supprime les conteneurs (usage: 'make down' ou 'make down cmd=-v' pour purger les volumes)
	$(DOCKER_COMPOSE) down $(cmd)

stop: ## Arrête les conteneurs (sans les supprimer)
	$(DOCKER_COMPOSE) stop

restart: stop up ## Redémarre les services

build: ## Reconstruit les images Docker proprement (sans cache)
	$(DOCKER_COMPOSE) build --pull --no-cache

ps: ## Affiche l'état des conteneurs
	$(DOCKER_COMPOSE) ps

logs: ## Affiche les logs de tous les services en temps réel
	$(DOCKER_COMPOSE) logs -f

logs-mailer: ## Affiche les logs du serveur de mail local (Mailpit)
	$(DOCKER_COMPOSE) logs -f mailer

sh: shell ## Raccourci pour accéder au shell
shell: ## Entre dans le terminal du conteneur d'application (Zsh par défaut, ou Bash)
	$(PHP_CONT) zsh || $(PHP_CONT) bash

cmd: ## Exécute une commande libre dans le conteneur app (usage: make cmd cmd="ls -la")
	$(PHP_CONT) $(cmd)

## —— COMPOSER & SYMFONY 🎵 ————————————————————————————————————————————————————

sf: ## Exécute une commande Symfony console (usage: make sf cmd="debug:router")
	$(CONSOLE) $(cmd)

cc: ## Vide le cache de l'application Symfony
	$(CONSOLE) cache:clear

vendor: ## Installe les dépendances PHP via Composer
	$(COMPOSER_CONT) composer install

composer: ## Exécute une commande composer libre (usage: make composer cmd="require symfony/uid")
	$(COMPOSER_CONT) composer $(cmd)

composer-update: ## Met à jour l'intégralité des dépendances Composer (composer update)
	$(COMPOSER_CONT) composer update

composer-update-pkg: ## Met à jour un paquet spécifique (usage: make update-pkg cmd="symfony/uid")
	$(COMPOSER_CONT) composer update $(cmd)

composer-rm: ## Supprime brutalement le dossier vendor (pour reset)
	$(PHP_CONT) rm -rf vendor/

## —— BASE DE DONNÉES 🗄️ ———————————————————————————————————————————————————————

# Garde interne (pas de `##` : volontairement absente de `make help`).
#
# Les migrations de ce projet sont pilotées par le bundle privé
# florimond/multi-db-migrations-bundle. Sans accès aux dépôts privés il n'est
# pas installé, et Symfony répondrait par une CommandNotFoundException
# illisible : on dit franchement ce qu'il manque.
#
# Elle protège les cibles qui supposent le projet complètement installé
# (génération de migration, reset, install), pas `db-*-migrate` qui, sans le
# bundle, n'a simplement rien à appliquer.
check-migrations-bundle:
	@$(CONSOLE) list florimond:migrations >/dev/null 2>&1 || { \
	   printf "\033[0;31m❌ Le bundle florimond/multi-db-migrations-bundle n'est pas installé.\033[0m\n"; \
	   printf "   Les migrations de ce projet passent par lui.\n"; \
	   printf "   → Lance \033[36mmake install\033[0m avec un token GitHub valide.\n"; \
	   exit 1; \
	}


## —— BASE DE DONNÉES PRINCIPALE (MAIN) 🏛️ —————————————————————————————————————

db-main-create: ## Crée la base de données principale
	$(CONSOLE) doctrine:database:create --if-not-exists --connection=default

db-main-migration: check-migrations-bundle ## Génère le fichier de migration pour la base principale (patché en CustomMigration)
	$(CONSOLE) florimond:migrations:diff main

# Pourquoi la commande Doctrine et pas le wrapper `florimond:migrations:migrate` ?
# Le wrapper relaie la commande via un ArrayInput qui reste interactif : la
# confirmation « You are about to execute a migration… » attend une réponse et
# bloque `make install`. On appelle donc Doctrine directement, avec le même
# `--em` que le wrapper. Le tri des migrations entre les deux bases ne vient pas
# de lui mais des migrations elles-mêmes (CustomMigration::preUp), qui s'ignorent
# quand la connexion ne correspond pas à leur base cible.
db-main-migrate: ## Applique les migrations sur la base principale
	$(CONSOLE) doctrine:migrations:migrate --em=default --allow-no-migration --no-interaction

db-main-drop: ## Supprime brutalement la base principale (attention !)
	$(CONSOLE) doctrine:database:drop --if-exists --force --connection=default

db-main-reset: check-migrations-bundle db-main-drop db-main-create db-main-migrate messenger-setup ## Réinitialise à blanc la base principale


## —— BASE DE DONNÉES DES LOGS (LOG) 📝 ————————————————————————————————————————

db-log-create: ## Crée la base de données des logs
	$(CONSOLE) doctrine:database:create --if-not-exists --connection=log

db-log-migration: check-migrations-bundle ## Génère le fichier de migration pour la base de logs (patché en CustomMigration)
	$(CONSOLE) florimond:migrations:diff log

db-log-migrate: ## Applique les migrations spécifiques à la base de logs
	$(CONSOLE) doctrine:migrations:migrate --em=log --allow-no-migration --no-interaction

db-log-drop: ## Supprime brutalement la base de logs
	$(CONSOLE) doctrine:database:drop --if-exists --force --connection=log

db-log-reset: check-migrations-bundle db-log-drop db-log-create db-log-migrate ## Réinitialise à blanc la base de logs


## —— COMMANDES DE CONFIGURATION GLOBALE 🌐 ————————————————————————————————————

db-install: check-migrations-bundle ## Crée les bases si besoin et applique les migrations (sans rien supprimer)
	$(CONSOLE) doctrine:database:create --connection=default --if-not-exists --no-interaction
	$(CONSOLE) doctrine:database:create --connection=log --if-not-exists --no-interaction
	$(MAKE) db-main-migrate
	$(MAKE) db-log-migrate
	$(MAKE) messenger-setup

db-setup: ## (bouton panic) Supprime les deux bases, les recrée et rejoue les migrations
	$(MAKE) db-main-drop
	$(MAKE) db-log-drop
	$(MAKE) db-install

## —— MESSENGER & WORKERS 📨 ————————————————————————————————————————————————————

# La table de file d'attente n'est créée par aucune migration : le transport
# Doctrine est configuré en `auto_setup=0` (voir MESSENGER_TRANSPORT_DSN), pour
# que le worker ne tente pas un CREATE TABLE à chaud à chaque démarrage. C'est
# donc l'installation qui la met en place, une fois.
messenger-setup: ## Crée la table de file d'attente (messenger_messages) sur la base principale
	$(CONSOLE) messenger:setup-transports

worker: ## Lance le worker Messenger en premier plan (emails async, retry automatique)
	$(CONSOLE) messenger:consume async --time-limit=3600 -vv

worker-failed: ## Affiche les messages en échec dans la file failed
	$(CONSOLE) messenger:failed:show -vv

worker-retry: ## Réessaie les messages en échec (usage: make worker-retry ou make worker-retry id=42)
	$(CONSOLE) messenger:failed:retry $(id) -vv

## —— FRONTEND & NPM 📦 ————————————————————————————————————————————————————————

npm: ## Exécute une commande npm libre (usage: make npm cmd="install lucide-react")
	$(NPM_CONT) npm $(cmd)

npm-rm: ## Supprime brutalement le dossier node_modules (pour reset)
	$(PHP_CONT) rm -rf node_modules/

npm-dev: ## Lance une compilation simple des assets en mode développement
	$(NPM_CONT) npm run dev

watch: ## Lance le serveur de build d'assets en temps réel (Watcher / Auto-recompile)
	$(NPM_CONT) npm run watch

npm-build: ## Compile les assets pour la production
	$(NPM_CONT) npm run build

npm-setup: npm-rm npm-build ## Réinstallation propre de NPM et build complet du front

## —— ADMINISTRATION 👤 ————————————————————————————————————————————————————————

# Les deux arguments sont obligatoires côté commande : un skeleton ne doit pas
# pouvoir créer un compte administrateur avec des identifiants par défaut.
create-admin: ## Crée ou promeut un compte admin (usage: make create-admin email=x password=x)
	@if [ -z "$(email)" ] || [ -z "$(password)" ]; then \
	   printf "\033[0;31m❌ email et password sont obligatoires.\033[0m\n"; \
	   printf "   → \033[36mmake create-admin email=admin@exemple.fr password=motdepasse\033[0m\n"; \
	   exit 1; \
	fi
	$(CONSOLE) app:create-admin $(email) $(password)

fixtures: ## Charge les fixtures de développement (sans vider la base)
	$(CONSOLE) doctrine:fixtures:load --no-interaction --append

fixtures-reset: db-main-reset ## Réinitialise la base principale et recharge les fixtures
	$(CONSOLE) doctrine:fixtures:load --no-interaction --append

## —— QUALITÉ, TESTS & DROITS 🛠️ ————————————————————————————————————————————————

cs: ## Corrige le style de code PHP selon la configuration d'entreprise (.php-cs-fixer.dist.php)
	$(COMPOSER_CONT) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

stan: ## Analyse statique du code avec PHPStan
	$(COMPOSER_CONT) vendor/bin/phpstan analyse --memory-limit=1G

rector: ## Prévisualise les modernisations Rector sans les appliquer (dry-run)
	$(COMPOSER_CONT) vendor/bin/rector process --dry-run

rector-fix: ## Applique les modernisations Rector au code
	$(COMPOSER_CONT) vendor/bin/rector process

db-test-setup: ## Crée et initialise les bases de données de test via le schéma Doctrine
	$(CONSOLE) --env=test doctrine:database:drop --if-exists --force --connection=default
	$(CONSOLE) --env=test doctrine:database:create --connection=default
	$(CONSOLE) --env=test doctrine:schema:create --em=default
	$(CONSOLE) --env=test doctrine:database:drop --if-exists --force --connection=log
	$(CONSOLE) --env=test doctrine:database:create --connection=log

test: ## Lance les tests avec PHPUnit 10 (lancer db-test-setup d'abord si besoin)
	$(COMPOSER_CONT) vendor/bin/phpunit -c phpunit.dist.xml

qa: cs stan test ## Lance la suite de contrôle qualité complète (CS-Fixer + PHPStan + PHPUnit)

perm: ## Répare les permissions des fichiers (Optimisé WSL2 & VS Code)
	$(DOCKER_COMPOSE) exec -u root app chown -R www-data:www-data var/
	sudo chown -R $(shell id -u):$(shell id -g) .
