# 🚀 Skeleton e-commerce — Symfony 8 / PHP 8.4 + React 19

Base de départ **réutilisable et générique** pour un site e-commerce, pré-câblée pour
Docker, PHP 8.4, Symfony 8, PHPUnit, React 19, Tailwind v4 et Shadcn UI, avec une
**architecture à double base de données** (`main` applicative + `log` d'audit).

> Ce dépôt est un **skeleton** : tout doit y rester générique et configurable, pas de
> logique métier propre à un client donné.

---

## 📚 Documentation

Le fonctionnement détaillé de chaque brique est décrit dans les guides du dossier
[`/doc`](./doc/README.md) :

| Guide | Contenu |
|-------|---------|
| [🐳 1. Docker & environnement](./doc/1-docker-and-environment.md) | Conteneurs, Traefik, fichiers `.env`, `setup.sh`, Makefile |
| [🐘 2. Architecture Backend & DDD](./doc/2-backend-architecture.md) | Couches, managers, autorisation, machines à états, paiement, auth, async |
| [🎨 3. Workflow Frontend](./doc/3-frontend-workflow.md) | React 19, Tailwind v4, Shadcn, les SPA par section, intégration Symfony↔React |
| [🧪 4. Qualité de code & tests](./doc/4-qa-and-tests.md) | php-cs-fixer, PHPStan, Rector, PHPUnit, DAMA, CI |
| [🗄️ 5. Double base de données & migrations](./doc/5-multi-database-and-migrations.md) | Les 2 bases `main`/`log`, cloisonnement des EM, `CustomMigration`, pièges |

**Nouveau sur le projet ?** Commence par le [guide 1](./doc/1-docker-and-environment.md)
pour lancer l'environnement, puis le [guide 2](./doc/2-backend-architecture.md) pour
l'architecture. **Avant de toucher à la base de données**, lis le
[guide 5](./doc/5-multi-database-and-migrations.md) : il documente les deux pièges les
plus coûteux du projet (`auto_mapping` et la convention `CustomMigration`).

---

## ⚡ Démarrage rapide

### Prérequis
L'infrastructure globale (`_infra`) doit être lancée et opérationnelle sur ton poste
(Traefik + réseau Docker partagé).

### Une seule commande
```bash
make install
```
Depuis un clone frais, elle fait **tout** : elle commence par l'assistant de
configuration (nom du projet, token GitHub pour les bundles privés, CI), puis se
relance et enchaîne images Docker, dépendances Composer/NPM, build des assets,
création + migration des deux bases (`main` + `log`), table Messenger, bases de
test et vidage du cache.

Elle est **idempotente** : relance-la autant que nécessaire. `make setup` seul
permet de (re)configurer sans installer.

### Puis, pour avoir de quoi cliquer
```bash
make create-admin email=admin@exemple.fr password=motdepasse   # accès au back-office
make fixtures                                                  # catalogue de démonstration
```

---

## 🌍 Accès aux services

Une fois `make install` terminé, `<projet>` étant le nom saisi à l'installation :

* 💻 **Application** : `http://<projet>.localhost`
* 📬 **Mailpit** (capture des mails) : `http://mail.<projet>.localhost`
* 🗄️ **phpMyAdmin** (via l'infra globale) : `http://localhost:8080`

---

## 🛠️ Commandes utiles au quotidien

> Toujours passer par `make` — jamais de commande directe dans le conteneur.
> `make help` liste l'ensemble des cibles disponibles.

### 🐳 Docker & environnement
| Commande | Rôle |
|----------|------|
| `make up` / `make down` | Démarre / arrête les conteneurs (`make down cmd=-v` purge les volumes) |
| `make ps` | État et ports des conteneurs |
| `make shell` (ou `make sh`) | Ouvre un shell dans le conteneur PHP |
| `make cmd cmd="ls -la"` | Exécute une commande ponctuelle dans le conteneur |

### 🧙 Composer & Symfony
| Commande | Rôle |
|----------|------|
| `make composer cmd="require <pkg>"` | Commande Composer |
| `make sf cmd="debug:router"` | Console Symfony |
| `make cc` | Vide le cache |
| `make worker` | Lance le worker Messenger (emails async) |

### 🗄️ Base de données (double DB : main + log)
| Commande | Rôle |
|----------|------|
| `make db-main-migration` | Génère une migration sur la base **principale** (patchée en `CustomMigration`) |
| `make db-log-migration` | Génère une migration sur la base **log** |
| `make db-main-migrate` | Applique les migrations en attente sur la base principale |
| `make db-install` | Crée les bases manquantes et applique les migrations (non destructif) |
| `make db-setup` | **(Bouton panic)** Supprime les **deux** bases et rejoue tout |
| `make fixtures` / `make fixtures-reset` | (Re)charge les fixtures |

> ⚠️ Ne **jamais** générer une migration via `make:migration` directement : ça produit
> un `AbstractMigration` nu qui s'exécute sur les deux bases. Voir le
> [guide 5](./doc/5-multi-database-and-migrations.md).

### 🎨 Frontend & assets
| Commande | Rôle |
|----------|------|
| `make watch` | Build des assets en temps réel (recommandé en dev) |
| `make npm-build` | Build de production |
| `make npm cmd="install <pkg>"` | Commande NPM à la volée |
| `make npm-setup` | Purge `node_modules` et reconstruit tout le front |

### 🚀 Qualité de code & tests
| Commande | Rôle |
|----------|------|
| `make cs` | Corrige le style (php-cs-fixer, standard `@Symfony`) |
| `make stan` | Analyse statique PHPStan |
| `make qa` | **(Avant chaque commit)** Suite complète : CS + PHPStan + PHPUnit |
| `make db-test-setup && make test` | Prépare la base de test et lance PHPUnit |
| `make perm` | Répare les permissions de fichiers (WSL2 / Windows) |
