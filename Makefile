# --- VARIABLES & ENVIRONNEMENT ---
ENV_FILE := .env.docker.local
SAFE_TARGETS := setup help

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
.PHONY: help setup install up down stop restart build ps logs sh shell cmd cs vendor sf cc cc-hard db-main-create db-main-migration db-main-migrate db-main-drop db-main-reset db-log-create db-log-migrate db-log-drop db-log-reset db-setup db-test-setup stan perm composer composer-rm npm npm-rm npm-setup qa test

## —— SYSTEM & CONFIGURATION ⚙️ ————————————————————————————————————————————————

setup: ## Lance l'assistant de configuration initial (.env.docker.local)
	@if [ -f $(ENV_FILE) ]; then \
	   echo "⚠️ Le fichier $(ENV_FILE) existe déjà."; \
	else \
	   ./setup.sh; \
	fi

install: ## Configuration, build docker, installation Composer/NPM et migrations BDD
	@if [ ! -f $(ENV_FILE) ]; then \
	   ./setup.sh; \
	fi
	$(DOCKER_COMPOSE) up -d --build
	$(COMPOSER_CONT) composer install
	$(COMPOSER_CONT) composer recipes:install symfony/apache-pack --force
	$(NPM_CONT) npm install
	$(NPM_CONT) npm run dev
	$(MAKE) db-setup
	$(MAKE) cc
	@echo "🚀 L'application est prête sur http://$(PROJECT_NAME).localhost !"

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

## —— BASE DE DONNÉES PRINCIPALE (MAIN) 🏛️ —————————————————————————————————————

db-main-create: ## Crée la base de données principale
	$(CONSOLE) doctrine:database:create --if-not-exists --connection=default

db-main-migration: ## Génère le fichier de migration pour la base principale
	$(CONSOLE) make:migration

db-main-migrate: ## Applique les migrations sur la base principale
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --em=default

db-main-drop: ## Supprime brutalement la base principale (attention !)
	$(CONSOLE) doctrine:database:drop --if-exists --force --connection=default

db-main-reset: db-main-drop db-main-create db-main-migrate ## Réinitialise à blanc la base principale


## —— BASE DE DONNÉES DES LOGS (LOG) 📝 ————————————————————————————————————————

db-log-create: ## Crée la base de données des logs
	$(CONSOLE) doctrine:database:create --if-not-exists --connection=log

db-log-migrate: ## Applique les migrations spécifiques à la base de logs
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --em=log

db-log-drop: ## Supprime brutalement la base de logs
	$(CONSOLE) doctrine:database:drop --if-exists --force --connection=log

db-log-reset: db-log-drop db-log-create db-log-migrate ## Réinitialise à blanc la base de logs


## —— COMMANDES DE CONFIGURATION GLOBALE 🌐 ————————————————————————————————————

db-setup: ## Réinitialise et re-migre l'intégralité des deux bases (Main + Log)
	$(MAKE) db-main-reset
	$(MAKE) db-log-reset

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

## —— QUALITÉ, TESTS & DROITS 🛠️ ————————————————————————————————————————————————

cs: ## Corrige le style de code PHP selon la configuration d'entreprise (.php-cs-fixer.dist.php)
	$(COMPOSER_CONT) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

stan: ## Analyse statique du code avec PHPStan
	$(COMPOSER_CONT) vendor/bin/phpstan analyse src --memory-limit=1G

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
