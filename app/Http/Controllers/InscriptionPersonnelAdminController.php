<?php

namespace App\Http\Controllers;

use App\Mail\CoordonneesCompteMail;
use App\Models\InscriptionPersonnel;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InscriptionPersonnelAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InscriptionPersonnel::class);

        $statut = $request->input('statut', 'pending');
        $query = InscriptionPersonnel::query()->orderByDesc('created_at');

        if (in_array($statut, ['pending', 'approved', 'rejected'], true)) {
            $query->where('statut', $statut);
        }

        $demandes = $query->paginate(20)->withQueryString();

        $counts = [
            'pending' => InscriptionPersonnel::where('statut', 'pending')->count(),
            'approved' => InscriptionPersonnel::where('statut', 'approved')->count(),
            'rejected' => InscriptionPersonnel::where('statut', 'rejected')->count(),
        ];

        $roles = array_keys(UsersController::rolesAssignableTo($request->user()));

        // Pour chaque demande, indiquer si un profil journaliste ou un compte existe déjà
        $demandes->getCollection()->transform(function ($d) {
            $d->journalisteExistant = Journaliste::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($d->email)])
                ->first();
            $d->utilisateurExistant = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($d->email)])
                ->first();

            return $d;
        });

        return view('users.inscriptions', compact('demandes', 'statut', 'counts', 'roles'));
    }

    public function approve(Request $request, InscriptionPersonnel $inscription): RedirectResponse
    {
        $this->authorize('approve', $inscription);

        if (! $inscription->isPending()) {
            return back()->with('error', 'Cette demande a déjà été traitée.');
        }

        $allowedRoles = array_keys(UsersController::rolesAssignableTo($request->user()));

        $valid = $request->validate([
            'role' => ['required', 'string', 'max:50', Rule::in($allowedRoles)],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        // Vérifier si un compte utilisateur existe déjà pour cet e-mail
        $userExistant = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($inscription->email)])
            ->first();

        if ($userExistant) {
            // Lier la demande au compte existant sans en créer un nouveau
            $inscription->update([
                'statut' => InscriptionPersonnel::STATUT_APPROVED,
                'validated_by' => auth()->id(),
                'validated_at' => now(),
                'user_id' => $userExistant->id,
            ]);

            // Mettre à jour le journaliste lié si nécessaire
            if ($userExistant->journaliste_id) {
                Journaliste::where('id', $userExistant->journaliste_id)->update([
                    'is_active' => true,
                ]);
            }

            return back()->with('success', "Demande de {$inscription->nomComplet} approuvée. Un compte existait déjà ({$userExistant->email}) — aucun doublon créé. Rôle à attribuer manuellement si besoin.");
        }

        try {
            $result = DB::transaction(function () use ($valid, $inscription) {
                $tempPassword = UsersController::genererMotDePasseTemporaire();

                // Réutiliser le journaliste existant s'il y en a un (pas de doublon)
                $journaliste = Journaliste::query()
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($inscription->email)])
                    ->first();

                $journalisteCree = false;
                if (! $journaliste) {
                    $journaliste = Journaliste::create([
                        'civilite' => $inscription->civilite,
                        'prenom' => $inscription->prenom,
                        'nom' => $inscription->nom,
                        'email' => $inscription->email,
                        'telephone' => $inscription->telephone,
                        'pole' => 'actualite',
                        'statut' => 'actif',
                        'is_active' => true,
                        'is_presentateur' => false,
                    ]);
                    $journalisteCree = true;
                }

                if ($journaliste->user_id !== null || User::where('journaliste_id', $journaliste->id)->exists()) {
                    throw new \RuntimeException('Ce profil journaliste possède déjà un compte utilisateur.');
                }

                $name = trim($inscription->prenom.' '.$inscription->nom);
                $data = [
                    'name' => $name,
                    'email' => $inscription->email,
                    'phone' => $inscription->telephone,
                    'password' => Hash::make($tempPassword),
                    'role' => $valid['role'],
                    'department' => $valid['department'] ?? $inscription->department,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password_changed_at' => null,
                    'journaliste_id' => $journaliste->id,
                    'created_by' => auth()->id(),
                ];

                $user = User::create($data);
                $user->syncRoles([$valid['role']]);

                $journaliste->update(['user_id' => $user->id, 'email' => $user->email]);

                $inscription->update([
                    'statut' => InscriptionPersonnel::STATUT_APPROVED,
                    'validated_by' => auth()->id(),
                    'validated_at' => now(),
                    'user_id' => $user->id,
                ]);

                return ['user' => $user, 'temp_password' => $tempPassword, 'journaliste_cree' => $journalisteCree];
            });

            $u = $result['user'];
            $tempPass = e($result['temp_password']);
            $nom = e($u->nomComplet);
            $email = e($u->email);
            $note = $result['journaliste_cree'] ? 'Profil journaliste créé.' : 'Profil journaliste existant réutilisé (pas de doublon).';
            $html = <<<HTML
<div class="text-left">
    <p class="mb-3 font-semibold text-on-surface">✅ Compte créé pour <strong>{$nom}</strong>.</p>
    <p class="text-xs text-on-surface-variant mb-3 italic">{$note}</p>
    <div class="bg-surface-container-high/60 rounded-xl p-4 space-y-2 text-sm border border-outline-variant/50">
        <div class="flex gap-2"><span class="text-on-surface-variant w-32">📧 Email :</span><span class="font-semibold text-on-surface">{$email}</span></div>
        <div class="flex gap-2 items-start">
            <span class="text-on-surface-variant w-32 pt-1.5">🔑 Mot de passe temporaire :</span>
            <span class="font-mono font-bold bg-[#0A2463] text-white px-3 py-1.5 rounded-lg tracking-wider">{$tempPass}</span>
            <button type="button" onclick="navigator.clipboard.writeText('{$tempPass}'); TvAlert.success('Copié : {$tempPass}', {timer: 1400, title:'Presse-papier'});" class="ml-1 px-2.5 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high transition text-on-surface-variant">📋 Copier</button>
        </div>
        <p class="text-xs text-on-surface-variant pt-2 border-t border-outline-variant/40 mt-1.5">⚠️ Communiquer ce mot de passe à l'utilisateur. Il sera <strong>obligé de le changer à sa 1ère connexion</strong>.</p>
    </div>
</div>
HTML;

            if (! CoordonneesCompteMail::envoyer($u, $result['temp_password'], 'inscription approuvée')) {
                $html = $html.'<p class="text-xs text-error mt-2">⚠️ L’envoi de l’e-mail de coordonnées a échoué — les identifiants sont affichés dans cette alerte et devront être communiqués manuellement.</p>';
            }

            return redirect()->route('personnel.inscriptions.index')
                ->with('swal', ['type' => 'success', 'html' => $html, 'timer' => 0, 'title' => 'Compte créé']);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()
                ->with('swal', ['type' => 'error', 'title' => "Erreur création d'utilisateur", 'text' => 'Impossible de créer le compte. Consultez les journaux pour plus de détails.']);
        }
    }

    public function reject(Request $request, InscriptionPersonnel $inscription): RedirectResponse
    {
        $this->authorize('reject', $inscription);

        if (! $inscription->isPending()) {
            return back()->with('error', 'Cette demande a déjà été traitée.');
        }

        $valid = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $inscription->update([
            'statut' => InscriptionPersonnel::STATUT_REJECTED,
            'validated_by' => auth()->id(),
            'validated_at' => now(),
            'rejection_reason' => $valid['rejection_reason'] ?? null,
        ]);

        return back()->with('success', "Demande de {$inscription->nomComplet} rejetée.");
    }
}
