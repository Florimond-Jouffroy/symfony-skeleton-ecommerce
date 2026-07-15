 # 🚀 Lancer un nouveau projet avec le Skeleton Symfony + React
 
 Bienvenue sur le Skeleton officiel de l'application. Ce projet est pré-configuré pour Docker, PHP 8.4, PHPUnit 10, React, Tailwind v4 et Shadcn UI avec une architecture multi-bases de données.
 
 ---
 
 ## 📚 Documentations Détaillées
 
 Pour comprendre en détail le fonctionnement et la philosophie de chaque brique de ce Skeleton, consulte les guides dédiés dans le dossier `/doc` :
 * [🐳 1. Guide Docker & Environnement](./doc/1-docker-and-environment.md)
 * [🐘 2. Architecture Backend & DDD](./doc/2-backend-architecture.md)
 * [🎨 3. Workflow Frontend (React/Tailwind v4/Shadcn)](./doc/3-frontend-workflow.md)
 * [🧪 4. Qualité de Code & Tests (PHPUnit 10)](./doc/4-qa-and-tests.md)
 
 ---
 
 ## Prérequis
 Avant de démarrer, assure-toi que ton infrastructure globale (`_infra`) est bien lancée et opérationnelle sur ton PC.
 
 ## Procédure d'initialisation
 
 ### 1. Cloner ou dupliquer le Skeleton
 Crée ton nouveau répertoire de projet dans ton dossier habituel, puis place-toi à l'intérieur.
 
 ### 2. Configurer l'environnement local
 Lance la commande suivante pour générer tes fichiers de configuration personnalisés :
 ```bash
 make setup
 ```
 Le script `setup.sh` va se lancer automatiquement dans ton terminal pour te demander le nom de ton projet (ex: `mon-super-blog`). Il créera automatiquement les fichiers `.env.docker.local` et `.env.local`.
 
 ### 3. Installer et démarrer le projet
 Exécute la commande magique pour tout orchestrer d'un coup (téléchargement des images Docker, build du conteneur Apache/PHP, installation des dépendances Composer/NPM, et création automatique de tes bases de données `main` et `log`) :
 ```bash
 make install
 ```

## 🌍 Accès aux services
Une fois l'installation terminée avec succès, tes services personnels sont disponibles instantanément aux adresses suivantes :

* 💻 **Application Symfony :** http://<nom-de-ton-projet>.localhost
* 📬 **Boîte Mailpit (Capture de mails) :** http://mail.<nom-de-ton-projet>.localhost
* 🗄️ **Gestion de la BDD (phpMyAdmin global) :** http://localhost:8080 (ou via ton url globale d'infra)

## 🛠️ Commandes utiles au quotidien

### 🐳 Docker & Environnement
* `make up` : Démarre les conteneurs du projet en arrière-plan.
* `make down` : Arrête et supprime les conteneurs (ajoute `cmd=-v` pour purger les volumes).
* `make ps` : Affiche l'état et les ports des conteneurs qui tournent.
* `make shell` (ou `make sh`) : Ouvre un terminal (Zsh/Starship) directement dans le conteneur applicatif PHP.
* `make cmd cmd="ls -la"` : Exécute une commande rapide dans le conteneur sans y entrer.

### 🧙‍♂️ Composer & Symfony
* `make composer cmd="require <package>"` : Exécute une commande Composer dans le conteneur.
* `make sf cmd="debug:autowiring"` : Exécute une commande de la console Symfony.
* `make cc` : Vide instantanément le cache de l'application Symfony.

### 🗄️ Base de données (Multi-DB : Main + Log)
* `make make-migration` : Génère un nouveau fichier de migration Doctrine (diff).
* `make migrate` : Applique les migrations en attente sur la base de données principale.
* `make db-setup` : **(Bouton Panic)** Réinitialise complètement et re-migre à blanc les deux bases de données (`main` et `log`).

### 📦 Frontend & Assets
* `make watch` : **(Recommandé en dev)** Lance le build d'assets en temps réel avec auto-recompilation (idéal pour React).
* `make npm cmd="install <package>"` : Exécute une commande NPM à la volée.
* `make npm-setup` : Purge le dossier `node_modules` et reconstruit proprement tous tes assets.

### 🚀 Qualité de code & Tests (QA)
* `make cs` : Aligne et corrige automatiquement ton code PHP selon les standards de l'entreprise via PHP-CS-Fixer.
* `make stan` : Déclenche l'analyse statique PHPStan pour débusquer les bugs cachés.
* `make qa` : **(Recommandé avant chaque commit)** Exécute la suite complète de contrôle qualité (CS-Fixer + PHPStan + PHPUnit).
* `make perm` : Répare instantanément les conflits de droits de fichiers (indispensable pour l'interopérabilité WSL2 & Windows).
