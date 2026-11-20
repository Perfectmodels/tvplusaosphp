# 08 — Routes et API

## 1. Organisation

`bootstrap/app.php` déclare :

- `routes/web.php` (middleware `web`) ;
- `routes/api.php` (middleware `api`, préfixe `/api`) ;
- `routes/console.php` ;
- `routes/channels.php` pour le broadcasting ;
- endpoint de santé `/up`.

`routes/web.php` charge les modules : `auth.php`, `module_invitation_publique.php`, `module_rh.php`, `module_autres.php`, `module_users.php`, `module_sondage.php`, `module_workspace.php`.

Recenser les routes :

```bash
php artisan route:list --except-vendor
php artisan route:list --name=sondage
```

Environ production : **221 routes** hors vendor (inventaire du 20/11/2026).

## 2. Répartition par préfixe

| Préfixe | Routes | Domaine |
|---|---:|---|
| `conducteurs` | 53 | Conducteurs, séquences, chef d'édition, présentateur |
| `veille` | 23 | Veille et remixes |
| `espace-metier` | 13 | Espace partagé |
| `intervenants` | 13 | Intervenants et QR |
| `emissions` | 11 | Émissions |
| `journalistes` | 11 | Annuaire interne |
| `parcours-intervenants` | 10 | Suivi logistique |
| `api` | 9 | API v1 et endpoints internes |
| `users` | 8 | Utilisateurs |
| `digital` | 7 | Publications numériques |
| `personnel` | 6 | Inscriptions |
| `scanner-qr` | 6 | Check-in |
| `sujets-editoriaux` | 6 | Sujets éditoriaux |
| `evenements-editoriaux` | 5 | Événements |
| `i` | 5 | Pages publiques (invitations, interactions) |
| `sondages` | 4 | Administration du sondage |
| `s` | 3 | Vote public |
| `dashboard`, `login`, `profile`… | ~40 | Divers |

## 3. Routes publiques

| Route | Nom | Description |
|---|---|---|
| `GET /` | — | Intro sonore (accueil) |
| `GET /login` | `login` | Connexion |
| `GET /personnel/inscription` | `personnel.inscription.create` | Demande d'accès |
| `GET /personnel/inscription/search` | `personnel.inscription.search` | Recherche de profil (throttle 20/min, données minimales) |
| `GET /i/{token}` | `invitations.show` | Fiche invitée |
| `GET /i/emission/{token}` | `emission-interactions.show` | Questions du public |
| `GET /s/{token}` | `sondage.show` | Page de vote |
| `GET /s/{token}/resultats` | `sondage.resultats` | Résultats JSON (no-store) |

Les pages publiques sont protégées par des throttles (30/min en lecture, 10/min sur le vote) et des en-têtes `noindex`.

## 4. Routes du sondage

```text
GET    /s/{token}                    sondage.show
POST   /s/{token}/vote               sondage.vote
GET    /s/{token}/resultats          sondage.resultats
GET    /emissions/{emission}/sondage/create   sondages.create
POST   /emissions/{emission}/sondage          sondages.store
GET    /sondages/{sondage}           sondages.show
POST   /sondages/{sondage}/ouvrir    sondages.ouvrir
POST   /sondages/{sondage}/clore     sondages.clore
GET    /sondages/{sondage}/qr.svg    sondages.qr
```

Détail : [09 — Sondage direct](09-sondage-direct.md).

## 5. API v1

Préfixe `/api/v1`, middleware `auth:sanctum` + `throttle:60,1`. Principales familles :

- intervenants (liste, recherche) ;
- veille (scrape d'URL, actions IA, promotion) ;
- opérations diverses liées au workspace.

Toute requête non authentifiée est refusée. En cas de besoin, générer un token Sanctum via le profil.

## 6. Endpoints internes

Routes protégées par un secret bearer (`DeploymentController::authorizeDeployment`) :

| Route | Usage |
|---|---|
| `/api/internal/readiness` | État de déploiement |
| `/api/internal/veille` | Déclenchement de la veille (à passer en POST + file d'attente selon le plan d'audit) |

Ne pas exposer ce secret dans le code ou la documentation.

## 7. Conventions de nommage

- Routes : segments en kebab-case (`emission-interactions.show`, `conducteurs.sequences.store`).
- Paramètres : `->where('token','[A-Za-z0-9]{48}')` ou `->whereNumber()`.
- Contrôleurs : `App\Http\Controllers\*Controller`, souvent `final`.
- Vues : dot-notation (`emission-interactions.show` → `resources/views/emission-interactions/show.blade.php`).
- FormRequests : `Store*Request`, `Update*Request`, avec `authorize()` et `rules()`.

## 8. Ajouter une route

1. Créer le contrôleur via `php artisan make:controller --no-interaction`.
2. Créer la FormRequest si la validation est substantielle.
3. Déclarer la route dans le bon `routes/module_*.php`.
4. Protéger par `auth`, `verified` et une Policy.
5. Ajouter un test Feature (statut 200/302/403/422 selon le cas).
6. `php artisan route:list --name=<nouveau>` pour vérifier.
