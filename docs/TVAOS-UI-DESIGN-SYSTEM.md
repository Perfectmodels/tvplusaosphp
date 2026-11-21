# TV+A OS — Référentiel UI/UX

Projet : Laravel 12 + Blade + Alpine.js + Tailwind CSS 3.
But : uniformiser les interfaces sans refondre les workflows, la navigation métier ou les permissions serveur.

## Charte et couleurs existantes

| Usage | Token Tailwind | Couleur |
|---|---|---|
| Navigation et action principale | primary | #001a41 |
| Action secondaire et focus | secondary | #0054cd |
| Texte principal | on-surface | #181c1e |
| Texte d'aide | on-surface-variant | #44474e |
| Bordures | outline-variant | #c5c6cf |
| Erreur et suppression | error | #ba1a1a |
| Confirmation | success | #007d44 |
| Attention | warning | #924a00 |

Conserver Inter, les icônes du projet et les logos fournis. Ne pas réintroduire la palette Breeze gris/indigo.

## Composants partagés

- x-primary-button, x-secondary-button, x-danger-button : actions au minimum 44 px, focus contrasté et désactivation lisible.
- x-text-input, x-input-label, x-input-error : champ accessible, indication obligatoire et erreurs annoncées.
- x-modal : rôle de dialogue, nom accessible, confinement Tab, fermeture Échap, retour au bouton déclencheur.
- x-dropdown / x-dropdown-link : menu au clavier, flèches, Home, End, Échap, état développé exposé.
- x-ui.empty-state : message clair sur l'absence de données, actions optionnelles si autorisées.
- x-ui.status-badge : variantes neutral, info, success, warning, danger.
- x-ui.table-shell : tableau à défilement horizontal avec région accessible nommée.
- CSS : ui-action, ui-action--primary/secondary/outline/danger, ui-field, ui-panel, ui-feedback, ui-table-frame.

## Règles d'implémentation

1. Une action principale par section ; pas de faux boutons.
2. Les boutons d'édition/suppression n'apparaissent que lorsque la permission serveur correspondante existe.
3. Sur smartphone, une cible interactive doit mesurer au moins 44 × 44 pixels.
4. Les champs ont un label, les erreurs sont explicites et les formulaires peuvent être repris après échec.
5. Un dialogue rend le focus au déclencheur et ne bloque pas le clavier.
6. Les tableaux ont en-tête, état vide, tri/filtre si pertinent, et accès mobile.
7. Les transitions non essentielles respectent la préférence de réduction du mouvement.
8. Le titre de la page et la navigation restent visibles sans créer de débordement horizontal.
9. Les espaces de production TV+A OS privilégient les données métier, les outils QR/chat/sondage venant ensuite.
10. Ne jamais remplacer les liens/actions existants par des contrôles décoratifs.

## Exemples Blade

~~~blade
<x-ui.empty-state title="Aucune édition" description="Choisissez une autre date.">
    <x-slot:actions>
        <a href="/conducteurs/emission/create" class="ui-action ui-action--primary">
            Nouveau conducteur
        </a>
    </x-slot:actions>
</x-ui.empty-state>

<x-ui.table-shell label="Conducteurs de la semaine">
    <table><thead>...</thead><tbody>...</tbody></table>
</x-ui.table-shell>

<x-ui.status-badge variant="success">Validé</x-ui.status-badge>
~~~

## Vérification avant livraison

- PHP : php artisan test --filter=UiDesignSystemTest.
- Frontend : npm ci, puis npm run build.
- Ecrans : 375 px, 768 px, 1280 px, 1920 px.
- Parcours clavier : Tab, Shift+Tab, Echap, Home, End, flèches.
- Scénarios : utilisateur sans permission, administrateur, présentateur et chef d'édition.
- Etats : attente, succès, erreur, hors connexion, vide, conflit et formulaire incomplet.
- Contrôle visuel : tester dans le navigateur avec une session authentifiée.

La fusion GitHub sur main et la mise en ligne FTPS sur LWS sont deux opérations distinctes. La première ne prouve pas la seconde.
