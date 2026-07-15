#!/bin/bash

GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${CYAN}====================================================${NC}"
echo -e "${GREEN}    🚀 CONFIGURATION ET INSTALLATION DU PROJET       ${NC}"
echo -e "${CYAN}====================================================${NC}"

# 1. Récupération ou définition du nom du projet
default_name="symfony-app"
if [ -f .env.docker.local ]; then
    project_name=$(grep PROJECT_NAME .env.docker.local | cut -d '=' -f2)
    echo -e "${GREEN}ℹ️ Projet existant détecté : ${project_name}${NC}"
else
    read -p "📝 Nom du projet (slug local, ex: mon-blog) [$default_name] : " project_name
    project_name=${project_name:-$default_name}
    project_name=$(echo "$project_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
fi

# 2. Demande du Token GitHub Privé (indispensable pour l'accès aux bundles privés)
echo -e "\n${CYAN}🔐 Authentification GitHub Privée${NC}"
read -sp "🔑 Colle ton GitHub Personal Access Token (PAT) : " github_token
echo -e ""

if [ -z "$github_token" ]; then
    echo -e "${RED}❌ Erreur : Le token GitHub est obligatoire pour installer les dépendances privées.${NC}"
    exit 1
fi

echo -e "\n${YELLOW}⏳ Configuration des fichiers d'environnement locaux...${NC}"

# 3. Écriture sécurisée du .env.docker.local
cat <<EOF > .env.docker.local
PROJECT_NAME=${project_name}
LOCAL_MACHINE_DOMAIN=${project_name}.localhost
GITHUB_COMPOSER_TOKEN=${github_token}
EOF

# 4. Gestion intelligente du .env.local
if [ ! -f .env.local ]; then
    echo -e "${YELLOW}⏳ Création du fichier .env.local initial...${NC}"
    cat <<EOF > .env.local
# Connexions de base générées par le setup (DYNAMIQUE PAR PROJET)
DATABASE_URL=mysql://root:root@${project_name}-db:3306/app_main?serverVersion=mariadb-10.11.15&charset=utf8mb4
LOG_DATABASE_URL=mysql://root:root@${project_name}-db:3306/app_log?serverVersion=mariadb-10.11.15&charset=utf8mb4
DATABASE_URL_TEST=mysql://root:@${project_name}-db-test:3306/app_test?serverVersion=mariadb-10.11.15

# Variable dynamique pour ton FlorimondCoreBundle
FLORIMOND_CORE_SITE_NAME=${project_name}
EOF
else
    echo -e "${GREEN}ℹ️ Un fichier .env.local existe déjà. Vérification des variables de connexion...${NC}"
    grep -q "DATABASE_URL=" .env.local || echo "DATABASE_URL=mysql://root:root@${project_name}-db:3306/app_main?serverVersion=mariadb-10.11.15&charset=utf8mb4" >> .env.local
    grep -q "LOG_DATABASE_URL=" .env.local || echo "LOG_DATABASE_URL=mysql://root:root@${project_name}-db:3306/app_log?serverVersion=mariadb-10.11.15&charset=utf8mb4" >> .env.local
    grep -q "FLORIMOND_CORE_SITE_NAME=" .env.local || echo "FLORIMOND_CORE_SITE_NAME=${project_name}" >> .env.local
fi

# 5. Démarrage de l'infrastructure Docker
echo -e "\n${YELLOW}🐳 Lancement de l'environnement Docker...${NC}"
docker compose --env-file .env.docker.local -p "${project_name}" up -d --build

echo -e "${YELLOW}⏳ Attente de la stabilisation des bases de données...${NC}"
sleep 5

# 6. Installation initiale des dépendances (Nécessaire pour charger Flex)
echo -e "\n${YELLOW}🎵 Installation des dépendances via Composer...${NC}"
docker compose --env-file .env.docker.local -p "${project_name}" exec app composer install

# 7. Installation des bundles s'ils ne sont pas déjà présents
if grep -q "florimond/log-bundle" composer.json; then
    echo -e "${GREEN}ℹ️ Le bundle de log est déjà présent dans le composer.json.${NC}"
else
    echo -e "${YELLOW}📦 Téléchargement du Bundle de Logs Privé...${NC}"
    docker compose --env-file .env.docker.local -p "${project_name}" exec app composer require florimond/log-bundle:^1.1 --no-interaction --no-scripts
fi

if grep -q "florimond/multi-db-migrations-bundle" composer.json; then
    echo -e "${GREEN}ℹ️ Le bundle multi-db-migrations est déjà présent dans le composer.json.${NC}"
else
    echo -e "${YELLOW}📦 Téléchargement du Bundle Multi-DB Migrations Privé...${NC}"
    docker compose --env-file .env.docker.local -p "${project_name}" exec app composer require florimond/multi-db-migrations-bundle:^1.0 --no-interaction --no-scripts
fi

if grep -q "florimond/core-bundle" composer.json; then
    echo -e "${GREEN}ℹ️ Le Florimond Core Bundle est déjà présent dans le composer.json.${NC}"
else
    echo -e "${YELLOW}📦 Téléchargement du Florimond Core Bundle Privé...${NC}"
    docker compose --env-file .env.docker.local -p "${project_name}" exec app composer require florimond/core-bundle:^1.0 --no-interaction --no-scripts
fi

# 7d. 🛠️ DÉPLOIEMENT DES RECETTES LOCALES
echo -e "${YELLOW}⚙️ Déploiement des configurations des bundles (Recettes locales)...${NC}"

# Nettoyage préventif du fichier par défaut de Symfony avant d'appliquer tes surcharges de recettes
docker compose --env-file .env.docker.local -p "${project_name}" exec app rm -f config/packages/doctrine_migrations.yaml

if [ -d "vendor/florimond/log-bundle/recipe" ]; then
    docker compose --env-file .env.docker.local -p "${project_name}" exec app cp -R vendor/florimond/log-bundle/recipe/config/packages/. config/packages/
fi

if [ -d "vendor/florimond/multi-db-migrations-bundle/recipe" ]; then
    docker compose --env-file .env.docker.local -p "${project_name}" exec app cp -R vendor/florimond/multi-db-migrations-bundle/recipe/config/packages/. config/packages/
    echo -e "${GREEN}✅ Recette Multi-DB Migrations appliquée !${NC}"
else
    echo -e "${RED}❌ Erreur : Dossier recipe introuvable dans le bundle multi-db-migrations.${NC}"
fi

if docker compose --env-file .env.docker.local -p "${project_name}" exec app test -d vendor/florimond/core-bundle/recipe; then
    docker compose --env-file .env.docker.local -p "${project_name}" exec app sh -c "find vendor/florimond/core-bundle/recipe -name florimond_core.yaml -exec cp {} config/packages/ \;" 2>/dev/null
    echo -e "${GREEN}✅ Recette Florimond CoreBundle appliquée !${NC}"
fi

# 🌟 FIX DE SYNCHRONISATION WSL2 : On laisse 2 secondes au système de fichiers pour propager les configurations copiées
sleep 2

echo -e "${YELLOW}🧹 Nettoyage final du cache Symfony...${NC}"
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console cache:clear

# =====================================================================
# 8. Mise en place de la Base de données
# =====================================================================
echo -e "${YELLOW}⏳ Création des bases de données physiques manquantes...${NC}"
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console doctrine:database:create --connection=default --if-not-exists --no-interaction
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console doctrine:database:create --connection=log --if-not-exists --no-interaction

# Initialisation obligatoire des répertoires requis par ton bundle
docker compose --env-file .env.docker.local -p "${project_name}" exec app mkdir -p migrations/main migrations/log

echo -e "${YELLOW}⏳ Exécution des anciennes migrations...${NC}"
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console florimond:migrations:migrate main --no-interaction
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console florimond:migrations:migrate log --no-interaction

echo -e "${YELLOW}⚡ Génération des fichiers de migrations initiaux (Main + Log) via ton bundle...${NC}"

# 1. Génération et patch automatique pour la base PRINCIPALE via ton bundle
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console florimond:migrations:diff main --no-interaction

# 2. Vérification et génération pour la base de LOGS
log_migration_count=$(docker compose --env-file .env.docker.local -p "${project_name}" exec app sh -c "find migrations/log -name '*.php' 2>/dev/null | wc -l" | tr -d '\r')

if [ "${log_migration_count}" -eq 0 ]; then
    echo -e "${YELLOW}⚡ Première installation : Génération de la migration pour la base de logs via ton bundle...${NC}"
    # 🌟 FIX : On passe "yes" via un pipe pour valider automatiquement toute question bloquante de Doctrine
    yes | docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console florimond:migrations:diff log --no-interaction > /dev/null 2>&1
fi

echo -e "${YELLOW}⏳ Exécution finale et synchronisation des tables...${NC}"
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console florimond:migrations:migrate main --no-interaction
docker compose --env-file .env.docker.local -p "${project_name}" exec app bin/console florimond:migrations:migrate log --no-interaction

echo -e "\n${YELLOW}🎨 Compilation des assets Frontend (Webpack Encore)...${NC}"
docker compose --env-file .env.docker.local -p "${project_name}" exec app npm install
docker compose --env-file .env.docker.local -p "${project_name}" exec app npm run dev
echo -e "${GREEN}✅ Assets Frontend compilés avec succès !${NC}"

echo -e "\n${GREEN}====================================================${NC}"
echo -e "${GREEN}🎉 TEMPLATE INITIALISÉ ET PRÊT POUR LE DEV !${NC}"
echo -e "${GREEN}====================================================${NC}"
echo -e "${GREEN}🚀 L'application est prête sur http://${project_name}.localhost !${NC}\n"
