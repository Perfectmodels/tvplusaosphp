<?php

namespace App\Http\Controllers;

use App\Models\InscriptionPersonnel;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PersonnelInscriptionController extends Controller
{
    /** Affiche le formulaire d'inscription du personnel. */
    public function create(): View
    {
        return view('auth.inscription-personnel');
    }

    /** Recherche de profils existants par nom (pour éviter les doublons). */
    public function searchProfiles(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->input('q', '')), 0, 100);

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.addcslashes($q, '\\%_').'%';

        $journalistes = Journaliste::query()
            ->where(function ($w) use ($like) {
                $w->where('prenom', 'like', $like)
                    ->orWhere('nom', 'like', $like)
                    ->orWhereRaw("CONCAT(prenom, ' ', nom) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT(nom, ' ', prenom) LIKE ?", [$like]);
            })
            ->orderBy('nom')
            ->limit(8)
            ->get(['id', 'civilite', 'prenom', 'nom']);

        $results = $journalistes->map(function (Journaliste $j): array {
            return [
                'id' => $j->id,
                'civilite' => $j->civilite?->value,
                'prenom' => $j->prenom,
                'nom' => $j->nom,
                'label' => $j->nomComplet,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /** Enregistre une demande d'inscription (pas de création de compte). */
    public function store(Request $request): RedirectResponse
    {
        $key = 'inscription-personnel:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', 'Trop de tentatives. Réessayez dans quelques minutes.');
        }
        RateLimiter::hit($key, 300);

        $valid = $request->validate([
            'civilite' => ['nullable', 'string', 'max:8'],
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:200', 'unique:users,email', 'unique:inscription_personnel,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:100'],
            'fonction' => ['nullable', 'string', 'max:100'],
            'motivation' => ['nullable', 'string', 'max:1000'],
            'journaliste_id' => ['nullable', 'integer', 'exists:journalistes,id'],
        ], [
            'prenom.required' => 'Le prénom est obligatoire.',
            'nom.required' => 'Le nom est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail est invalide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée ou une demande est déjà en cours.',
        ]);

        // Vérifier si un compte utilisateur existe déjà pour cet e-mail
        $userExistant = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($valid['email'])])
            ->first();

        if ($userExistant) {
            return back()->withInput()
                ->with('error', 'Un compte utilisateur existe déjà pour cette adresse e-mail. Contactez l\'administrateur si vous n\'avez pas reçu vos identifiants.');
        }

        // Vérifier si un profil journaliste existe déjà pour cet e-mail
        $journalisteExistant = Journaliste::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($valid['email'])])
            ->first();

        if ($journalisteExistant && $journalisteExistant->user_id) {
            return back()->withInput()
                ->with('error', 'Un compte utilisateur existe déjà pour cette adresse e-mail. Contactez l\'administrateur si vous n\'avez pas reçu vos identifiants.');
        }

        // Si un profil journaliste existe (même avec un e-mail différent), on le note
        // pour que l'admin puisse le réutiliser lors de l'approbation
        $inscription = InscriptionPersonnel::create([
            'civilite' => $valid['civilite'] ?? null,
            'prenom' => $valid['prenom'],
            'nom' => $valid['nom'],
            'email' => $valid['email'],
            'telephone' => $valid['telephone'] ?? null,
            'department' => $valid['department'] ?? null,
            'fonction' => $valid['fonction'] ?? null,
            'motivation' => $valid['motivation'] ?? null,
            'statut' => InscriptionPersonnel::STATUT_PENDING,
        ]);

        // Si un journaliste_id est fourni (profil existant sélectionné), on le stocke dans les notes
        if (! empty($valid['journaliste_id'])) {
            $journaliste = Journaliste::find($valid['journaliste_id']);
            if ($journaliste) {
                $inscription->update([
                    'motivation' => ($inscription->motivation ? $inscription->motivation."\n\n" : '')
                        .'[Profil existant sélectionné: '.$journaliste->prenom.' '.$journaliste->nom.' (id='.$journaliste->id.') - e-mail original: '.($journaliste->email ?? 'N/A').']',
                ]);
            }
        }

        return redirect()->route('login')
            ->with('status', 'Votre demande d\'inscription a été transmise. Un administrateur va l\'examiner et vous recevrez vos identifiants par e-mail.');
    }
}
