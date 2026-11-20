<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveAccountRequestRequest;
use App\Http\Requests\RejectAccountRequestRequest;
use App\Http\Controllers\UsersController;
use App\Models\Journaliste;
use App\Models\User;
use App\Models\UserAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAccountRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', UserAccountRequest::class);

        $status = $request->string('status')->toString() ?: UserAccountRequest::STATUS_PENDING;
        $allowed = ['all', UserAccountRequest::STATUS_PENDING, UserAccountRequest::STATUS_APPROVED, UserAccountRequest::STATUS_REJECTED];
        if (! in_array($status, $allowed, true)) {
            $status = UserAccountRequest::STATUS_PENDING;
        }

        $requests = UserAccountRequest::query()
            ->with(['journaliste', 'reviewer', 'createdUser'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('account-requests.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => [
                'pending' => UserAccountRequest::where('status', UserAccountRequest::STATUS_PENDING)->count(),
                'approved' => UserAccountRequest::where('status', UserAccountRequest::STATUS_APPROVED)->count(),
                'rejected' => UserAccountRequest::where('status', UserAccountRequest::STATUS_REJECTED)->count(),
            ],
        ]);
    }

    public function show(UserAccountRequest $user_account_request): View
    {
        $this->authorize('view', $user_account_request);

        $journalistesDisponibles = Journaliste::query()
            ->where('is_active', true)
            ->where(function ($q) use ($user_account_request) {
                $q->whereNull('user_id');
                if ($user_account_request->journaliste_id) {
                    $q->orWhere('id', $user_account_request->journaliste_id);
                }
            })
            ->whereNotIn('id', User::query()->whereNotNull('journaliste_id')->select('journaliste_id')
                ->when($user_account_request->journaliste_id, fn ($q) => $q->where('journaliste_id', '!=', $user_account_request->journaliste_id)))
            ->orderBy('nom')
            ->get(['id', 'matricule', 'civilite', 'nom', 'prenom', 'fonction', 'email', 'telephone']);

        return view('account-requests.show', [
            'accountRequest' => $user_account_request->load(['journaliste', 'reviewer', 'createdUser']),
            'roles' => UsersController::rolesAssignableTo(auth()->user()),
            'journalistes' => $journalistesDisponibles,
            'tempPasswordDefault' => UsersController::genererMotDePasseTemporaire(),
        ]);
    }

    public function approve(ApproveAccountRequestRequest $request, UserAccountRequest $user_account_request): RedirectResponse
    {
        $accountRequest = $user_account_request;
        if (! $accountRequest->isPending()) {
            return back()->with('swal', ['type' => 'warning', 'title' => 'Demande déjà traitée', 'text' => 'Cette demande a déjà été validée ou refusée.']);
        }

        $valid = $request->validated();
        $roleName = $valid['role'];
        $roleNames = array_values(array_unique(array_merge([$roleName], $valid['roles'] ?? [])));

        try {
            $result = DB::transaction(function () use ($valid, $accountRequest, $roleName, $roleNames) {
                // recheck pending with lock
                $locked = UserAccountRequest::query()->lockForUpdate()->findOrFail($accountRequest->id);
                if (! $locked->isPending()) {
                    throw ValidationException::withMessages(['status' => 'Cette demande a déjà été traitée.']);
                }

                $tempPassword = ($valid['temp_password'] ?? null) ?: UsersController::genererMotDePasseTemporaire();
                $journaliste = null;
                if (! empty($valid['journaliste_id'])) {
                    $journaliste = Journaliste::query()->lockForUpdate()->findOrFail((int) $valid['journaliste_id']);
                    if ($journaliste->user_id !== null && $journaliste->user_id !== $locked->created_user_id) {
                        throw ValidationException::withMessages(['journaliste_id' => 'Ce journaliste possède déjà un compte utilisateur.']);
                    }
                }

                $user = User::create([
                    'name' => trim(($valid['prenom'] ?? '').' '.($valid['nom'] ?? '')),
                    'email' => $valid['email'],
                    'phone' => $valid['phone'] ?? null,
                    'password' => Hash::make($tempPassword),
                    'role' => $roleName,
                    'department' => $valid['department'] ?? null,
                    'is_active' => array_key_exists('is_active', $valid) ? (bool) $valid['is_active'] : true,
                    'email_verified_at' => now(),
                    'password_changed_at' => null,
                    'journaliste_id' => $journaliste?->id,
                    'created_by' => auth()->id(),
                ]);
                $user->syncRoles($roleNames);

                if ($journaliste) {
                    $journaliste->update(['user_id' => $user->id, 'email' => $user->email]);
                }

                $locked->update([
                    'prenom' => $valid['prenom'],
                    'nom' => $valid['nom'],
                    'email' => $valid['email'],
                    'phone' => $valid['phone'] ?? null,
                    'department' => $valid['department'] ?? null,
                    'fonction' => $valid['fonction'],
                    'journaliste_id' => $journaliste?->id,
                    'status' => UserAccountRequest::STATUS_APPROVED,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'created_user_id' => $user->id,
                    'rejection_reason' => null,
                ]);

                return ['user' => $user, 'temp_password' => $tempPassword];
            });

            $u = $result['user'];
            $tempPass = e($result['temp_password']);
            $nom = e($u->nomComplet);
            $email = e($u->email);
            $html = <<<HTML
<div class="text-left">
    <p class="mb-3 font-semibold text-on-surface">✅ Demande validée et compte créé pour <strong>{$nom}</strong>.</p>
    <div class="bg-surface-container-high/60 rounded-xl p-4 space-y-2 text-sm border border-outline-variant/50">
        <div class="flex gap-2"><span class="text-on-surface-variant w-32">📧 Email :</span><span class="font-semibold text-on-surface">{$email}</span></div>
        <div class="flex gap-2 items-start">
            <span class="text-on-surface-variant w-32 pt-1.5">🔑 Mot de passe temporaire :</span>
            <span class="font-mono font-bold bg-[#0A2463] text-white px-3 py-1.5 rounded-lg tracking-wider">{$tempPass}</span>
            <button type="button" onclick="navigator.clipboard.writeText('{$tempPass}'); if(window.TvAlert){TvAlert.success('Copié', {timer:1200});}" class="ml-1 px-2.5 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high transition text-on-surface-variant">📋 Copier</button>
        </div>
        <p class="text-xs text-on-surface-variant pt-2 border-t border-outline-variant/40 mt-1.5">⚠️ Communiquez ce mot de passe. Il devra être changé à la 1ère connexion.</p>
    </div>
</div>
HTML;

            return redirect()->route('admin.account-requests.index')->with('swal', [
                'type' => 'success',
                'html' => $html,
                'timer' => 0,
                'title' => 'Compte créé',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('swal', [
                'type' => 'error',
                'title' => 'Validation impossible',
                'text' => $e->getMessage(),
            ]);
        }
    }

    public function reject(RejectAccountRequestRequest $request, UserAccountRequest $user_account_request): RedirectResponse
    {
        $accountRequest = $user_account_request;
        if (! $accountRequest->isPending()) {
            return back()->with('swal', ['type' => 'warning', 'title' => 'Demande déjà traitée']);
        }

        $accountRequest->update([
            'status' => UserAccountRequest::STATUS_REJECTED,
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.account-requests.index')->with('swal', [
            'type' => 'success',
            'timer' => 2200,
            'title' => 'Demande refusée',
            'text' => 'Le motif a été enregistré.',
        ]);
    }
}
