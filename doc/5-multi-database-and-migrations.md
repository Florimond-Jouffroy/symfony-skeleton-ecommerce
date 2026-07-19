# 🗄️ 5. Double base de données & migrations

Ce guide explique **le mécanisme le plus subtil du skeleton** : deux bases de
données pilotées par un seul projet Symfony, et la convention de migrations qui
va avec. Lis-le en entier avant de générer ta première migration — c'est là que
se cache le piège le plus coûteux du projet.

---

## 1. Vue d'ensemble

Le projet parle à **deux bases MySQL distinctes** :

| Base | Connexion / EM Doctrine | Contenu | Migrations |
|------|-------------------------|---------|------------|
| `app_main` | `default` | Toutes les données applicatives (produits, commandes, users, retours…) | `migrations/main/` |
| `app_log`  | `log`     | Les journaux techniques (logs applicatifs, HTTP, mail, auth, erreurs) | `migrations/log/` |

Les deux connexions et les deux entity managers sont déclarés dans
[`config/packages/doctrine.yaml`](../config/packages/doctrine.yaml). Les URLs
viennent de `.env` : `DATABASE_URL` (main) et `LOG_DATABASE_URL` (log).

**Pourquoi séparer ?** Les logs ont un volume, un cycle de vie (purge, rétention)
et des contraintes de perf très différents des données métier. Les isoler dans
leur propre base évite qu'ils polluent le schéma applicatif, permet de les purger
ou de les archiver sans toucher au reste, et rend possible de les héberger
ailleurs plus tard sans migration lourde.

Trois bundles maison orchestrent tout ça :

- **`florimond/log-bundle`** — fournit les entités de log (`ApplicationLog`,
  `HttpLog`, `MailLog`, `AuthLog`, `ErrorLog`) et les listeners qui les
  remplissent. Ces entités sont mappées sur l'EM `log`.
- **`florimond/core-bundle`** — fournit des entités transverses (ex.
  `SystemConfiguration`), mappées sur l'EM `main`.
- **`florimond/multi-db-migrations-bundle`** — le chef d'orchestre des
  migrations multi-bases. C'est le sujet de la section 3.

---

## 2. Le cloisonnement des entités (à ne jamais casser)

### Le principe

Chaque entity manager ne doit voir **que** les entités de sa base :

- `default` → entités de `src/Entity` (App) + `Florimond\CoreBundle\Entity`
- `log` → `Florimond\LogBundle\Entity`

C'est déclaré **explicitement** dans `doctrine.yaml`, mapping par mapping.

### Le piège : `auto_mapping`

> ⚠️ **`auto_mapping` doit rester à `false` sur l'entity manager `default`.**

Avec `auto_mapping: true`, l'EM principal ne se contente pas de mapper `src/Entity` :
il ramasse **toutes** les entités du projet, y compris celles du `LogBundle`.
Résultat, `default` « croit » que les tables de logs lui appartiennent, et un
`doctrine:migrations:diff` sur la base main propose de **créer les 5 tables de
logs dans `app_main`**. Si cette migration est générée puis appliquée sans être
relue, on se retrouve avec des tables `error_logs`, `http_logs`, etc. dupliquées
dans la base principale.

La bonne configuration (déjà en place) :

```yaml
# config/packages/doctrine.yaml
entity_managers:
    default:
        connection: default
        auto_mapping: false          # ← indispensable
        mappings:
            App:                     # tes entités
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
            FlorimondCoreBundle:     # entités transverses du core-bundle
                is_bundle: false
                dir: '%kernel.project_dir%/vendor/florimond/core-bundle/src/Entity'
                prefix: 'Florimond\CoreBundle\Entity'
```

### Convention pour mapper l'entité d'un bundle

Pour rattacher les entités d'un bundle vendor à un EM précis, ce projet utilise
**toujours** le pattern `is_bundle: false` + `dir` pointant vers le chemin réel
dans `vendor/`. C'est le cas pour le LogBundle (mappé sur `log` dans
[`config/packages/florimond_log.yaml`](../config/packages/florimond_log.yaml)) et
pour le CoreBundle (mappé sur `default`). N'utilise pas `is_bundle: true` : ces
bundles suivent la structure moderne `src/Entity`, que le résolveur de bundle
Doctrine ne retrouve pas.

**Vérifier le cloisonnement à tout moment :**

```bash
make sf cmd="doctrine:mapping:info --em=default"   # doit lister App + CoreBundle uniquement
make sf cmd="doctrine:mapping:info --em=log"       # doit lister les 5 entités de log uniquement
make sf cmd="doctrine:schema:validate --em=default"
make sf cmd="doctrine:schema:validate --em=log"
```

---

## 3. Les migrations multi-bases

### Le problème que le bundle résout

Doctrine Migrations a une limite native : **les deux namespaces de migrations
(`DoctrineMigrations\Main` et `DoctrineMigrations\Log`) sont visibles par les
deux connexions**. Quand tu lances une migration sur la connexion `log`, Doctrine
voit *aussi* les migrations `Main` et voudrait les jouer — sur la mauvaise base.

`florimond/multi-db-migrations-bundle` résout ça avec deux briques.

#### a) `CustomMigration` + `$targetDatabase`

Toute migration doit étendre `CustomMigration` (et non `AbstractMigration`) et
déclarer sa base cible :

```php
final class Version20260718220905 extends CustomMigration
{
    protected ?string $targetDatabase = 'main';   // ou 'log'

    public function up(Schema $schema): void { /* ... */ }
}
```

Le `preUp()` / `preDown()` de `CustomMigration` compare la connexion courante à
la base cible (via `ConnectionMatcherService`, qui parse l'URL de la base) et fait
un `skipIf()` si elles ne correspondent pas. Une migration `Main` lancée sur la
connexion `log` **se saute donc toute seule**, proprement.

> ⚠️ **Une migration qui étend `AbstractMigration` n'a aucun garde-fou** : elle
> s'exécute sur les deux bases. C'est l'autre grand piège du projet.

#### b) Des commandes dédiées, scopées par « set »

La config du bundle
([`config/packages/florimond_multi_db_migrations.yaml`](../config/packages/florimond_multi_db_migrations.yaml))
définit deux **sets**, `main` et `log`, chacun avec son EM, son namespace et son
dossier. Le bundle expose alors :

```bash
florimond:migrations:diff  <set>   # génère une migration ET la patche en CustomMigration
florimond:migrations:migrate <set> # applique, en scopant le bon namespace/EM
```

`florimond:migrations:diff` appelle `doctrine:migrations:diff` en interne, puis
son `MigrationFixer` réécrit automatiquement le fichier généré : remplace
`AbstractMigration` par `CustomMigration` et injecte le bon `$targetDatabase`.
**Tu n'as donc plus jamais à ajouter le garde-fou à la main.**

### Les cibles `make`

Le Makefile enveloppe ces commandes — **utilise toujours `make`, jamais
`make:migration` directement** (qui produit un `AbstractMigration` nu, sans
garde-fou) :

```bash
make db-main-migration   # génère une migration main (déjà patchée)
make db-main-migrate     # applique les migrations main
make db-log-migration    # génère une migration log (déjà patchée)
make db-log-migrate       # applique les migrations log

make db-setup            # reset + re-migre les DEUX bases (destructif)
```

### Workflow type pour une nouvelle feature

1. Écris ou modifie ton entité dans `src/Entity`.
2. `make db-main-migration` — relis le fichier généré : il doit contenir
   **uniquement** ta table/colonne, jamais de table de log. Si tu vois du
   `error_logs`/`http_logs`, c'est que le cloisonnement de la section 2 est cassé.
3. `make db-main-migrate`.
4. `make sf cmd="doctrine:schema:validate --em=default"` → doit dire *in sync*.
5. **Vérifie la page concernée dans le navigateur.** Un test vert ne suffit pas
   (voir section 4).

---

## 4. Le piège des tests (important)

`make db-test-setup` construit la base de test **depuis le schéma Doctrine**
(`doctrine:schema:create`), **pas depuis les migrations**. Conséquence directe :

> Une suite de tests 100 % verte ne prouve PAS qu'une migration a été appliquée
> sur ta base de dev, ni même qu'elle est correcte.

C'est exactement comme ça qu'un bug de désynchronisation est passé inaperçu :
une colonne existait en base de test (créée depuis le schéma) alors que sa
migration n'avait jamais tourné sur la base de dev. **Toujours valider une
migration sur la base de dev + dans le navigateur**, pas seulement via les tests.

---

## 5. Diagnostiquer une désynchronisation

Si `make db-main-migrate` plante ou si un `diff` propose des changements
inattendus :

```bash
# État de chaque base : quelles migrations sont marquées jouées
make sf cmd="doctrine:migrations:list --em=default"
make sf cmd="doctrine:migrations:list --em=log"

# Le schéma correspond-il aux entités ?
make sf cmd="doctrine:schema:validate --em=default"

# Quelles tables existent réellement, et dans quelle base ?
make sf cmd="dbal:run-sql --connection=default \
  \"SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES \
    WHERE TABLE_SCHEMA IN ('app_main','app_log') ORDER BY 1,2\""
```

**Marquer une migration comme jouée sans toucher au schéma** (utile si une colonne
existe déjà en base mais que sa migration est marquée « non jouée ») :

```bash
make sf cmd="doctrine:migrations:version \
  'DoctrineMigrations\Main\VersionXXXX' --add --no-interaction --em=default"
```

À ne faire **qu'après** avoir vérifié que le `up()` de la migration correspond
bien à l'état réel de la base.

---

## 6. Checklist « je touche à la base »

- [ ] `auto_mapping` reste à `false` sur `default`.
- [ ] Toute nouvelle entité de bundle est mappée explicitement sur le bon EM.
- [ ] Je génère mes migrations avec `make db-*-migration`, jamais `make:migration`.
- [ ] Chaque migration étend `CustomMigration` avec le bon `$targetDatabase`.
- [ ] Je relis le fichier généré : pas de table étrangère à la base ciblée.
- [ ] `doctrine:schema:validate` répond *in sync* sur les deux EM.
- [ ] J'ai vérifié la feature dans le navigateur, pas seulement via les tests.
