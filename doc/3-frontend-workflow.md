# 🎨 3. Workflow Frontend (React 19 / Tailwind v4 / Shadcn)

Le front de ce skeleton n'est **pas** une seule application React : c'est un
ensemble de **SPA indépendantes**, une par section du site, chacune montée dans
un shell Twig. Ce guide explique ce découpage, la chaîne de build Webpack Encore,
la stack Tailwind v4 / Shadcn, et le pattern d'intégration Symfony ↔ React.

---

## 1. Le découpage : des SPA par section

Il y a **7 entrées Webpack** (`assets/*.jsx`), dont **6 sont de vraies SPA React**
et **1 (`app`) est un bootstrap Stimulus / Symfony UX-React** — pas une SPA.

| Section | Entrée | Composant racine | Shell Twig | `id` racine |
|---------|--------|------------------|------------|-------------|
| admin | `assets/admin.jsx` | `assets/react/admin/AdminApp.jsx` | `templates/admin/shell.html.twig` | `#admin-root` |
| shop | `assets/shop.jsx` | `assets/react/shop/ShopApp.jsx` | `templates/shop/shell.html.twig` | `#shop-root` |
| blog | `assets/blog.jsx` | `assets/react/blog/BlogApp.jsx` | `templates/blog/shell.html.twig` | `#blog-root` |
| account | `assets/account.jsx` | `assets/react/account/AccountApp.jsx` | `templates/account/shell.html.twig` | `#account-root` |
| faq | `assets/faq.jsx` | `assets/react/faq/FaqApp.jsx` | `templates/faq/shell.html.twig` | `#faq-root` |
| contact | `assets/contact.jsx` | `assets/react/contact/ContactApp.jsx` | `templates/contact/shell.html.twig` | `#contact-root` |
| **app** | `assets/app.jsx` | _(aucun — voir §5)_ | _(aucun)_ | — |

**Pourquoi ce découpage ?** Chaque section est une app autonome : son bundle JS,
son routing client, son état. Le back-office (`admin`) ne charge jamais le code de
la boutique, et inversement. C'est plus lourd en fichiers mais isole parfaitement
les domaines et garde chaque bundle petit.

Chaque SPA vit sous `assets/react/{section}/` avec la même organisation :
`pages/`, et selon les besoins `components/`, `layouts/`, `context/`. Exemple, la
boutique : `context/CartContext`, `components/CartDrawer`, `pages/` (catalogue,
produit, panier, checkout).

### Montage React (identique pour les 6 SPA)

`assets/shop.jsx` :

```jsx
import { createRoot } from 'react-dom/client';
import ShopApp from './react/shop/ShopApp';

const container = document.getElementById('shop-root');
if (container) {
    createRoot(container).render(<ShopApp />);
}
```

- API React 19 (`react-dom/client` → `createRoot`).
- **Montage conditionnel** (`if (container)`) : le bundle peut être chargé sur une
  page sans son div racine sans planter.
- **Routing client** avec `react-router-dom` v7, `basename` = préfixe d'URL Symfony.
  Ex. `ShopApp.jsx` : `<BrowserRouter basename="/boutique">`. Le routing serveur
  Symfony amène sur le shell, puis react-router prend la main côté client.

---

## 2. Build : Webpack Encore

Tout est dans [`webpack.config.js`](../webpack.config.js).

- **Sortie** : `public/build/`, public path `/build`.
- **7 `addEntry()`** : app, admin, shop, blog, account, faq, contact — un par
  fichier `assets/*.jsx`. Chaque entrée produit son JS **et** son CSS (chaque
  `.jsx` importe `./styles/app.css`).
- `splitEntryChunks()` + `enableSingleRuntimeChunk()` → un `runtime.js` partagé et
  des chunks vendor mutualisés.
- `enableReactPreset()` (JSX) + Babel `preset-env` (`useBuiltIns: 'usage'`,
  corejs 3).
- `enablePostCssLoader()` → branche `postcss.config.js` (Tailwind v4).
- `enableStimulusBridge('./assets/controllers.json')` → pour le canal UX-React (§5).
- **Alias** `@` → `assets/` (cohérent avec `components.json`).
- **Versioning** (noms de fichiers hashés) et désactivation des source maps
  **uniquement en production**.
- `configureWatchOptions` ignore `public/build` — sans quoi le watch boucle à
  l'infini sous Docker/WSL2.

### Commandes (via `make`)

```bash
make watch        # encore dev --watch (développement, rebuild à chaque save)
make npm-build    # encore production --progress (build optimisé + versioning)
```

Scripts npm sous-jacents (`package.json`) : `dev`, `watch` (`encore dev --watch`),
`build` (`encore production`).

> ⚠️ En dev, sans `make watch` actif, tes modifs `.jsx`/CSS ne sont **pas**
> recompilées : la page sert l'ancien bundle.

---

## 3. Styles : Tailwind v4 + Shadcn

- **Tailwind v4 sans `tailwind.config.js`** : la configuration se fait en CSS.
  `postcss.config.js` n'a qu'un plugin, `@tailwindcss/postcss`.
- **Styles globaux** : [`assets/styles/app.css`](../assets/styles/app.css), importé
  par les 7 entrées. Son en-tête :
  ```css
  @import "tailwindcss";
  @import "tw-animate-css";
  @import "shadcn/tailwind.css";
  @import "@fontsource-variable/geist";
  ```
  Puis deux directives `@source` déclarent les fichiers scannés pour les classes
  utilisées : `../../templates/**/*.twig` et `../**/*.jsx`. Les blocs `@theme`
  mappent les variables Shadcn (couleurs en oklch, base color `neutral`, radius,
  police Geist).
- **Composants Shadcn / Radix** : `assets/components/ui/` (~21 composants :
  `button`, `dialog`, `dropdown-menu`, `sidebar`, `table`, `tabs`, `sheet`,
  `select`, `card`, `input`…). Le `cn()` habituel est dans `assets/lib/utils.js`,
  un hook `assets/hooks/use-mobile.js`, et des composants data-table
  (`assets/components/data-table.jsx`). Config Shadcn dans `components.json` :
  `tsx: false` (donc du JSX, pas TSX), `cssVariables: true`, style `radix-nova`,
  registry via le package `radix-ui`.

> ℹ️ **Deux points à connaître** (pièges hérités, pas des bugs) :
> - `ma-librairie-ui` (la lib UI maison, `github:Florimond-Jouffroy/ma-librairie-ui`)
>   est **installée mais importée nulle part** dans `assets/`. Dépendance présente,
>   non consommée aujourd'hui.
> - `autoprefixer` est en devDependency mais **absent de `postcss.config.js`**
>   (Tailwind v4 gère le prefixing lui-même).

---

## 4. Stack front (extrait de `package.json`)

| Domaine | Paquets |
|---------|---------|
| Cœur | `react` / `react-dom` **^19.2**, `react-router-dom` **^7** |
| Style | `tailwindcss` **^4.3**, `@tailwindcss/postcss`, `tw-animate-css` |
| UI | `radix-ui`, `shadcn`, `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-react` |
| Build | `@symfony/webpack-encore` **^6**, `webpack` 5, Babel (`preset-env`, `preset-react`) |
| UX Symfony | `@symfony/stimulus-bridge`, `@hotwired/stimulus`, `@symfony/ux-react` (`file:vendor/symfony/ux-react/assets`) |
| Métier | `@stripe/react-stripe-js`, `@paypal/react-paypal-js`, `@tanstack/react-table`, `recharts`, `@editorjs/*` (éditeur blog), `qrcode` |

---

## 5. Intégration Symfony ↔ React

Deux canaux coexistent.

### Canal principal : SPA + data-attributes + API JSON

Le pattern, de bout en bout :

1. **Twig rend le squelette** et injecte l'état initial + la table des routes en
   JSON dans des `data-*`. Ex. `templates/shop/shell.html.twig` :
   ```twig
   {{ encore_entry_link_tags('shop') }}
   {{ encore_entry_script_tags('shop') }}
   <div id="shop-root"
        data-urls="{{ urls|json_encode }}"
        data-is-connected="{{ isConnected ? 'true' : 'false' }}"></div>
   ```
   Le contrôleur passe `urls` (map de routes), `isConnected`, etc. Le shell admin
   est le plus riche : `data-user-email`, `data-permissions` (json), `data-urls`,
   `data-app-name`.

2. **L'entry `.jsx` monte le root** sur le div (§1).

3. **Le composant `*App` lit les data-attributes** au chargement :
   ```jsx
   const root        = document.getElementById('shop-root');
   const urls        = JSON.parse(root?.dataset.urls ?? '{}');
   const isConnected = root?.dataset.isConnected === 'true';
   ```
   Ces valeurs descendent en props (`<CartProvider urls={urls}>`, etc.). **Pas de
   `window` global** : tout passe par les `data-*`.

4. **Les pages appellent l'API JSON** via le wrapper maison
   `assets/react/utils/api.js` : `api.get/post/put/patch/delete`, JSON auto,
   en-têtes `X-Requested-With` + `Accept: application/json`, classe `ApiError`
   (status + body), helpers `getErrorMessage` (messages FR par code HTTP) et
   `extractValidationErrors` (gère tableau brut, RFC 7807 `violations`, objet
   clé→message). Les URLs viennent des templates passés en `data-urls`, résolus par
   `assets/react/utils/url.js` (`resolveUrl`, remplacement de placeholders
   `__ID__`).

> 🔗 Les endpoints appelés sont ceux décrits dans le
> [guide backend](./2-backend-architecture.md) (`src/Controller/Api/**`) —
> l'autorisation y est gérée par les Voters.

### Canal secondaire : Symfony UX-React

`assets/app.jsx` ne monte aucune SPA. Il :
- importe les styles globaux + le bootstrap Stimulus,
- enregistre les composants de `assets/react/controllers/` (`LoginForm.jsx`,
  `RegisterForm.jsx`, `ForgotPasswordForm.jsx`…) via
  `registerReactControllerComponents`.

Ces composants sont montés **directement depuis Twig** avec `{{ react_component() }}`
(bridge `@symfony/ux-react`, `controllers.json` en `fetch: eager`), sans SPA. C'est
le bon canal pour un composant React isolé sur une page Twig classique (formulaires
d'auth notamment).

---

## 6. À retenir

- **Une SPA par section**, montée sur un `#{section}-root`, routing client avec
  `basename` = préfixe d'URL Symfony.
- **Données initiales via `data-*`**, jamais de `window` global ; le reste via
  `api.*` sur `/api/**`.
- **`make watch` obligatoire en dev** pour recompiler.
- **Tailwind v4 se configure en CSS** (`assets/styles/app.css`), pas de
  `tailwind.config.js`.
- UI = **Shadcn/Radix** dans `assets/components/ui/`, JSX (pas TSX).
