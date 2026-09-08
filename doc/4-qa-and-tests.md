# 🧪 4. Qualité de code & tests

Le skeleton impose une barre de qualité vérifiée par outillage : style, analyse
statique et tests. Ce guide décrit chaque outil, sa config, et **ce qu'il faut
lancer avant un commit**.

> ✅ **Avant tout commit :** `make qa` (style + PHPStan + tests). Détails ci-dessous.

---

## 1. La commande à retenir : `make qa`

```bash
make qa      # = cs → stan → test, dans cet ordre
```

- `cs` — php-cs-fixer **en mode correction** (il modifie tes fichiers)
- `stan` — PHPStan
- `test` — PHPUnit

> ⚠️ **Différence avec la CI :** `make qa` **corrige** le style (`cs` = `fix`),
> alors que la CI le vérifie en **dry-run** (lecture seule). Un `make qa` local vert
> garantit donc que la CI passera côté style — à condition de **commiter les
> corrections** que `cs` vient d'appliquer.

> ℹ️ `make qa` **n'inclut pas Rector** (voir §4). Rector se lance à la demande.

---

## 2. php-cs-fixer — style

Config : [`.php-cs-fixer.dist.php`](../.php-cs-fixer.dist.php).

- **Base `@Symfony`** + `setRiskyAllowed(true)`.
- Surcharges maison :
  - `declare_strict_types` (obligatoire partout) ;
  - `binary_operator_spaces` → **alignement vertical** de `=>` et `=`
    (`align_single_space_minimal`) ;
  - `single_line_empty_body` → constructeurs/corps vides `{}` sur une ligne.
- **Finder** : `src/` **et** `tests/`.
- Cache : `var/cache/.php-cs-fixer.cache`.

```bash
make cs     # corrige le style (applique les changements)
```

> Il n'existe **pas** de cible `make cs-dry`. Le dry-run n'est utilisé qu'en CI
> (`--dry-run --diff`). En local, lance `make cs` puis commite le résultat.

---

## 3. PHPStan — analyse statique

Config : [`phpstan.dist.neon`](../phpstan.dist.neon).

- **Niveau 6.**
- **Analyse `src/` uniquement** (pas `tests/`).
- **Baseline** : [`phpstan-baseline.neon`](../phpstan-baseline.neon) gèle **60
  erreurs** — essentiellement des types manquants dans des docblocks
  (`missingType.iterableValue`/`generics`, ~56) + quelques patterns Doctrine/
  défensifs. La baseline « photographie » la dette existante pour qu'elle ne bloque
  pas la CI, tout en interdisant d'en ajouter.
- Extensions `phpstan-symfony` et `phpstan-doctrine` chargées automatiquement
  (via `phpstan/extension-installer`).

```bash
make stan
```

> **Réduire la dette :** corrige des erreurs baselinées, puis **régénère** la
> baseline (`phpstan analyse --generate-baseline`) pour que le compteur descende.
> N'ajoute jamais d'`ignoreErrors` global pour masquer une vraie erreur.

---

## 4. Rector — modernisation (optionnel, hors `qa`)

Config : [`rector.php`](../rector.php).

- Cible `src/` et `tests/`.
- Sets : PHP 8.4 (`withPhpSets` + `UP_TO_PHP_84`), `deadCode`, `codeQuality`,
  `typeDeclarations`, suppression des imports inutilisés.

```bash
make rector       # dry-run (prévisualise)
make rector-fix   # applique
```

> ⚠️ Rector **n'est ni dans `make qa` ni dans la CI** : c'est un outil à passer
> ponctuellement, en relisant son diff (il peut être intrusif). Ne pas l'enchaîner
> aveuglément avant un commit.

---

## 5. PHPUnit — tests

Config : [`phpunit.dist.xml`](../phpunit.dist.xml) (PHPUnit 10).

- **Bootstrap** : `tests/bootstrap.php` (autoload + `Dotenv::bootEnv`).
- **Rigueur maximale** : `failOnDeprecation`, `failOnNotice`, `failOnWarning` tous
  à `true` — une dépréciation fait **échouer** la suite.
- `APP_ENV=test`.
- **DAMA** enregistré comme extension (voir §6).

### Structure `tests/`

- `tests/Functional/` — ~23 fichiers, organisés par domaine d'API
  (`Api/Account`, `Api/Admin`, `Api/Auth`, `Api/Shop`, `Api/Webhook`).
- `tests/Unit/Service/` — tests unitaires (`RateLimiterServiceTest`,
  `TrustedDeviceServiceTest`).
- **~24 fichiers de test, ~230 tests** au total.

### `AbstractApiTestCase` — le socle des tests d'API

`tests/Functional/AbstractApiTestCase.php` (hérite de `WebTestCase`). **Tout test
fonctionnel d'API en hérite.** Il fournit :

- **Requêtes JSON** : `postJson()`, `getJson()` (décode en array), `putJson()`,
  `patchJson()`.
- **Auth** : `createUser()`, `createAdmin()` (ROLE_ADMIN), `loginAs()`
  (`client->loginUser()`), `refreshUser()`.
- **Fabriques d'entités** (persist + flush) : `createCustomer`, `createProduct`,
  `createProductCategory`, `createOrder`, `createOrderWithProduct`, `createFaqItem`,
  `createStaticPage`, `createSupportTicket`, `createProductReview`,
  `createPasswordResetToken`…

Il **ne fait aucun rollback manuel** : c'est DAMA qui s'en charge (§6).

```bash
make test             # lance PHPUnit
make db-test-setup    # (re)construit les bases de test — après un changement de schéma
```

`make install` appelle déjà `db-test-setup` : sur un projet fraîchement installé,
`make test` fonctionne directement.

### Quelles bases utilisent les tests ?

`app_main_test` et `app_log_test` — les bases de développement suffixées par
Doctrine (`when@test` dans `config/packages/doctrine.yaml`).

Deux pièges, corrigés une fois pour toutes dans `.env` :

1. **Symfony ignore `.env.local` quand `APP_ENV=test`**, pour que les tests ne
   dépendent pas de la machine. Le `DATABASE_URL` de l'environnement de test vient
   donc de `.env`, pas de celui que `setup.sh` a écrit dans `.env.local` : les deux
   doivent nommer la même base, sinon les tests visent une base que personne ne crée.
2. **L'hôte doit être `${PROJECT_NAME}-db`, jamais l'alias `database`.** Tous les
   projets partagent le réseau `gateway`, où plusieurs conteneurs répondent à
   `database` : le DNS Docker en tire un au hasard, et `db-test-setup` peut créer
   une base sur un serveur puis en chercher une autre ailleurs.

---

## 6. DAMA DoctrineTestBundle — isolation des tests

- Activé **en env test uniquement** (`config/bundles.php`), sans fichier de config
  → **configuration par défaut**.
- Effet : **chaque test est enveloppé dans une transaction annulée** à la fin.
  Les données créées par un test (fabriques d'`AbstractApiTestCase`) n'existent que
  le temps du test, et n'ont pas besoin d'être nettoyées à la main. C'est ce qui
  rend la suite rapide et déterministe.

---

## 7. ⚠️ Le piège : base de test ≠ migrations

`make db-test-setup` construit les bases de test **depuis le schéma Doctrine**
(`doctrine:schema:create --em=default`), **pas** en rejouant les migrations. Idem
en CI.

> **Conséquence :** une suite de tests 100 % verte ne prouve PAS qu'une migration
> a été appliquée sur ta base de dev, ni qu'elle est correcte — la base de test est
> reconstruite depuis les entités, en court-circuitant les migrations.

C'est exactement comme ça qu'un bug de désynchronisation est passé inaperçu.
**Toujours valider une migration sur la base de dev + dans le navigateur**, pas
seulement via `make test`. Détails dans le
[guide 5 §4](./5-multi-database-and-migrations.md).

---

## 8. CI — GitHub Actions

Fichier : [`.github/workflows/ci.yml`](../.github/workflows/ci.yml).

- **Déclencheurs** : push et pull request sur `main` et `dev` (pas sur les branches
  feature).
- **Un seul job `qa`**, PHP **8.4** (pas de matrice), service **MariaDB 10.11**.
- Étapes, dans l'ordre :
  1. checkout + setup PHP + auth Composer + cache ;
  2. `composer install` ;
  3. **php-cs-fixer en dry-run** (`--dry-run --diff`) ;
  4. **PHPStan** ;
  5. prépare les 2 bases de test (`database:create` default + `schema:create`,
     `database:create` log) ;
  6. **PHPUnit**.
- La CI **n'utilise pas les cibles `make`** (commandes directes) et **ne lance pas
  Rector**.

### 🔑 Le secret `COMPOSER_GITHUB_TOKEN`

Les bundles `florimond/*` (LogBundle, MultiDbMigrationsBundle, CoreBundle) sont des
dépôts **GitHub privés**. La CI a besoin d'un token pour les installer via Composer.

- Le secret GitHub Actions **doit s'appeler `COMPOSER_GITHUB_TOKEN`** — **pas**
  `GITHUB_*` (préfixe réservé par GitHub Actions, interdit pour un secret custom).
- Sa valeur = un PAT fine-grained avec accès **lecture** aux 3 dépôts `florimond/*`
  (la même valeur que le `GITHUB_COMPOSER_TOKEN` de ton `.env.docker.local` local).

> Sans ce secret, la CI échoue dès `composer install`.

---

## 9. Écarts & points d'attention

| Point | Réalité |
|-------|---------|
| `make cs` | **corrige** (pas de dry-run local) ; le dry-run est en CI |
| `make qa` vs CI | `qa` corrige le style, la CI le vérifie en lecture seule |
| PHPStan | analyse `src/` seulement, pas `tests/` |
| Rector | absent de `qa` **et** de la CI — à lancer à la main |
| Base `log` en CI | créée mais **sans** `schema:create` |
| Couverture | providers de paiement, plusieurs Managers et `InvoiceService` restent peu/pas testés |
