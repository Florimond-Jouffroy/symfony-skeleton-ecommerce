#!/bin/bash
#
# =============================================================================
#  Assistant de configuration du projet
# -----------------------------------------------------------------------------
#  Ce script ne fait QUE de la configuration : il pose les questions dont
#  personne d'autre ne connaît la réponse (nom du projet, token GitHub, CI) et
#  écrit les fichiers d'environnement qui en découlent.
#
#  Il n'installe rien. Docker, les dépendances, les bases et les assets sont
#  l'affaire de `make install`, qui est idempotent et peut donc être relancé à
#  volonté. C'est cette séparation qui évite de tout installer deux fois quand
#  on enchaîne `make setup` puis `make install`.
#
#  Fichiers produits :
#    .env.docker.local  nom du projet, domaine local, token GitHub (gitignoré)
#    .env.local         URLs de connexion et secrets propres au projet (gitignoré)
# =============================================================================

set -euo pipefail

GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${CYAN}====================================================${NC}"
echo -e "${GREEN}    ⚙️  CONFIGURATION DU PROJET                       ${NC}"
echo -e "${CYAN}====================================================${NC}"

# 1. Récupération ou définition du nom du projet
default_name="symfony-shop"
if [ -f .env.docker.local ]; then
    project_name=$(grep -E '^PROJECT_NAME=' .env.docker.local | tail -n 1 | cut -d '=' -f2-)
    echo -e "${GREEN}ℹ️ Projet existant détecté : ${project_name}${NC}"
else
    read -p "📝 Nom du projet (slug local, ex: mon-super-shop) [$default_name] : " project_name
    project_name=${project_name:-$default_name}
    project_name=$(echo "$project_name" | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
fi

# 2. Token GitHub privé — indispensable : les trois bundles `florimond/*` du
#    composer.json vivent sur des dépôts privés.
echo -e "\n${CYAN}🔐 Authentification GitHub Privée${NC}"
read -sp "🔑 Colle ton GitHub Personal Access Token (PAT) : " github_token
echo -e ""

if [ -z "$github_token" ]; then
    echo -e "${RED}❌ Erreur : Le token GitHub est obligatoire pour installer les dépendances privées.${NC}"
    exit 1
fi

# 2b. Choix : activer ou non l'intégration continue (CI GitHub Actions)
echo -e "\n${CYAN}🤖 Intégration Continue${NC}"
read -p "⚙️  Activer la CI GitHub Actions pour ce projet ? [o/N] : " enable_ci
enable_ci=$(echo "${enable_ci:-n}" | tr '[:upper:]' '[:lower:]')

echo -e "\n${YELLOW}⏳ Configuration des fichiers d'environnement locaux...${NC}"

# 3. Écriture du .env.docker.local (lu par le Makefile et par compose.yaml)
cat <<EOF > .env.docker.local
PROJECT_NAME=${project_name}
LOCAL_MACHINE_DOMAIN=${project_name}.localhost
GITHUB_COMPOSER_TOKEN=${github_token}
EOF

# 3b. Génération des secrets propres au projet
#
# APP_SECRET signe les cookies « remember me », les URI signées et les jetons
# CSRF ; le pepper du log-bundle sale la pseudonymisation des IP. Les deux sont
# vides ou en clair dans le `.env` versionné du template : laissés tels quels,
# n'importe qui connaissant le squelette peut forger les signatures ou
# ré-identifier les IP. Ils ne doivent donc jamais être partagés entre deux
# projets, ni committés — on les génère ici, dans .env.local, qui est gitignoré.
generate_secret() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
    else
        head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'
    fi
}

# Écrit VAR=<secret aléatoire> dans le fichier, sauf si la variable y porte déjà
# une valeur non vide (on ne casse jamais les sessions d'un projet en cours).
ensure_secret() {
    local env_file="$1" var="$2" label="$3" current

    current=$(grep -E "^${var}=" "${env_file}" 2>/dev/null | tail -n 1 | cut -d '=' -f2- | tr -d "'\" " || true)

    if [ -n "${current}" ]; then
        echo -e "${GREEN}ℹ️ ${var} déjà défini dans ${env_file}, on n'y touche pas.${NC}"
        return
    fi

    # Ligne présente mais vide : on la supprime avant de réécrire.
    sed -i "/^${var}=/d" "${env_file}" 2>/dev/null || true
    printf '\n# %s (généré au setup, propre à ce projet)\n%s=%s\n' "${label}" "${var}" "$(generate_secret)" >> "${env_file}"
    echo -e "${GREEN}✅ ${var} généré dans ${env_file}.${NC}"
}

# 4. Gestion intelligente du .env.local
#
# L'hôte des bases est le nom du conteneur (`${projet}-db`) et non le nom de
# service `database` : les projets partagent le réseau `gateway`, où seul le nom
# de conteneur est unique.
db_dsn() { echo "mysql://root:root@${project_name}-db:3306/$1?serverVersion=mariadb-10.11.15&charset=utf8mb4"; }

if [ ! -f .env.local ]; then
    echo -e "${YELLOW}⏳ Création du fichier .env.local initial...${NC}"
    cat <<EOF > .env.local
# Connexions de base générées par le setup (DYNAMIQUE PAR PROJET)
# La base de test est la même, suffixée `_test` par Doctrine (voir doctrine.yaml).
DATABASE_URL=$(db_dsn app_main)
LOG_DATABASE_URL=$(db_dsn app_log)

# Variable dynamique pour ton FlorimondCoreBundle
FLORIMOND_CORE_SITE_NAME=${project_name}
EOF
else
    echo -e "${GREEN}ℹ️ Un fichier .env.local existe déjà. Vérification des variables de connexion...${NC}"
    grep -q "^DATABASE_URL=" .env.local             || echo "DATABASE_URL=$(db_dsn app_main)" >> .env.local
    grep -q "^LOG_DATABASE_URL=" .env.local         || echo "LOG_DATABASE_URL=$(db_dsn app_log)" >> .env.local
    grep -q "^FLORIMOND_CORE_SITE_NAME=" .env.local || echo "FLORIMOND_CORE_SITE_NAME=${project_name}" >> .env.local
fi

ensure_secret .env.local APP_SECRET "Clé de signature de cette application"
ensure_secret .env.local FLORIMOND_LOG_IP_PSEUDONYMIZER_PEPPER "Sel de pseudonymisation des IP (log-bundle)"

# =====================================================================
# 5. Intégration Continue (opt-in)
# =====================================================================
#
# Le workflow lit le PAT dans le secret `COMPOSER_GITHUB_TOKEN` : GitHub Actions
# réserve le préfixe `GITHUB_*`, le secret ne peut donc pas reprendre le nom de
# la variable locale (GITHUB_COMPOSER_TOKEN).
case "${enable_ci}" in
    o|oui|y|yes)
        echo -e "\n${YELLOW}🤖 Configuration de la CI GitHub Actions...${NC}"
        if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1 && git remote get-url origin >/dev/null 2>&1; then
            if printf '%s' "${github_token}" | gh secret set COMPOSER_GITHUB_TOKEN >/dev/null 2>&1; then
                target_repo=$(gh repo view --json nameWithOwner -q .nameWithOwner 2>/dev/null || true)
                echo -e "${GREEN}✅ Secret CI 'COMPOSER_GITHUB_TOKEN' configuré sur ${target_repo}.${NC}"
            else
                echo -e "${RED}⚠️  Impossible de définir le secret automatiquement.${NC}"
                echo -e "${YELLOW}   Ajoute-le à la main : Settings ▸ Secrets and variables ▸ Actions ▸ 'COMPOSER_GITHUB_TOKEN' (valeur = ton PAT).${NC}"
            fi
        else
            echo -e "${YELLOW}ℹ️ gh non authentifié ou dépôt distant absent : secret CI non configuré automatiquement.${NC}"
            echo -e "${YELLOW}   Une fois le dépôt GitHub créé, ajoute le secret 'COMPOSER_GITHUB_TOKEN' (valeur = ton PAT).${NC}"
        fi
        ;;
    *)
        echo -e "\n${YELLOW}🚫 CI non activée : suppression du workflow GitHub Actions.${NC}"
        rm -f .github/workflows/ci.yml
        rmdir .github/workflows .github 2>/dev/null || true
        ;;
esac

echo -e "\n${GREEN}====================================================${NC}"
echo -e "${GREEN}✅ CONFIGURATION TERMINÉE${NC}"
echo -e "${GREEN}====================================================${NC}"
echo -e "${CYAN}Prochaine étape :${NC} ${YELLOW}make install${NC}"
echo -e "${CYAN}  → images Docker, dépendances, bases de données, assets.${NC}\n"
