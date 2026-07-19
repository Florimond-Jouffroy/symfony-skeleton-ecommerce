# 🐘 2. Architecture Backend & DDD

Guide d'architecture backend pour un développeur qui reprend le projet. Objectif :
comprendre **où vit quoi**, **comment les couches communiquent** et **quelles
conventions ne jamais casser**. Stack : Symfony 8 / PHP 8.4, Doctrine ORM,
architecture **DDD-inspired** (pas de DDD tactique complet, mais une séparation
stricte des responsabilités).

> 🗄️ Le système à **deux bases de données** et sa convention de migrations font
> l'objet d'un guide dédié, à lire absolument :
> [5. Double base de données & migrations](./5-multi-database-and-migrations.md).

---

## 1. Les couches, en une image

```
Requête HTTP
   │
   ▼
┌──────────────────────────────────────────────┐
│ Controller/                                   │  Thin HTTP : parse, autorise,
│   Controller/Api/{Account,Admin,Blog,Shop,    │  délègue, sérialise. Pas de
│                   Webhook}/                    │  logique métier ici.
└───────────────┬──────────────────────────────┘
                │  denyAccessUnlessGranted(XxxVoter::ACTION)
                ▼
┌──────────────────────────────────────────────┐
│ Security/Voter/  →  Security/PermissionService │  Autorisation (section 3)
└───────────────┬──────────────────────────────┘
                ▼
┌──────────────────────────────────────────────┐
│ Service/Manager/   (logique métier)           │  Règles, effets de bord,
│ Service/           (services transverses)     │  transactions (section 2)
└───────────────┬──────────────────────────────┘
                ▼
┌──────────────────────────────────────────────┐
│ Entity/  +  Repository/                        │  Données + requêtes
└──────────────────────────────────────────────┘
```

**Règle d'or de la couche :** un contrôleur ne contient **pas** de logique métier.
Il autorise, appelle un Manager, et sérialise le résultat. Toute règle (validation
métier, transition d'état, effet de bord) vit dans un `Service/Manager/`.

### Répertoires sous `src/`

| Répertoire | Rôle |
|------------|------|
| `Controller/` | Contrôleurs HTML front (8) + `Controller/Api/` (REST JSON, découpé par domaine) |
| `Entity/` | 27 entités Doctrine |
| `Repository/` | Repositories Doctrine (requêtes) |
| `Service/Manager/` | 13 managers = logique métier par agrégat |
| `Service/` | Services transverses (mailers, logger, rate limiter, facturation…) |
| `Security/` + `Security/Voter/` | Authentification + 20 voters d'autorisation |
| `Payment/` + `Payment/Provider/` | Abstraction paiement (section 5) |
| `EventSubscriber/` | Garde boutique + mode maintenance (section 6) |
| `Twig/`, `Form/` (+ `Form/Handler`), `Dto/`, `Exception/`, `Command/`, `DataFixtures/` | Support |
| `Message/Command/`, `MessageHandler/Command/` | Emplacements pour messages async — **vides** aujourd'hui (voir section 7) |

Ordres de grandeur : **50 contrôleurs**, **27 entités**, **19 services** dont
**13 managers**.

---

## 2. La couche métier : les Managers

Chaque agrégat important a son manager dans `src/Service/Manager/` : `OrderManager`,
`ProductManager`, `StockManager`, `ReturnManager`, `UserManager`, `ArticleManager`,
`CategoryManager`, `ProductCategoryManager`, `MediaFileManager`,
`PasswordResetManager`, `SupportTicketManager`, etc. À côté, des services
transverses non liés à un agrégat : `ActivityLogger`, `InvoiceService`,
`RateLimiterService`, `TrustedDeviceService`, et les mailers (`OrderMailer`,
`AuthMailer`, `SupportMailer`).

### Exemple de chaîne complète

Changement de statut d'une commande côté admin :

```
POST /api/admin/commandes/{id}/transition
  → OrderController::transition()                 src/Controller/Api/Admin/OrderController.php:75
      denyAccessUnlessGranted(OrderVoter::EDIT)    ligne 77   (autorisation)
      $this->manager->transition($order, $status, $comment)   ligne 95  (délégation)
  → OrderManager::transition()                    src/Service/Manager/OrderManager.php:95
      - refuse si !$order->canTransitionTo($status)
      - écrit un OrderStatusHistory
      - effets de bord (restock, facture, mail) — voir §4
      - flush()
  → OrderRepository / EntityManager               (persistance)
```

Le contrôleur reste mince : il autorise, appelle, renvoie du JSON. Toute la règle
est dans le manager.

---

## 3. Autorisation : Voters + PermissionService + matrice YAML

L'autorisation est **centralisée dans un fichier de config**, pas éparpillée dans
le code. Trois pièces :

1. **`config/permissions.yaml`** (~267 lignes) — la matrice. Chaque permission
   liste les rôles qui l'obtiennent :
   ```yaml
   permissions:
       ORDER_VIEW:
           roles: [ ROLE_ADMIN ]
           description: "Voir les commandes"
       ORDER_EDIT:
           roles: [ ROLE_ADMIN ]
           description: "Modifier une commande (statut, note)"
   ```
2. **`src/Security/PermissionService.php`** — charge le YAML, et
   `hasPermission($permission, $userRoles)` calcule les rôles atteignables
   (via la hiérarchie de rôles Symfony) puis les croise avec les rôles autorisés
   de la permission. Un champ optionnel `conditions` est supporté mais non utilisé
   aujourd'hui.
3. **`src/Security/Voter/`** — **20 voters**, un par domaine (`OrderVoter`,
   `ProductVoter`, `UserVoter`…). Tous suivent le même patron : des constantes
   d'attribut (`ORDER_VIEW/EDIT/DELETE`), un `supports()`, et un
   `voteOnAttribute()` qui délègue à `PermissionService::hasPermission()`.

**Côté contrôleur**, ça donne une seule ligne lisible :

```php
$this->denyAccessUnlessGranted(OrderVoter::EDIT, $order);
```

Symfony route vers `OrderVoter` → `PermissionService` → matrice YAML. **Pour
ajouter/retirer un droit, on édite `permissions.yaml`, pas le code.**

---

## 4. Machines à états

### Commandes — machine à états stricte

Les statuts et les transitions autorisées sont définis **dans l'entité**
`src/Entity/Order.php`, pas dans le manager :

```
pending    → confirmed, cancelled
confirmed  → shipped, cancelled
shipped    → delivered, cancelled
delivered  → refunded
cancelled  → (terminal)
refunded   → (terminal)
```

- `Order::TRANSITIONS` = la table de transitions ; `Order::canTransitionTo()` la
  vérifie.
- `OrderManager::transition()` est le **seul point d'entrée** : il refuse une
  transition illégale, écrit un `OrderStatusHistory`, puis déclenche les effets
  de bord selon le statut cible :
  - `cancelled` → restock (`StockManager::restoreForOrder`)
  - `confirmed` → génération de facture (si trigger `on_confirm`)
  - `shipped` → email au client

> ⚠️ **Ne jamais muter `Order::$status` directement.** Passer par
> `OrderManager::transition()`, sinon on court-circuite les gardes et les effets
> de bord.

### Factures — pas de machine à états

`src/Entity/Invoice.php` définit 3 statuts (`pending`, `paid`, `cancelled`) **à
titre de libellés uniquement** : il n'y a ni table de transitions ni garde.
`InvoiceService` génère la facture (statut initial `pending`) et le PDF. Ne pas
supposer une machine à états ici.

---

## 5. Paiement : abstraction multi-provider

Tout est derrière une interface, dans `src/Payment/` :

- **`PaymentProviderInterface`** — le contrat : `createIntent(Order)`,
  `constructWebhookEvent(payload, signature)`, `extractOrderNumber`,
  `extractEventId`, `isPaymentSucceeded/Failed`, `isEnabled`, etc.
- **3 providers** : `Provider/{Stripe,PayPal,Mollie}PaymentProvider.php`. Chaque
  `isEnabled()` lit des `AppSetting` (ex. Stripe exige
  `payment.stripe.enabled == 'true'` **et** une `secret_key` non vide).
- **`PaymentProviderRegistry::getActive()`** retourne **le premier provider
  activé** (ou `null`). L'ordre = l'ordre de déclaration du `tagged_iterator
  app.payment_provider` dans `config/services.yaml`.

**Au checkout** (`CheckoutController`) : `getActive()` ; si `null`, la commande
passe directement en prête (paiement désactivé / manuel) ; sinon `createIntent()`
et on renvoie `provider` + `publicKey` au front.

### Idempotence des webhooks

Un paiement peut notifier plusieurs fois. Deux défenses :

1. **`ProcessedWebhookEvent`** — entité avec contrainte unique sur
   `(provider, event_id)`. `WebhookHandler::handle()` vérifie
   `isProcessed()` avant d'agir, puis `markProcessed()` après une action
   terminale, en avalant la `UniqueConstraintViolationException` en cas de
   livraisons concurrentes.
2. **La machine à états elle-même** : une seconde transition `pending → confirmed`
   est de toute façon refusée, même sans event id.

Contrôleurs : `Controller/Api/Webhook/{Stripe,PayPal,Mollie}WebhookController.php`
(vérifient la signature, renvoient 400 si invalide, délèguent à `WebhookHandler`).

---

## 6. Sécurité & authentification

- **`LoginAuthenticator`** (custom, session-based) : son `supports()` renvoie
  toujours `false` — il **ne s'auto-déclenche jamais**. On l'invoque
  explicitement via `Security::login($user, LoginAuthenticator::class)` depuis
  `AuthController`. Il sert aussi de `AuthenticationEntryPoint` : 401 JSON pour
  `/api/*` et XHR, redirection login sinon.
- **2FA TOTP admin** (lib `otphp`) : secret dans `User::$totpSecret`, activation
  via `TwoFactorController` (setup → secret en session + URI provisioning →
  vérification du code → stockage). Au login, si `isTotpEnabled()`, l'API renvoie
  `2fa_required` (sauf device de confiance).
- **`TrustedDevice`** : cookie `_td_2fa` HttpOnly + SameSite=Strict, token stocké
  haché SHA-256, durée pilotée par le setting `security.2fa.trusted_device_days`
  (défaut 30 j). Permet de sauter la 2FA sur un appareil connu.
- **`RateLimiterService`** (backend `cache.app`) : clés par `type + IP`, appliqué
  sur `login`, `2fa`, `register`, `reset` dans `AuthController`. Seuils et fenêtres
  lus depuis les settings.
- **`config/packages/security.yaml`** : hiérarchie `ROLE_ADMIN ⊃ ROLE_USER`,
  firewall `main` avec `custom_authenticators: [LoginAuthenticator]`,
  `access_control` sur `^/admin` et `^/api/admin` (ROLE_ADMIN), `^/mon-compte` et
  `^/api/compte` (ROLE_USER).

---

## 7. Asynchrone (Messenger)

- `config/packages/messenger.yaml` : transport `async`
  (`%env(MESSENGER_TRANSPORT_DSN)%`), `failure_transport: failed`, retry 3× avec
  backoff (delay 1000 ms, multiplier 2). En test : `in-memory://`.
- **Seul routage async aujourd'hui : `SendEmailMessage` → async.** Autrement dit,
  **seuls les emails partent en asynchrone** (via `make worker`).
- Les dossiers `src/Message/Command/` et `src/MessageHandler/Command/` sont
  **vides** (`.gitkeep`) : ce sont des emplacements prêts pour tes propres
  commandes async, pas du code existant.

> Pour que les emails partent réellement en dev, lancer le worker : `make worker`.

---

## 8. Configuration dynamique & garde-fous applicatifs

### `AppSetting` (clé-valeur en base)

Entité `src/Entity/AppSetting.php` : un couple `settingKey` / `value`. Accès via
`AppSettingRepository::getValue($key, $default)` / `setValue()`. C'est le magasin
de config runtime (activation boutique, taux de TVA, seuils de rate limit, clés
de paiement…).

> ℹ️ **Attention à une idée reçue :** il n'existe **pas** de fonction Twig
> `app_setting('key')`. Ce qui existe côté template :
> - `src/Twig/AppSettingExtension.php` expose deux **globals** Twig :
>   `shopEnabled` et `maintenanceMode` ;
> - le core-bundle expose une fonction `get_sys_config(key)` qui lit l'entité
>   `SystemConfiguration` (base main), distincte d'`AppSetting`.

### Les deux EventSubscribers applicatifs

- **`ShopGuardSubscriber`** (`kernel.request`, priorité 10) : bloque `/boutique`,
  `/boutique/*`, `/api/boutique/*` quand `shop.enabled != true` → 503 JSON pour
  l'API, template `shop/disabled.html.twig` sinon.
- **`MaintenanceSubscriber`** (`kernel.request`, priorité 5, après le firewall) :
  si `site.maintenance == true`, laisse passer admin/auth/assets et les
  ROLE_ADMIN, rend `maintenance.html.twig` (503) pour tout le reste.

Ce sont les **seuls** subscribers applicatifs ; le log-bundle branche les siens
séparément.

### `ActivityLogger` — traçabilité admin

`src/Service/ActivityLogger.php` : `log($action, $entityType, $entityId,
$entityLabel, $context)`. Crée une `ActivityLog`, renseigne l'auteur via le token
de sécurité (fallback `'système'`), `persist` + `flush` immédiat. Appelé dans les
contrôleurs admin (Order, Product, Review, Support) après chaque action sensible.

> ⚠️ **Convention à conserver :** quand tu ajoutes une action admin qui modifie
> des données, appelle `ActivityLogger::log(...)`. C'est la piste d'audit du
> back-office.

> À ne pas confondre avec le **log technique** (exceptions, HTTP, mail, auth), géré
> par `florimond/log-bundle` dans la base `app_log` — voir le
> [guide multi-bases](./5-multi-database-and-migrations.md).

---

## 9. Conventions backend à retenir

- `declare(strict_types=1)` partout ; style `@Symfony` (php-cs-fixer).
- Code de **domaine en anglais**, UI/templates en **français**.
- **Prix en centimes EUR** (`1999` = 19,99 €) — jamais de float pour l'argent.
- **Panier = session PHP** (`shop_cart`), pas de persistance DB.
- Autorisation → toujours via un **Voter** + `permissions.yaml`, jamais de test de
  rôle en dur dans un contrôleur.
- Transition de statut → toujours via le **manager**, jamais de mutation directe.
- Action admin → **`ActivityLogger`**.
- Avant tout commit : `make qa` (cs + stan + test). Voir le
  [guide QA & tests](./4-qa-and-tests.md).
