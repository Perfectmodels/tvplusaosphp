<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\CoordonneesCompteMail;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public static function rolesAssignableTo(User $actor): array
    {
        if (! $actor->hasAnyRole(['admin', 'superadmin'])) {
            return [];
        }

        $roles = collect(config('workspaces.roles', []))
            ->map(static fn (string $label): array => ['label' => $label, 'badge' => 'badge-blue'])
            ->all();

        return $actor->hasRole('superadmin')
            ? $roles
            : array_diff_key($roles, ['superadmin' => true]);
    }

    public static function genererMotDePasseTemporaire(int $length = 12): string
    {
        if (method_exists(Str::class, 'password')) {
            // Symbols requis par la politique de changement (FirstLoginChangePasswordRequest) :
            // min(8) + mixedCase + numbers + symbols.
            return (string) Str::password($length, true, true, true, false);
        }
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%';

        return substr(str_shuffle(str_repeat($chars, (int) ceil($length / strlen($chars)))), 0, $length);
    }

    public function index(Request $request): View
    {
        $valid = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
        ]);
        $roleKeys = array_keys(config('workspaces.roles', []));
        $users = User::query()
            ->when(filled($valid['q'] ?? null), fn ($q) => $q->recherche($valid['q']))
            ->when(filled($valid['role'] ?? null) && in_array($valid['role'], $roleKeys, true), fn ($q) => $q->byRole($valid['role']))
            ->when(($valid['type'] ?? null) === 'journaliste', fn ($q) => $q->journalistesLies())
            ->when(($valid['type'] ?? null) === 'standalone', fn ($q) => $q->standalone())
            ->with(['journaliste', 'roles'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => self::rolesAssignableTo(auth()->user()),
            'filters' => [
                'q' => $valid['q'] ?? '',
                'role' => $valid['role'] ?? '',
                'type' => $valid['type'] ?? '',
            ],
        ]);
    }

    public function create(): View
    {
        $journalistesDisponibles = Journaliste::query()
            ->whereNull('user_id')
            ->whereNotIn('id', User::query()->whereNotNull('journaliste_id')->select('journaliste_id'))
            ->where('is_active', true)
            ->orderBy('nom', 'asc')
            ->get(['id', 'matricule', 'civilite', 'nom', 'prenom', 'fonction', 'pole', 'email', 'telephone', 'photo']);

        return view('users.create', [
            'journalistes' => $journalistesDisponibles,
            'roles' => self::rolesAssignableTo(auth()->user()),
            'tempPasswordDefault' => self::genererMotDePasseTemporaire(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $valid = $request->validated();
        $mode = $valid['mode'];
        $roleName = $valid['role'];
        $roleNames = array_values(array_unique(array_merge([$roleName], $valid['roles'] ?? [], $valid['additional_roles'] ?? [])));

        try {
            $result = DB::transaction(function () use ($valid, $mode, $roleName, $roleNames) {
                $tempPassword = ($valid['temp_password'] ?? null) ?: self::genererMotDePasseTemporaire();
                $journaliste = null;
                if ($mode === 'from_journaliste') {
                    $journaliste = Journaliste::query()->lockForUpdate()->findOrFail((int) $valid['journaliste_id']);
                    if ($journaliste->user_id !== null || User::where('journaliste_id', $journaliste->id)->exists()) {
                        throw ValidationException::withMessages(['journaliste_id' => 'Ce journaliste possède déjà un compte utilisateur.']);
                    }
                    $name = trim(($journaliste->prenom ?? '').' '.($journaliste->nom ?? ''));
                    $data = [
                        'name' => $name,
                        'email' => $valid['email'],
                        'phone' => $journaliste->phone ?? ($journaliste->telephone ?? null),
                        'profile_photo_path' => $journaliste->getRawOriginal('photo') ? (string) $journaliste->photo : null,
                    ];
                } else {
                    $name = trim(($valid['prenom'] ?? '').' '.($valid['nom'] ?? ''));
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
                    'is_active' => array_key_exists('is_active', $valid) ? (bool) $valid['is_active'] : true,
                    'email_verified_at' => now(),
                    'password_changed_at' => null, // Flag force 1ère connexion
                    'journaliste_id' => $journaliste?->id,
                    'created_by' => auth()->id(),
                ]);
                $user = User::create($data);
                $user->syncRoles($roleNames);
                if ($journaliste) {
                    $journaliste->update(['user_id' => $user->id, 'email' => $user->email]);
                }

                return ['user' => $user, 'temp_password' => $tempPassword];
            });

            $u = $result['user'];
            $tempPass = e($result['temp_password']);
            $nom = e($u->nomComplet);
            $email = e($u->email);
            $html = <<<HTML
<div class="text-left">
    <p class="mb-3 font-semibold text-on-surface">✅ Utilisateur <strong>{$nom}</strong> créé avec succès.</p>
    <div class="bg-surface-container-high/60 rounded-xl p-4 space-y-2 text-sm border border-outline-variant/50">
        <div class="flex gap-2"><span class="text-on-surface-variant w-32">📧 Email :</span><span class="font-semibold text-on-surface">{$email}</span></div>
        <div class="flex gap-2 items-start">
            <span class="text-on-surface-variant w-32 pt-1.5">🔑 Mot de passe temporaire :</span>
            <span class="font-mono font-bold bg-[#0A2463] text-white px-3 py-1.5 rounded-lg tracking-wider">{$tempPass}</span>
            <button type="button" onclick="navigator.clipboard.writeText('{$tempPass}'); TvAlert.success('Copié : {$tempPass}', {timer: 1400, title:'Presse-papier'});" class="ml-1 px-2.5 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high transition text-on-surface-variant">📋 Copier</button>
        </div>
        <p class="text-xs text-on-surface-variant pt-2 border-t border-outline-variant/40 mt-1.5">⚠️ Communiquer ce mot de passe à l'utilisateur. Il sera <strong>obligé de le changer à sa 1ère connexion</strong> (avant tout accès au dashboard).</p>
    </div>
</div>
HTML;

            if (! CoordonneesCompteMail::envoyer($u, $result['temp_password'], 'création')) {
                $html = $html.'<p class="text-xs text-error mt-2">⚠️ L’envoi de l’e-mail de coordonnées a échoué — les identifiants sont affichés dans cette alerte et devront être communiqués manuellement.</p>';
            }

            return redirect()->route('users.index')
                ->with('swal', ['type' => 'success', 'html' => $html, 'timer' => 0, 'title' => 'Utilisateur créé']);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()
                ->with('swal', ['type' => 'error', 'title' => "Erreur création d'utilisateur", 'text' => $e->getMessage()]);
        }
    }

    public function edit(User $user): View
    {
        $user->loadMissing(['journaliste', 'roles', 'createdByUser']);

        return view('users.edit', [
            'user' => $user,
            'roles' => self::rolesAssignableTo(auth()->user()),
            'currentRoleName' => optional($user->roles->first())->name ?: (string) $user->role,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $valid = $request->validated();
        try {
            DB::transaction(function () use ($user, $valid) {
                $payload = [
                    'name' => $valid['name'],
                    'email' => $valid['email'],
                    'phone' => $valid['phone'] ?? null,
                    'department' => $valid['department'] ?? null,
                ];

                if (array_key_exists('is_active', $valid)) {
                    $payload['is_active'] = (bool) $valid['is_active'];
                }

                $canAssign = auth()->user()?->can('assignRoles', $user) ?? false;
                if ($canAssign && filled($valid['role'] ?? null)) {
                    $roleName = (string) $valid['role'];
                    $roleNames = array_values(array_unique(array_merge([$roleName], $valid['roles'] ?? [])));
                    $payload['role'] = $roleName;
                    $user->update($payload);
                    $user->syncRoles($roleNames);
                } else {
                    $user->update($payload);
                }
            });

            return redirect()->route('users.index')
                ->with('swal', ['type' => 'success', 'timer' => 2400, 'title' => 'Utilisateur mis à jour', 'text' => $user->fresh()->nomComplet.' — profil et rôle actualisés.']);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()
                ->with('swal', ['type' => 'error', 'title' => 'Erreur mise à jour', 'text' => $e->getMessage()]);
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('swal', ['type' => 'error', 'title' => 'Action impossible', 'text' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }
        try {
            DB::transaction(function () use ($user) {
                if ($user->journaliste) {
                    $user->journaliste->update(['user_id' => null]);
                }
                DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', (int) $user->id)->delete();
                $user->delete();
            });

            return redirect()->route('users.index')
                ->with('swal', ['type' => 'success', 'timer' => 2400, 'title' => 'Utilisateur supprimé', 'text' => "Compte {$user->nomComplet} retiré. Le journaliste RH associé a été conservé et dissocié."]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('swal', ['type' => 'error', 'title' => 'Erreur suppression', 'text' => $e->getMessage()]);
        }
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);
        if ($user->id === auth()->id()) {
            return back()->with('swal', ['type' => 'error', 'title' => 'Action impossible', 'text' => 'Utilisez "Profil" pour changer votre propre mot de passe.']);
        }
        try {
            $temp = self::genererMotDePasseTemporaire();
            DB::transaction(function () use ($user, $temp) {
                $user->update([
                    'password' => Hash::make($temp),
                    'password_changed_at' => null,
                ]);
            });
            $nom = e($user->nomComplet);
            $t = e($temp);
            $html = <<<HTML
<div class="text-left">
    <p class="mb-3 font-semibold">🔑 Mot de passe réinitialisé pour <strong>{$nom}</strong>.</p>
    <div class="bg-surface-container-high/60 rounded-xl p-4 space-y-2 text-sm border border-outline-variant/50">
        <div class="flex gap-2 items-start">
            <span class="text-on-surface-variant w-28 pt-1.5">Nouveau mdp temporaire :</span>
            <span class="font-mono font-bold bg-[#0A2463] text-white px-3 py-1.5 rounded-lg tracking-wider">{$t}</span>
            <button type="button" onclick="navigator.clipboard.writeText('{$t}'); TvAlert.success('Copié : {$t}', {timer: 1400, title:'Presse-papier'});" class="ml-1 px-2.5 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high transition text-on-surface-variant">📋 Copier</button>
        </div>
        <p class="text-xs text-on-surface-variant pt-2 border-t border-outline-variant/40">À la prochaine connexion, l'utilisateur sera obligé de changer ce mot de passe.</p>
    </div>
</div>
HTML;

            if (! CoordonneesCompteMail::envoyer($user, $temp, 'réinitialisation')) {
                $html = $html.'<p class="text-xs text-error mt-2">⚠️ L’envoi de l’e-mail de réinitialisation a échoué — le mot de passe temporaire est affiché dans cette alerte et devra être communiqué manuellement.</p>';
            }

            return back()->with('swal', ['type' => 'success', 'html' => $html, 'timer' => 0, 'title' => 'Mot de passe réinitialisé']);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('swal', ['type' => 'error', 'title' => 'Erreur', 'text' => $e->getMessage()]);
        }
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);
        if ($user->id === auth()->id()) {
            return back()->with('swal', ['type' => 'error', 'title' => 'Action impossible', 'text' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }
        try {
            $user->update(['is_active' => ! $user->is_active]);

            return back()->with('swal', $user->is_active
                ? ['type' => 'success', 'timer' => 2000, 'title' => 'Compte activé ✅', 'text' => "{$user->nomComplet} peut maintenant se connecter."]
                : ['type' => 'warning', 'timer' => 2200, 'title' => 'Compte désactivé ⛔', 'text' => "{$user->nomComplet} ne peut plus se connecter (Login : « Compte désactivé »)."]
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with('swal', ['type' => 'error', 'title' => 'Erreur', 'text' => $e->getMessage()]);
        }
    }
}
