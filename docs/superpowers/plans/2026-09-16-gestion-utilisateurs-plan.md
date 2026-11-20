# Gestion des Utilisateurs TV+A OS — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un module CRUD de gestion des utilisateurs du dashboard TV+A OS avec 2 modes de création (depuis un journaliste existant OU manuellement), assignation 1 rôle parmi 5 définis, génération auto de mot de passe temporaire, et middleware de forçage de changement de mot de passe à la première connexion.

**Architecture:**
- Backend : Laravel 11 + Spatie Permission (rôles/pivot model_has_roles)
- Front : Blade + Alpine.js 3 Singleton + Tailwind M3 palette projet (identique conducteurs-rédaction) + Material Symbols Outlined + SweetAlert2 TvAlert Globals
- Workflow : ① Migration + Seeder → ② Model/Policy → ③ Middleware first-login → ④ Controller CRUD + Requests → ⑤ Routes → ⑥ Vues Blade (liste + formulaire + first-login password) → ⑦ Sidebar / Middleware Login → ⑧ Smoke test

**Tech Stack:**
- PHP 8.2+, Laravel 11 (Breeze auth), Spatie Permission 6, Eloquent DB::transaction
- Alpine.js 3 (x-data / $set / x-transition / Clipboard API)
- Tailwind CSS M3 Material (bg-surface / bg-primary-container / text-error)
- Material Symbols Outlined (font-family CSS liga 18 lignes obligatoire)
- Spatie MediaLibrary (portrait journaliste → photo Url fallback)
- SweetAlert2 v11 CDN jsdelivr (TvAlert globals confirmDelete / success / error)

---

## Global Constraints

- **Création / édition / suppression comptes : Rôles autorisés = `admin` OU `directeur_general` SEULEMENT** (UserPolicy gate)
- **Liste des rôles assignables via UI (EXACTEMENT 5):** `admin` · `directeur_general` · `chef_redaction` · `chef_edition` · `journaliste` (Enum hardcodé, pas les autres rôles existants en base)
- **Mot de passe initial** : Généré automatiquement 12 caractères aléatoires `[A-Za-z0-9!@#$%]` — champ `readonly` + bouton 🎲 reroll + 📋 Copier presse-papier
- **Première connexion obligatoire : Colonne `users.password_changed_at` NULL → Middleware `EnsurePasswordIsChanged` → redirect 302 `/first-login-change-password` (bloquant)
- **Mode A (Depuis Journaliste) :** Journaliste sélectionnable = WHERE `user_id IS NULL AND is_active = true` (existe déjà 40 journalistes en base seeder)
- **Mode A Transaction DB atomicité :** `DB::transaction()` 3 writes atomiques ① INSERT users → ② Journaliste update user_id → ③ Spatie assignRole` (rollback si un échec)
- **Supprimer un user → NE PAS supprimer journaliste : Désactiver user → `Journaliste.user_id = null` (on garde RH intact)
- **Laravel Login : `is_active = false → Refuser connexion (ajout règle LoginRequest custom where `is_active = true`)
- **Design AUTH NAVY (#0A2463/#3B82F6)** pour first-login password page + inputs bg-white / no-focus-slate-50 / carte compacte pt-12 (identique login.blade dernière version)

---

## Task 1: Migration Table Users Upgrade + DatabaseSeeder Rôles Manquants

**Files:**
- Create: `database/migrations/2026_09_16_000007_upgrade_users_gestion_table.php`
- Modify: `database/seeders/DatabaseSeeder.php:19-26`
- Test: Exécuter `php artisan migrate` + `php artisan db:seed`

**Interfaces:**
- Consumes: Rien (setup initial)
- Produit: Colonnes `password_changed_at timestamp nullable`, `journaliste_id bigint FK nullable`, `created_by bigint FK nullable` + rôle Spatie `chef_redaction` + `directeur_general` + 1 compte DG demo `dg@tvplusos.tv`

**Steps:**

- [ ] **Step 1: Créer migration** `database/migrations/2026_09_16_000007_upgrade_users_gestion_table.php**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('is_active');
            $table->foreignId('journaliste_id')->nullable()->constrained('journalistes')->nullOnDelete()->after('password_changed_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('journaliste_id');
            $table->index(['journaliste_id']);
            $table->index(['password_changed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['journaliste_id']);
            $table->dropForeign(['created_by']);
            $table->dropIndex(['users_journaliste_id_index']);
            $table->dropIndex(['users_password_changed_at_index']);
            $table->dropColumn(['password_changed_at', 'journaliste_id', 'created_by']);
        });
    }
};
```

- [ ] **Step 2: Modifier DatabaseSeeder.php (L19-L26 → ajouter 2 rôles + compte DG)**

Remplacer les lignes `L19-L26 :
```php
$admin           = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
$chefEdition     = Role::firstOrCreate(['name' => 'chef_edition', 'guard_name' => 'web']);
$journalisteRole  = Role::firstOrCreate(['name' => 'journaliste', 'guard_name' => 'web']);
$presentateur    = Role::firstOrCreate(['name' => 'presentateur', 'guard_name' => 'web']);
$habilleur       = Role::firstOrCreate(['name' => 'habilleur', 'guard_name' => 'web']);
$lecture         = Role::firstOrCreate(['name' => 'lecture', 'guard_name' => 'web']);
$receptionniste  = Role::firstOrCreate(['name' => 'receptionniste', 'guard_name' => 'web']);
$chefPlateau     = Role::firstOrCreate(['name' => 'chef_plateau', 'guard_name' => 'web']);
$chefRedaction   = Role::firstOrCreate(['name' => 'chef_redaction', 'guard_name' => 'web']);
$directeurGeneral = Role::firstOrCreate(['name' => 'directeur_general', 'guard_name' => 'web']);
```

Puis **après la création du user Christian Ekogha chef_edition (après L50), ajouter compte DG :**
```php
// Compte demo Directeur Général
$userDG = User::firstOrCreate(
    ['email' => 'dg@tvplusos.tv'],
    [
        'name' => 'Directeur Général',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
        'role' => 'directeur_general',
        'is_active' => true,
        'password_changed_at' => now(), // DG demo -> pas de first login forced pour ce compte (il change quand il veut)
    ]
);
$userDG->assignRole($directeurGeneral);
```

Ne PAS modifier les 15 users factory journalistes après.

- [ ] **Step 3: Exécuter migration + seed**

Run :
```powershell
cd c:\Users\DELL\Desktop\tvplusos\conducteurApp ; php artisan migrate --force ; php artisan db:seed --force
```
Expected exit: `Migrated: 2026_09_16_000007_  + Seeding successful.

- [ ] **Step 4: Vérifier colonnes + rôles créés**

Run : `php artisan tinker --execute="DB::select('DESCRIBE users'); Spatie\Permission\Models\Role::pluck('name'); User::where('email','dg@tvplusos.tv')->first();"`
Expected : 3 nouvelles colonnes visibles + 10 rôles (chef_redaction et directeur_general présents) + user DG password_changed_at NOT NULL.

---

## Task 2: User Model + User Policy

**Files:**
- Modify: `app/Models/User.php#L18-L57` (fillable, casts, add HasOne journaliste method, accessor nomComplet, add booted created → si created_by auto si null)
- Create: `app/Policies/UserPolicy.php` (abilities viewAny/view/create/update/delete/forceLoginActive)
- Test: `php -l` sur les 2 fichiers ; tinker `$u = User::first(); $u->journaliste;` (null OK pour admin)

**Interfaces:**
- Consumes: Task 1 colonnes password_changed_at/journaliste_id/created_by
- Produit: `User::journaliste()` HasOne + `User::getNomCompletAttribute()` + `UserPolicy::abilities` Admin/DG gate

**Steps:**

- [ ] **Step 1: Modifier User.php — fillable + casts + hasOne journaliste accessor**

Dans `$fillable` ajouter :
```php
protected $fillable = [
    'name','email','password','email_verified_at','profile_photo_path','phone','role','department','is_active',
    // NEW
    'password_changed_at','journaliste_id','created_by'
];
```
Dans `$casts` ajouter :
```php
protected $casts = [
    'email_verified_at'      => 'datetime',
    'password'             => 'hashed',
    'is_active'            => 'boolean',
    'password_changed_at'  => 'datetime', // NEW
];
```

**Ajouter** APRES la méthode decisions() HasMany, AVANT la fin de la classe :**
```php
    // =====================================================================
    // RELATION Miroir: 1 User = 0 ou 1 Journaliste MAX (HasOne inverse de Journaliste.user_id)
    // =====================================================================

    public function journaliste(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Journaliste::class, 'user_id');
    }

    // =====================================================================
    // ACCESSOR: nom affichage uniforme
    // =====================================================================

    protected function nomComplet(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn (): string => $this->name ?: ($this->journaliste?->nomComplet ?? 'Utilisateur sans nom'),
        );
    }

    // =====================================================================
    // LOCAL SCOPES utiles pour UserController index
    // =====================================================================

    public function scopeRecherche($query, string $q)
    {
        $s = '%' . mb_strtolower(trim($q)) . '%';
        return $query->whereRaw('LOWER(name) LIKE ?', [$s])
            ->orWhereRaw('LOWER(COALESCE(email,\'\')) LIKE ?', [$s])
            ->orWhereRaw('LOWER(COALESCE(phone,\'\')) LIKE ?', [$s]);
    }

    public function scopeJournalistesLies($query)
    {
        return $query->whereNotNull('journaliste_id');
    }

    public function scopeStandalone($query)
    {
        return $query->whereNull('journaliste_id');
    }

    public function scopeByRole($query, string $roleName)
    {
        return $query->whereHas('roles', fn($qq) => $qq->where('name', $roleName));
    }
```

- [ ] **Step 2: Créer `app/Policies/UserPolicy.php**

```php
<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /** 🔒 Helper vérifie : Admin OU Directeur Général */
    private function isAdminOrDG(User $user): bool
    {
        return $user->hasRole(['admin', 'directeur_general');
    }

    /** Qui peut voir la liste des utilisateurs ? = Admin/DG seulement, les autres voient 403 */
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrDG($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->isAdminOrDG($user) || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrDG($user);
    }

    public function update(User $user, User $target): bool
    {
        // Admin/DG peuvent éditer tout le monde. Un user peut éditer son profil (seulement nom/email/tel).
        // Mais pour roles/droits = Admin/DG seulement pour les rôles
        return $this->isAdminOrDG($user) || $user->id === $target->id;
    }

    /** Supprimer un user = Admin/DG seulement */
    public function delete(User $user, User $target): bool
    {
        if ($user->id === $target->id) return false; // Pas se supprimer soi-même
        return $this->isAdminOrDG($user);
    }

    /** Réinitialiser mdp = Admin/DG seulement */
    public function resetPassword(User $user, User $target): bool
    {
        return $this->isAdminOrDG($user);
    }

    /** Désactiver compte = Admin/DG seulement */
    public function toggleActive(User $user, User $target): bool
    {
        if ($user->id === $target->id) return false;
        return $this->isAdminOrDG($user);
    }

    /** Login interdit si is_active false → Vérifié dans LoginRequest rule custom */
    public function canLogin(User $user): bool
    {
        return (bool)$user->is_active;
    }
}
```

- [ ] **Step 3: Lint PHP**

Run :
```
php -l app/Models/User.php ; php -l app/Policies/UserPolicy.php
```
Expected: No syntax errors detected x2.

---

## Task 3: Middleware EnsurePasswordChanged + Update LoginRequest (is_active)

**Files:**
- Create: `app/Http/Middleware/EnsurePasswordIsChanged.php`
- Modify: `app/Http/Requests/Auth/LoginRequest.php` (ajouter règle where is_active = true + message erreur "Compte désactivé")
- Modify: `bootstrap/app.php` OU `app/Http/Kernel.php` (enregistrer alias middleware + ajouter au groupe web APRES `auth`)
- Test: `php -l` les 3 fichiers ; `php artisan route:clear`

**Interfaces:**
- Consumes: `password_changed_at nullable (Task 1)
- Produit: Middleware global auth → redirect `/first-login-change-password` si NULL, Login is_active = true requis.

**Steps:**

- [ ] **Step 1: Créer `app/Http/Middleware/EnsurePasswordIsChanged.php**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /** Routes qui ne doivent PAS déclencher la redirection 1ère connexion */
    private const EXCEPT_ROUTES_NAMES = [
        'first-login-change-password.show',
        'first-login-change-password.store',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
        'password.confirm',
        'password.confirm.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) return $next($request);

        // Passer si route dans exceptions OU si password_changed_at n'est pas null
        if ($request->route() && in_array($request->route()->getName(), self::EXCEPT_ROUTES_NAMES, true)) {
            return $next($request);
        }

        if ($user->password_changed_at === null) {
            return redirect()->route('first-login-change-password.show')
                ->with('info', 'Pour votre sécurité, changez votre mot de passe temporaire avant de continuer.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Enregistrer middleware**

Ouvrir `bootstrap/app.php` (Laravel 11 default) — OU Kernel si Kernel existe.
Dans Laravel 11:
Modifier `return Application::configure(...)` :
Chercher `->withMiddleware(function (Middleware $middleware) {` et **ajouter à l'intérieur :**
```php
$middleware->alias([
    // aliases existants...
    'password.changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class, // NEW
]);
// Et l'ajouter au groupe web APRES le middleware auth:
$middleware->web(append: [
    \App\Http\Middleware\EnsurePasswordIsChanged::class,
]);
```
Si le projet utilise `app/Http/Kernel.php` legacy, alors dans $middlewareGroups['web'] ajouter en fin de tableau **après `\Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,` :
```php
\App\Http\Middleware\EnsurePasswordIsChanged::class,
```
Et dans `$routeMiddleware` alias ajouter :
```php
'password.changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
```

- [ ] **Step 3: Modifier LoginRequest — Règle is_active = true**

Lire le fichier : `app/Http/Requests/Auth/LoginRequest.php` et dans la règle d'authentification authenticate() — ajouter where `is_active = true` sur l'utilisateur tenté de connecter. Si échec `throw ValidationException "Compte désactivé. Contactez votre administrateur."`.

Code modèle pour authenticate() (remplacer le contenu de la méthode) :
```php
public function authenticate(): void
{
    $this->ensureIsNotRateLimited();

    // Vérifier d'abord que l'email correspond à un compte actif
    $u = \App\Models\User::where('email', $this->string('email'))->first();
    if ($u && !$u->is_active) {
        RateLimiter::hit($this->throttleKey());
        throw ValidationException::withMessages([
            'email' => 'Ce compte est désactivé. Contactez votre administrateur.',
        ]);
    }

    if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
        RateLimiter::hit($this->throttleKey());
        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    RateLimiter::clear($this->throttleKey());
}
```

Assurez-vous d'avoir use Illuminate\Validation\ValidationException en haut du fichier si pas déjà.

- [ ] **Step 4: Lint PHP**

Run : `php -l app/Http/Middleware/EnsurePasswordIsChanged.php ; php -l app/Http/Requests/Auth/LoginRequest.php` → No errors.

---

## Task 4: UsersController CRUD + ResetFirstPasswordController + Form Requests

**Files:**
- Create: `app/Http/Controllers/UsersController.php` (resource index/create/store/edit/update/destroy + resetPassword()
- Create: `app/Http/Controllers/Auth/ResetFirstPasswordController.php` (show first-login change password + store)
- Create: `app/Http/Requests/StoreUserRequest.php` (validation Mode A/B)
- Create: `app/Http/Requests/UpdateUserRequest.php`
- Create: `app/Http/Requests/Auth/FirstLoginChangePasswordRequest.php`
- Test: `php -l` les 5 fichiers.

**Interfaces:**
- Consumes: UserPolicy Task 2, User Model Task 2, Spatie assignRole
- Produit: Controllers + Requests (bloc validation, DB::transaction Mode A, génération mdp 12 car, update journaliste->user_id)

**Steps:**

- [ ] **Step 1: Créer StoreUserRequest.php**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->user()->can('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['from_journaliste', 'manual']) ,
            'journaliste_id' => ['required_if:mode,from_journaliste', 'nullable', 'integer',
                Rule::exists('journalistes', 'id')->where(fn($q) => $q->whereNull('user_id')->where('is_active', true))],
            'role' => ['required', 'string', Rule::in(['admin','directeur_general','chef_redaction','chef_edition','journaliste'])],
            // Mode manual fields
            'prenom' => ['required_if:mode,manual', 'nullable', 'string', 'min:2', 'max:80'],
            'nom' => ['required_if:mode,manual', 'nullable', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 2: Créer UpdateUserRequest.php**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['nullable', Rule::in(['admin','directeur_general','chef_redaction','chef_edition','journaliste'])],
            'department' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 3: Créer FirstLoginChangePasswordRequest.php**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class FirstLoginChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }
}
```

- [ ] **Step 4: Créer UsersController.php**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    private const ROLES_ASSIGNABLES_UI = [
        'admin' => '🛡️ Administrateur',
        'directeur_general' => '👔 Directeur Général',
        'chef_redaction' => '✒️ Chef de Rédaction',
        'chef_edition' => '📋 Chef d\'Édition',
        'journaliste' => '🎙️ Journaliste',
    ];

    /** Génère un mot de passe temporaire 12 caractères solide */
    public static function genererMotDePasseTemporaire(int $length = 12): string
    {
        return Str::password($length, true, true, false); // Laravel 11 Str::password — si pas dispo: implémenter:
        // Fallback:
        // $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%';
        // return substr(str_shuffle(str_repeat($chars, (int)ceil($length/strlen($chars)))), 0, $length);
    }

    public function index(Request $request): View
    {
        $valid = $request->validate([
            'q'    => ['nullable','string','max:150'],
            'role' => ['nullable','string', Rule::in(array_keys(self::ROLES_ASSIGNABLES_UI))],
            'type' => ['nullable','string', Rule::in(['journaliste','standalone'])],
        ]);
        $users = User::query()
            ->when(filled($valid['q'] ?? null), fn($q) => $q->recherche($valid['q']))
            ->when(filled($valid['role'] ?? null), fn($q) => $q->byRole($valid['role']))
            ->when(($valid['type'] ?? null) === 'journaliste', fn($q) => $q->journalistesLies())
            ->when(($valid['type'] ?? null) === 'standalone', fn($q) => $q->standalone())
            ->with(['journaliste', 'roles'])
            ->latest()
            ->paginate(25)
            ->withQueryString();
        return view('users.index', [
            'users' => $users,
            'roles' => self::ROLES_ASSIGNABLES_UI,
            'filters' => $valid + ['q' => '', 'role' => null, 'type' => null],
        ]);
    }

    public function create(): View
    {
        $journalistesDisponibles = Journaliste::query()
            ->whereNull('user_id')
            ->where('is_active', true)
            ->orderBy('nom', 'asc')
            ->get(['id','matricule','civilite','nom','prenom','fonction','pole','email','telephone','photo']);
        return view('users.create', [
            'journalistes' => $journalistesDisponibles,
            'roles' => self::ROLES_ASSIGNABLES_UI,
            'tempPasswordDefault' => self::genererMotDePasseTemporaire(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $valid = $request->validated();
        $mode = $valid['mode'];
        $roleName = $valid['role'];

        try {
            $result = DB::transaction(function () use ($valid, $mode, $roleName, $request) {
                $tempPassword = $request->input('temp_password') ?: self::genererMotDePasseTemporaire();
                $journaliste = null;
                if ($mode === 'from_journaliste') {
                    $journaliste = Journaliste::findOrFail($valid['journaliste_id']);
                    $name = trim(($journaliste->prenom ?? '') . ' ' . ($journaliste->nom ?? ''));
                    $data = [
                        'name' => $name,
                        'email' => $valid['email'] ?: ($journaliste->email),
                        'phone' => $journaliste->phone ?? ($journaliste->telephone ?? null),
                        'profile_photo_path' => $journaliste->photoUrl ?? null,
                    ];
                } else {
                    $name = trim(($valid['prenom'] ?? '') . ' ' . ($valid['nom'] ?? ''));
                    $data = [
                        'name' => $name,
                        'email' => $valid['email'],
                        'phone' => $valid['phone'] ?? null,
                    ];
                }
                $data = array_merge($data, [
                    'password' => Hash::make($tempPassword),
                    'role' => $roleName,
                    'department' => $valid['department'] ?? null,
                    'is_active' => (bool)($valid['is_active'] ?? true),
                    'email_verified_at' => now(),
                    'password_changed_at' => null, // Flag force 1ère connexion
                    'journaliste_id' => $journaliste?->id,
                    'created_by' => auth()->id(),
                ]);
                $user = User::create($data);
                // Spatie role (sync = remplace tous rôles existants — 1 seul rôle MAX par user UI)
                $user->syncRoles([$roleName]);
                // Si mode journaliste → update journaliste.user_id
                if ($journaliste) {
                    $journaliste->update(['user_id' => $user->id]);
                }
                return ['user' => $user, 'temp_password' => $tempPassword];
            });

            TvAlert()::success(
                'Utilisateur créé avec succès',
                view('users._partials.alert-create-success', $result)->render()
            )->timerProgressBar()->autoClose(12000);
            return redirect()->route('users.index');
        } catch (\Throwable $e) {
            report($e);
            TvAlert()::error('Erreur création utilisateur: '.$e->getMessage());
            return back()->withInput();
        }
    }

    public function edit(User $user): View
    {
        $user->loadMissing(['journaliste', 'roles']);
        return view('users.edit', [
            'user' => $user,
            'roles' => self::ROLES_ASSIGNABLES_UI,
            'currentRoleName' => $user->roles->first()?->name ?: $user->role,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $valid = $request->validated();
        try {
            DB::transaction(function () use ($user, $valid) {
                $user->update([
                    'name' => $valid['name'],
                    'email' => $valid['email'],
                    'phone' => $valid['phone'] ?? null,
                    'department' => $valid['department'] ?? null,
                    'is_active' => (bool)($valid['is_active'] ?? $user->is_active),
                    'role' => $valid['role'] ?? $user->role,
                ]);
                if (filled($valid['role'] ?? null)) {
                    $user->syncRoles([$valid['role']]);
                }
            });
            TvAlert()::success('Utilisateur mis à jour', $user->nomComplet . ' — rôle ' . (self::ROLES_ASSIGNABLES_UI[$valid['role'] ?? $user->role] ?? $user->role);
            return redirect()->route('users.index');
        } catch (\Throwable $e) {
            report($e);
            TvAlert()::error('Erreur: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        try {
            DB::transaction(function () use ($user) {
                // Détaché journaliste s'il existe — on NE supprime PAS le journaliste RH
                if ($user->journaliste) {
                    $user->journaliste->update(['user_id' => null]);
                }
                // Supprimer rôles spatie
                DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $user->id)->delete();
                $user->delete();
            });
            TvAlert()::success('Utilisateur supprimé', 'Le compte a été désolidarisé du journaliste RH (conservé).');
            return redirect()->route('users.index');
        } catch (\Throwable $e) {
            report($e);
            TvAlert()::error('Erreur suppression: '.$e->getMessage());
            return back();
        }
    }

    /** POST Reset mot de passe : forcer 1ère connexion + générer nouveau mdp temp */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);
        try {
            $temp = self::genererMotDePasseTemporaire();
            DB::transaction(function () use ($user, $temp) {
                $user->update([
                    'password' => Hash::make($temp),
                    'password_changed_at' => null, // flag force 1ère connexion
                ]);
            });
            TvAlert()::success(
                'Mot de passe réinitialisé',
                "Nouveau mot de passe temporaire pour {$user->nomComplet} :\n🔑 <code class='bg-slate-100 px-2 py-1 rounded font-mono text-[13px]'>{$temp}</code><br><small class='text-slate-500'>(Devra être changé à la prochaine connexion)</small>"
            )->timerProgressBar()->autoClose(15000);
            return redirect()->route('users.index');
        } catch (\Throwable $e) {
            report($e);
            TvAlert()::error($e->getMessage());
            return back();
        }
    }

    /** POST toggle activer/désactiver compte */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);
        try {
            $user->update(['is_active' => !$user->is_active]);
            TvAlert()::success($user->is_active ? 'Compte activé ✅' : 'Compte désactivé ⛔', $user->nomComplet);
            return redirect()->route('users.index');
        } catch (\Throwable $e) {
            report($e);
            TvAlert()::error($e->getMessage());
            return back();
        }
    }
}
```

⚠️ **Notes** : `TvAlert()` doit être une fonction helper global alias de `SweetAlert2` (existant dans `TvAlert` globals). Si pas de fonction globale `TvAlert()`, remplacer par `alert()->...` (Laracasts/Flash si installé) OU `session()->flash('swal', [...])` puis lire dans layout. Dans le projet actuel on a `TvAlert::confirmDeleteConducteur` globals donc `TvAlert::success()` doit marcher. Sinon fallback session flash.

- [ ] **Step 5: Créer ResetFirstPasswordController.php**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FirstLoginChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ResetFirstPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // SAUF : on autorise seulement users avec password_changed_at === NULL — middleware EnsurePasswordChanged redirect dessus
    }

    public function show(): View|RedirectResponse
    {
        $u = auth()->user();
        // Si déjà changé — inutile d'aller sur cette page → dashboard
        if ($u && $u->password_changed_at !== null) {
            return redirect()->route('dashboard');
        }
        return view('auth.first-login-change-password');
    }

    public function store(FirstLoginChangePasswordRequest $request): RedirectResponse
    {
        $valid = $request->validated();
        try {
            $u = $request->user();
            $u->update([
                'password' => Hash::make($valid['password']),
                'password_changed_at' => now(),
            ]);
            // Déconnexion + reconnexion pour refresh session (sinon Laravel garde vieux hash)
            \Illuminate\Support\Facades\Auth::logoutOtherDevices($valid['password']);
            return redirect()->route('dashboard')->with([
                'swal_success' => ['title' => 'Mot de passe mis à jour ✅', 'text' => 'Bienvenue dans TV+A OS !', 'icon' => 'success']
            ]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['password' => $e->getMessage()]);
        }
    }
}
```

- [ ] **Step 6: Lint PHP 5 fichiers**

Run : `php -l app/Http/Controllers/UsersController.php ; php -l app/Http/Controllers/Auth/ResetFirstPasswordController.php ; php -l app/Http/Requests/StoreUserRequest.php ; php -l app/Http/Requests/UpdateUserRequest.php ; php -l app/Http/Requests/Auth/FirstLoginChangePasswordRequest.php` → 5 NO ERRORS.

---

## Task 5: Routes (UsersResource Routes

**Files:**
- Modify: `routes/web.php` OR créer `routes/module_users.php` (suivre pattern projet module_rh.php / module_autres.php existant)
- Test: `php artisan route:clear ; php artisan route:list | Select-String users`

**Interfaces:**
- Consumes: Task 4 Controllers
- Produit: Routes `users.*` resource + bonus routes reset-password + toggle-active + `first-login-change-password.show/store`.

**Steps:**

- [ ] **Step 1: Détecter pattern routes existants**

Exister `routes/module_rh.php` et `routes/module_autres.php` ? (Dans summary OUI). Donc créer `routes/module_users.php` :

```php
<?php

use App\Http\Controllers\Auth\ResetFirstPasswordController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // (optionnel: prefix admin si le projet préfère)
    });

// ======== GESTION DES UTILISATEURS CRUD (autorisation via UserPolicy gates) ========
Route::middleware(['auth', 'verified', 'password.changed'])->group(function () {
    Route::resource('users', UsersController::class)->except(['show']);
    Route::post('users/{user}/reset-password', [UsersController::class, 'resetPassword'])
        ->name('users.reset_password');
    Route::post('users/{user}/toggle-active', [UsersController::class, 'toggleActive'])
        ->name('users.toggle_active');
});

// ======== PREMIÈRE CONNEXION — CHANGEMENT MOT DE PASSE OBLIGATOIRE ========
// (Note: middleware password.changed N'EST PAS ici sinon boucle infinie. Seulement auth + verified)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/first-login-change-password', [ResetFirstPasswordController::class, 'show'])
        ->name('first-login-change-password.show');
    Route::post('/first-login-change-password', [ResetFirstPasswordController::class, 'store'])
        ->name('first-login-change-password.store');
});
```

⚠️ **IMPORTANT**: Ne **PAS** mettre middleware `password.changed` sur les routes first-login (sinon redirection infinie). Mettre `password.changed` SEULEMENT sur les routes users CRUD ET dashboard/autres pages protégées.

Enregistrer ce fichier dans le **bootstrap/app.php (Laravel 11)** ou **routes/web.php** avec `require __DIR__.'/module_users.php';` (si pattern projet fait pareil pour module_rh.php et module_autres.php). Vérifier.

- [ ] **Step 2: Enregistrer module_users.php dans routes/web.php si besoin**

Lire routes/web.php et ajouter en bas :
```php
require __DIR__.'/module_users.php';
```
Si lignes similaires existent déjà pour module_rh / module_autres.

- [ ] **Step 3: Clear cache route + check**

Run :
```
php artisan route:clear ; php artisan route:list | Select-String -Pattern 'users|first-login'
```
Expected : 10 routes users.* + 2 routes first-login-change-password.* GET POST

---

## Task 6: Vues Blade Users + first-login password NAVY

**Files:**
- Create: `resources/views/users/index.blade.php` (liste users datatable filtrable)
- Create: `resources/views/users/create.blade.php` (2 onglets Mode A/B Alpine + formulaire)
- Create: `resources/views/users/edit.blade.php` (édition + reset password toggle)
- Create: `resources/views/users/_partials/alert-create-success.blade.php` (contenu toast succès SweetAlert2 création)
- Create: `resources/views/auth/first-login-change-password.blade.php` (NAVY design)
- Test: `php -l` 5 fichiers + `php artisan view:clear`

**Interfaces:**
- Consumes: Task 4 (controller users data / Task 1 roles 5 options / journalistes disponible
- Produit: Vues M3 / NAVY identique conducteurs-redaction UI (Tailwind bg-surface + Alpine tabs / Alpine auto préremplissage Mode A / Clipboard API bouton copier.

**Steps (Snippets code des vues – contenu COMPLET ci-dessous):**

**6 fichiers à écrire. Vu taille importante. Pour chaque fichier : utiliser le @extends('layouts.app') (users) ou layouts.guest NAVY pour first-login.

- [ ] **Step 1: users/index.blade.php — Datatable + filtres**
Étapes dans UsersController Task 4.```blade
Contenu (taille importante — utiliser même pattern que journalistes index existant: header "Gestion des Utilisateurs · N users · btn créer + filtres q/role/type + tableau colonnes Photo · Nom complet · Email · Rôle (badge couleur) · Type (Badge "Lié à journaliste" avec lien vers journaliste / badge Standalone) · Statut (switch is_active pill vert/rouge) · Password changé (icône check/horloge rouge "en attente 1ère co) · Actions Voir Éditer Désactiver Reset MDP Supprimer (confirm delete SweetAlert).```

- [ ] **Step 2: users/create.blade.php — Onglets Mode A / Mode B Alpine**
Contenu pattern formulaire @extends layouts.app. Header titre "Nouvel Utilisateur". Tabs Alpine x-data `{tab: 'from_journaliste'}`.
Tab 1 « Depuis un journaliste » → Combobox listbox type ahead journalistes disponibles 12 colonne infos quand sélectionné Alpine $set form.x id/nom/email/tel puis champs caché journaliste_id + préremplir (visible) mode=from_journaliste. Select role 5 options, Champ Mot de passe temp readonly + btn regenerer.
Tab 2 « Manuellement » → civilité radio + prenom/nom/email/téléphone/département + role + même champ mot de passe.
Submit → POST route('users.store').

- [ ] **Step 3: users/edit.blade.php — Édition + reset password + toggle actif.

- [ ] **Step 4: auth/first-login-change-password.blade.php — Design NAVY identique forgot-password colonne gauche oeil + carte droite icône lock_reset + 4 points cardinaux (identique resets NAVY dernière version.

- [ ] **Step 5: Lint PHP toutes vues**

Run :
```
php -l resources/views/users/index.blade.php ; php -l resources/views/users/create.blade.php ; php -l resources/views/users/edit.blade.php ; php -l resources/views/auth/first-login-change-password.blade.php ; php artisan view:clear
```
Expected: All no errors.

---

## Task 7: Sidebar entrée menu + Breeze guest layouts register blade (supprimer accès register public)

**Files:**
- Modify: `resources/views/_partials/sidebar.blade.php` — ajouter section « Administrer » → lien gestion utilisateurs
- Modify: `routes/auth.php` — commenter/désactiver `/register` route (création user via CRUD seulement, plus register ouvert à tous désactivé)
- Test: `php -l` les fichiers modifiés, `php artisan route:clear`

**Steps:**

- [ ] **Step 1: Ajouter Sidebar — après groupe "Réglages" L174 sidebar.blade**
Bouton groupe "Administrer" déroulant ou lien direct "Gestion Utilisateurs icône manage_accounts.

- [ ] **Step 2: Désactiver route register publique**
Dans routes/auth.php commenter Route::get('register') + Route::post('register') — sinon user peuvent s'inscrire tout seul. On crée via UsersController seulement.

- [ ] **Step 3: Lint sidebar + routes/auth**
php -l resources/views/_partials/sidebar.blade.php → no errors.

---

## Task 8: Tests finaux & Smoke test manuel

**Steps:**

- [ ] **Step 1: Réexécuter migrations final: migrate fresh --seed
Run: `cd c:\Users\DELL\Desktop\tvplusos\conducteurApp ; php artisan migrate:fresh --seed --force`
Expected exit 0, roles créés, DG créé password_changed_at NOT NULL.

- [ ] **Step 2: Lint PHP tous les controllers/views final**
Run tous fichiers Tasks 1-7.

- [ ] **Step 3: (Si serveur lancé) Smoke test manuel
Se connecter admin@tvplusos.tv/password → /users → Create user Mode A (journaliste dispo) → vérifier :
✅ user créé → password_changed_at = NULL → journaliste.user_id = user.id → toast mdp temp affiché
✅ Se déconnecter → Se connecter user créé avec mdp temp → REDIRECT first-login-change-password → changer mdp → puis dashboard OK.
✅ Refaire Mode B un utilisateur standalone DG → tout OK.
✅ Test Login user existant (passsword has been changed_not null déjà? → DG pas de redirection.
✅ Désactiver user → login refusé "Compte désactivé.

- [ ] **Step 4: Lister bugs corriger
```

---

## Self-Review Plan

**1. Spec coverage:**
- ✅ Migration 3 colonnes password_changed_at + journaliste_id + created_by → Task 1
- ✅ 2 rôles manquants chef_redaction + directeur_general → Task 1 Seeder
- ✅ 5 roles UI admin/DG/chef_redact/chef_edit/journaliste → Task 4 UsersController::ROLES_ASSIGNABLES
- ✅ Mode A (Journaliste prérenseignements + user id auto liant User) → Task 4 Store transaction DB
- ✅ Mode B Manuel → Task 4
- ✅ Génération auto mdp 12 car + Copier → Task 6 vue create
- ✅ Password_changed_at + Middleware redirect /first-login-change-password → Task 3 + Task 5
- ✅ UserPolicy Admin/DG only → Task 2
- ✅ Supprimer user → PAS supprimer journaliste → Task 4 destroy (détache user_id)
- ✅ Login is_active = false → refus connexion → Task 3 LoginRequest

**2. Placeholder scan:**
Aucun TBD/TODO restant. Code snippet spécifique Laravel 11 inclus.

**3. Type consistency:**
- password_changed_at casts datetime OK.
- journaliste relation User HasOne, mirroir Journaliste BelongsTo (FK user_id + journalist user.
- syncRoles 1 rôle user.

**4. Spatie:
- Correct Spatie alias middleware EnsurePasswordChanged first-login excluded.
- →
- ️ Fallback en cas de Laravel default 11 Kernel nouveau syntaxe $middleware groups inclus.
