# CLAUDE.md

Guide d'assistance pour ce dépôt. Skeleton e-commerce **Symfony 8 / PHP 8.4 + React 19**, pensé comme base de départ **réutilisable et générique** (pas un projet client).

## Règle d'or

Toute fonctionnalité doit rester **générique, configurable et clean** — pas de logique métier spécifique à un client. Code de domaine en **anglais**, UI/templates en **français**.

## Commandes (toujours via `make`, jamais directement dans le conteneur)

```bash
make up / down / ps          # cycle de vie Docker
make shell                   # bash dans le conteneur app
make sf cmd="debug:router"   # console Symfony
make cc                      # cache:clear

# Base de données (double DB : main + log)
make db-main-migration       # génère une migration (base principale)
make db-setup                # reset + migre les DEUX bases
make fixtures / fixtures-reset

# Qualité (à lancer avant tout commit)
make qa                      # cs + stan + test (suite complète)
make cs                      # php-cs-fixer (corrige)
make stan                    # phpstan
make db-test-setup && make test   # tests PHPUnit

# Async
make worker                  # worker Messenger (emails async)

# Front
make watch                   # webpack --watch (dev)
make npm-build               # build prod

make create-admin email=x password=x
```

## Architecture

- **Double base de données** : `main` (données applicatives) + `log` (logs via `florimond/log-bundle`). Migrations séparées dans `migrations/main/` et `migrations/log/`, orchestrées par `florimond/multi-db-migrations-bundle`.
- **Backend DDD-inspired** : `Controller/` (thin HTTP + API REST JSON) → `Service/Manager/` (logique métier) → `Entity/` + `Repository/`. Voters + `PermissionService` (matrice `config/permissions.yaml`) pour l'autorisation.
- **Frontend** : 7 SPA React indépendantes (`admin`, `shop`, `blog`, `account`, `faq`, `contact`, `app`), chacune avec son entry `assets/*.jsx` et un shell Twig `templates/{section}/shell.html.twig`. Tailwind v4 + Shadcn (radix-ui) + `ma-librairie-ui`.
- **Paiement** : abstraction `src/Payment/` — `PaymentProviderInterface` + `PaymentProviderRegistry` + providers Stripe / PayPal / Mollie (redirect/hosted checkout).
- **Auth** : session-based (`LoginAuthenticator` custom), 2FA TOTP admin (`otphp`) + `TrustedDevice`, rate limiting sur endpoints auth.

## Pièges à connaître

- **Tous les prix sont en centimes EUR** (`1999` = 19,99 €). Ne jamais manipuler de floats pour l'argent.
- **Panier = session PHP** (`shop_cart`), pas de persistance DB.
- **Deux DB** : une migration touchant les logs va dans `migrations/log/`, le reste dans `migrations/main/`.
- **Toute migration doit étendre `CustomMigration`** (bundle `multi-db-migrations`) et déclarer `protected ?string $targetDatabase = 'main';` (ou `'log'`). Les deux namespaces sont visibles par les deux connexions : sans ce garde-fou, une migration s'exécute aussi sur l'autre base. `make db-main-migration` / `db-log-migration` s'en chargent (ils passent par `florimond:migrations:diff`, qui patche le fichier généré) — mais **ne jamais générer via `make:migration` directement**, qui produit un `AbstractMigration` nu.
- **`auto_mapping` doit rester à `false`** sur l'entity manager `default` : activé, il ramasse les entités du `LogBundle` (qui vivent dans la base log) et les migrations main proposent alors de créer les tables de logs dans la base principale. Toute entité de bundle destinée à la base main se déclare explicitement dans `config/packages/doctrine.yaml`.
- **Config dynamique** : `AppSetting` (key-value) exposé aux templates via `app_setting('key')` ; `ShopGuardSubscriber` bloque `/boutique/*` si `shop.enabled` ≠ `true`.
- **ActivityLogger** trace toutes les actions admin — le conserver lors d'ajouts de features admin.
- Prix, statuts de commande (`pending|confirmed|shipped|delivered|cancelled|refunded`) et de facture sont des machines à états — passer par `OrderManager::transition()`, pas de mutation directe.

## Conventions

- `declare(strict_types=1)` partout ; style `@Symfony` (php-cs-fixer).
- Types stricts et docblocks (PHPStan). Lancer `make stan` avant commit.
- Tests fonctionnels API : hériter de `tests/Functional/AbstractApiTestCase.php` (DAMA rollback entre tests).
- Messages de commit : préfixe emoji (✨ feat, 🔧 fix/chore, ♻️ refactor, ✅ tests) — voir `git log`.
