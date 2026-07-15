 # 🎓 Guide de Développement Front-End : React, Tailwind CSS & Shadcn UI
 
 Bienvenue dans le guide de survie du développeur front-end sur ce Skeleton Symfony ! Ce document est conçu pour t'expliquer pas à pas comment créer tes interfaces visuelles, même si tu débutes complètement avec React, Tailwind et Shadcn.
 
 ---
 
 ## 🧱 1. La philosophie générale : Le jeu de Lego
 
 Développer avec ce combo ne demande pas d'écrire de longues lignes de CSS complexes. Tu vas fonctionner en assemblant des "briques" (appelées composants) et en leur donnant du style directement dans ton code.
 
 * **React** gère la structure et la logique (qu'est-ce qui se passe quand je clique ?).
 * **Shadcn UI** te fournit les briques de base prêtes à l'emploi (Boutons, Modales, Tableaux, Formulaires).
 * **Tailwind CSS** gère le look (les couleurs, les marges, les tailles) grâce à des classes magiques pré-calculées.
 
 ---
 
 ## 🛍️ 2. Étape 1 : Faire son marché sur Shadcn
 
 Quand tu as besoin d'un élément visuel pour ton site, ton premier réflexe doit être d'aller voir s'il existe déjà dans la bibliothèque de Shadcn.
 
 1. Rends-toi sur la documentation officielle : [ui.shadcn.com/docs/components](https://ui.shadcn.com/docs/components).
 2. Choisis le composant dont tu as besoin dans la barre latérale gauche (ex: *Badge*, *Card*, *Dialog*).
 3. Regarde son nom et lance la commande d'importation dans ton terminal principal :
    ```bash
    make cmd cmd="npx shadcn@latest add le-nom-du-composant"
    ```
 4. **Magie :** Le code source du composant est téléchargé directement dans ton dossier `assets/components/ui/`. Il t'appartient, tu peux l'ouvrir pour voir comment il est fait !
 
 ---
 
 ## 🏗️ 3. Étape 2 : Assembler les pièces en React
 
 Une fois tes composants importés, tu crées tes propres pages ou blocs en important ces briques.
 
 Voici l'anatomie d'un composant d'exemple (`assets/components/MonPremierBloc.jsx`) :
 
 ```jsx
 // 1. J'importe les briques de Shadcn dont j'ai besoin (Remarque l'alias @/ qui pointe vers le dossier assets)
 import { Button } from "@/components/ui/button"
 import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
 
 // 2. Je crée ma fonction React (Le nom doit TOUJOURS commencer par une MAJUSCULE)
 export default function MonPremierBloc() {
   return (
     // 3. J'écris ma structure en HTML/JSX
     <Card className="max-w-[350px]">
       <CardHeader>
         <CardTitle>Mon Premier Composant</CardTitle>
         <CardDescription>Propulsé par Shadcn et Tailwind v4</CardDescription>
       </CardHeader>
       <CardContent>
         <p className="text-sm text-slate-600">
           Je débute sur React, mais grâce à l'écosystème moderne, je code à la vitesse de l'éclair !
         </p>
       </CardContent>
       <CardFooter className="flex justify-end">
         <Button variant="default">C'est parti !</Button>
       </CardFooter>
     </Card>
   )
 }
 ```
 
 ---
 
 ## 🎨 4. Étape 3 : Styliser à la volée avec Tailwind CSS
 
 Au lieu d'ouvrir un fichier `.css` à part et de chercher des noms de classes (comme `.mon-bouton-bleu-arrondi`), tu écris tes styles directement dans la propriété `className="..."` de tes balises HTML.
 
 ### 💡 Le lexique de survie des classes Tailwind principales :
 
 * **Les Marges Interne (Padding) :** `p-4` (marge partout), `px-4` (gauche/droite), `py-2` (haut/bas).
 * **Les Marges Externes (Margin) :** `m-4`, `mx-auto` (centrer un bloc horizontalement), `mt-6` (marge au-dessus).
 * **Les Couleurs :** `bg-blue-500` (fond bleu), `text-slate-800` (texte gris foncé), `border-red-200` (bordure rouge claire).
 * **Le Texte :** `text-xl` (grand texte), `font-bold` (gras), `text-center` (centré).
 * **La Disposition (Flexbox) :** `flex` (active le mode boîte alignée), `flex-col` (aligné verticalement), `items-center` (centré verticalement), `justify-between` (espace max entre les éléments).
 * **Les Arrondis & Ombres :** `rounded-lg` (beaux angles arrondis), `shadow-md` (effet de relief avec une ombre).
 
 ---
 
 ## 🔄 5. Au quotidien : Ton flux de travail (Workflow)
 
 Pour travailler confortablement, tu auras généralement **deux fenêtres ou onglets de terminal** ouverts en même temps :
 
 1.  **Terminal 1 (Le Watcher en continu) :**
     Tu lances cette commande au tout début de ta session et tu la laisses tourner en arrière-plan :
     ```bash
     make watch
     ```
     *Pourquoi ?* Ce terminal écoute tes fichiers. Dès que tu modifies une classe Tailwind ou que tu touches à du React, il recompile tes assets en 0.1 seconde. Tu n'as qu'à rafraîchir ton navigateur pour voir le résultat.
 
 2.  **Terminal 2 (Les Commandes à la volée) :**
     Tu t'en sers pour faire tes actions Git, tes commandes Symfony, ou pour ajouter de nouveaux éléments Shadcn (ex: `make cmd cmd="npx shadcn@latest add ..."`).
 
 ---
 
 ## 🦎 6. L'approche "Caméléon" pour tes différents projets
 
 Vu que chaque projet aura son propre design unique, **tu ne touches jamais au code React de Shadcn** pour changer l'identité visuelle globale.
 Tout se passe dans ton fichier CSS principal (`assets/styles/app.css`), sous la directive `@theme`.
 
 Si ton projet B doit passer d'une ambiance "SaaS d'entreprise bleu" à une ambiance "Blog minimaliste vert foncé", tu as juste à te rendre sur [ui.shadcn.com/themes](https://ui.shadcn.com/themes), sélectionner tes nouvelles teintes, copier le code généré, et venir écraser tes variables CSS. Tous tes boutons et composants React s'adapteront tout seuls à la nouvelle charte graphique !
 
 Happy coding ! 🚀
