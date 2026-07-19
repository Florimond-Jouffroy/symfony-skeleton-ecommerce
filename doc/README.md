# 📚 Documentation du skeleton

Guides d'architecture et de fonctionnement, pour un développeur qui reprend le
projet.

| Guide | Contenu |
|-------|---------|
| [1. Docker & environnement](./1-docker-and-environment.md) | Conteneurs, Traefik, les fichiers `.env`, `setup.sh`, Makefile |
| [2. Architecture Backend & DDD](./2-backend-architecture.md) | Couches, managers, autorisation, machines à états, paiement, auth, async, config dynamique |
| [3. Workflow Frontend](./3-frontend-workflow.md) | React 19, Tailwind v4, Shadcn, les SPA par section, intégration Symfony↔React |
| [4. Qualité de code & tests](./4-qa-and-tests.md) | php-cs-fixer, PHPStan, Rector, PHPUnit, DAMA, CI |
| [5. Double base de données & migrations](./5-multi-database-and-migrations.md) | Les 2 bases `main`/`log`, cloisonnement des EM, convention `CustomMigration`, pièges |

## Par où commencer

- **Installer / lancer le projet** → [guide 1](./1-docker-and-environment.md).
- **Comprendre l'architecture générale** → [guide 2](./2-backend-architecture.md).
- **Toucher à la base de données ou générer une migration** → [guide 5](./5-multi-database-and-migrations.md)
  **avant** de lancer quoi que ce soit. C'est là que se trouvent les deux pièges
  les plus coûteux du projet (`auto_mapping` et `CustomMigration`).
