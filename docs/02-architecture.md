# 02 — Architecture

## 1. Stack

| Couche | Technologie | Version installée |
|---|---|---|
| Serveur | PHP | 8.5.12 en production |
| Framework | Laravel | 12.68.0 |
| Authentification web | Laravel Breeze | 2.4.2 |
| API | Laravel Sanctum | 4.3.3 |
| Rôles / permissions | Spatie Permission | 6.25.0 |
| Médias | Spatie MediaLibrary | (voir `composer.lock`) |
| QR code | Simple QrCode / bacon-qr-code | 4.2.0 |
| JWT | firebase/php-jwt | 6.11.1 |
| Temps réel | Laravel Reverb | 1.11.1 |
| Recherche | Laravel Scout | 11.6.1 |
| HTTP client | Guzzle | 7.15.5 |
| Tests | PHPUnit | 12.5.34 |
| Formatage | Laravel Pint | 1.30.5 |
| Build | Vite | 6.x |
| CSS | Tailwind CSS | 3.4 |
| JS réactif | Alpine.js | 3.x |
| Éditeur | Tiptap | 3.x |
| Calendrier | FullCalendar | 6.x |
| Scanner QR | html5-qrcode | 2.3.8 |

## 2. Structure des dossiers

```text
conducteurApp/
├── app/
│   ├── Console/            # commandes Artisan
│   ├── Enums/              # enums métier (types, statuts, rôles)
│   ├── Events/             # événements broadcast (ConducteurChanged, chat…)
│   ├── Http/
│   │   ├── Controllers/    # contrôleurs web
│   │   ├── Middleware/     # SecurityHeaders, EnsurePasswordIsChanged, …
│   │   └── Requests/       # FormRequests (validation + authorize)
│   ├── Jobs/               # tâches asynchrones (veille, scrape…)
│   ├── Models/             # Eloquent (Emission, Sequence, Sondage…)
│   ├── Policies/           # autorisations par modèle
│   ├── Providers/          # AppServiceProvider, etc.
│   └── Services/           # logique métier réutilisable
├── bootstrap/app.php       # configuration Laravel 12 (routing, middleware)
├── config/                 # configuration (role_access, workspaces, tvplusos…)
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   ├── css/, js/           # sources Vite
│   └── views/              # Blade (layouts, modules, partials, errors)
├── routes/
│   ├── web.php             # point d'entrée, require des modules
│   ├── api.php             # API v1 (Sanctum)
│   ├── auth.php            # login, inscription, reset
│   └── module_*.php        # modules thématiques
├── storage/                # logs, sessions, cache, fichiers MediaLibrary
└── tests/                  # Feature / Unit
```

## 3. Amorçage (bootstrap)

`bootstrap/app.php` configure :

- les routes `web`, `api`, `console` et le endpoint de santé `/up` ;
- le broadcasting via `routes/channels.php` (middleware `web`, `auth`) ;
- les alias de middleware : `role`, `permission`, `role_or_permission`, `password.changed` ;
- les middlewares web globaux ajoutés : `EnsurePasswordIsChanged`, `SecurityHeaders`, `LogAuthenticatedMutations` ;
- la protection des champs `password` / `password_confirmation` contre le flash en session.

`routes/web.php` charge ensuite les fichiers `auth.php`, `module_invitation_publique.php`, `module_rh.php`, `module_autres.php`, `module_users.php`, `module_sondage.php` et `module_workspace.php`.

`app/Providers/AppServiceProvider.php` enregistre notamment :

- le limiteur de débit nommé `assistant` (10 requêtes/minute par utilisateur) ;
- les observateurs de modèles (comptes, conducteurs, séquences, publications) ;
- les view composers (workspace, en-tête) ;
- en production uniquement : `URL::forceScheme('https')` et cookies de session stricts.

## 4. Cycle d'une requête

```text
HTTP → .htaccess (HTTPS, domaine, blocage .env/.git/.xml…) → index.php
     → middleware web (session, CSRF, SecurityHeaders, password.changed…)
     → routes/module_*.php → FormRequest (validation + authorize)
     → Policy / Gate → Contrôleur → Service / Eloquent → Vue Blade ou JSON
     → SecurityHeaders → réponse
```

Les mutations authentifiées sont journalisées par `LogAuthenticatedMutations`.

## 5. Données et stockage

- **Base** : MySQL en production (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`).
- **Sessions** : table `sessions`, chiffrées (`SESSION_ENCRYPT=true`), cookie `Secure`, `HttpOnly`, `SameSite=Lax`.
- **Cache et verrous** : tables `cache`, `cache_locks` (utilisées aussi par le planificateur).
- **Fichiers** : `storage/app/public` via le disque `public` (portraits, QR codes, médias).
- **Logs** : `storage/logs/laravel.log`, `security-*.log`, `veille-*.log`, `cron.log`.

## 6. Front-end

- Les sources sont compilées par Vite vers `public/build/` (fichiers hashés).
- `app.css` / `app.js` servent le dashboard ; des bundles dédiés existent pour l'intro (`welcome-intro`), l'œil (`welcome-eye`) et d'autres pages.
- Les pages publiques (intro, login, inscription, invitations, sondage) utilisent des vues autonomes ou `layouts/guest`.
- Les interactions côté navigateur utilisent surtout Alpine.js (`x-data`, `x-show`, `x-model`) ; la recherche de profils et le sondage utilisent `fetch`.

## 7. Temps réel

- `routes/channels.php` définit les canaux (notamment `private-conducteur.{id}`).
- Les événements (`ConducteurChanged`, messages de chat) sont broadcast via Reverb.
- Certaines vues complètent le temps réel par un polling léger (chat, état du conducteur, résultats du sondage).

## 8. Configuration métier

- `config/workspaces.php` : rôles, types de tâches, statuts, plateformes digitales.
- `config/role_access.php` : matrice permissions ↔ rôles.
- `config/tvplusos.php` : secret QR, paramètres propres à TV+A OS.
- `config/logging.php` : canaux `stack`, `security`, `tvplus_*`, `veille`.

Les valeurs sensibles sont lues via `env()` **uniquement** dans les fichiers de configuration ; le code applicatif utilise `config()`.

## 9. Environnements

| Environnement | Usage |
|---|---|
| local | Développement, SQLite possible, `APP_DEBUG=true` |
| testing | PHPUnit, SQLite `:memory:` (voir `phpunit.xml`) |
| production | `tvplusaos.com`, `APP_DEBUG=false`, HTTPS forcé |

## 10. Services métier utiles

| Service | Rôle |
|---|---|
| `ConducteurCalculatorService` | Calcul des durées et horaires des séquences |
| `ConducteurEditionService` | Édition du conducteur |
| `EditorialWorkflowService` | Avancement des étapes éditoriales |
| `InvitationService`, `QrCodeService` | QR codes, jetons, invitations |
| `IntervenantLogistiqueService`, `InvitationCheckinService` | Logistique et check-in |
| `JournalisteAccountService`, `MatriculeEmployeService` | Comptes et matricules |
| `ScraperService`, `GabonVeilleAgent`, `VeilleAIService` | Veille automatisée |
| `SharedWorkspaceService` | Espace de travail partagé |
| `RoleAccessService`, `RoleDashboardService` | Matrix des rôles et tableaux de bord |
| `SecurityLogger` | Journal des événements de sécurité |
| `PortraitService`, `MediaValidation` | Portraits et validation de fichiers |
