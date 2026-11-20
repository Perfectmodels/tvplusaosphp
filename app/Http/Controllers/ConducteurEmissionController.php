<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatutConducteurEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Services\ConducteurEditionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * CONDUCTEUR D'ÉMISSION (CE) — type='emission'
 * Vue antenne / direct : tableau 6 colonnes + Notes Présentateur.
 * Liste dédiée, strictement séparée du Conducteur de Rédaction (type='redaction').
 */
final class ConducteurEmissionController extends Controller
{
    // ============== INDEX : Liste des conducteurs d'ÉMISSION ==============
    public function index(Request $request, ConducteurEditionService $editionService)
    {
        Gate::authorize('viewAny', Conducteur::class);

        $validated = $request->validate(['emission_id' => ['nullable', 'integer', 'exists:emissions,id']]);
        $selectedEmission = ! empty($validated['emission_id']) ? Emission::findOrFail($validated['emission_id']) : null;
        $editionFilters = $selectedEmission ? ['emission_id' => $selectedEmission->id] : [];
        $query = Conducteur::typeEmission()->when($selectedEmission, fn ($query) => $query->where('emission_id', $selectedEmission->id));

        return view('conducteur_emission_index', [
            ...$editionService->index($query, $request, $editionFilters),
            'selectedEmission' => $selectedEmission,
        ]);
    }

    // ============== CREATE : Nouveau conducteur d'ÉMISSION ==============
    public function create(Request $request): View
    {
        Gate::authorize('create', Conducteur::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'emission_id' => ['nullable', 'integer', 'exists:emissions,id'],
        ]);
        $initialDateEdition = $validated['date'] ?? today()->toDateString();
        $selectedEmissionId = isset($validated['emission_id']) ? (int) $validated['emission_id'] : null;

        $emissions = Emission::query()
            ->orderByDesc('is_active')
            ->orderBy('nom')
            ->get(['id', 'nom', 'slug', 'couleur_habillage', 'theme_color', 'heure_debut', 'is_active']);

        $presentateurs = Intervenant::query()
            ->where('is_active', true)
            ->whereNotNull('fonction')
            ->where(function ($q) {
                $q->whereRaw('LOWER(fonction) LIKE ?', ['%present%'])
                    ->orWhereRaw('LOWER(fonction) LIKE ?', ['%animate%']);
            })
            ->orderBy('nom')
            ->get(['id', 'civilite', 'prenom', 'nom', 'fonction', 'photo']);

        $presentateursJournalistes = Journaliste::query()
            ->presentateurs()
            ->orderBy('nom')
            ->get(['id', 'civilite', 'prenom', 'nom', 'fonction', 'photo', 'email', 'telephone']);

        return view('conducteur_emission_create', compact('emissions', 'presentateurs', 'presentateursJournalistes', 'initialDateEdition', 'selectedEmissionId'));
    }

    // ============== STORE : Création (1 row partagé Chef Édition ↔ Présentateur) ==============
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Conducteur::class);

        $valid = $request->validate([
            'emission_id' => ['required', 'integer', 'exists:emissions,id'],
            'date_edition' => ['required', 'date_format:Y-m-d'],
            'presentateur_id' => ['sometimes', 'nullable', 'string', 'max:30'],
            'target_duration_sec' => ['sometimes', 'nullable', 'integer', 'min:60', 'max:21600'],
            'opening_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ], [
            'emission_id.required' => 'Veuillez sélectionner une émission.',
            'emission_id.exists' => 'L\'émission sélectionnée n\'existe pas.',
            'date_edition.required' => 'Veuillez indiquer la date d\'édition.',
            'date_edition.date' => 'La date d\'édition est invalide.',
        ]);

        $presentateurId = $this->resolvePresentateurId($valid['presentateur_id'] ?? null);

        // Un seul conducteur d'émission par (émission, date) — règle "1 row partagé"
        $exist = Conducteur::typeEmission()
            ->where('emission_id', (int) $valid['emission_id'])
            ->whereDate('date_edition', $valid['date_edition'])
            ->first();

        if ($exist) {
            if ($exist->presentateur_id === null && $presentateurId !== null) {
                Gate::authorize('updateEnTete', $exist);
                $exist->update(['presentateur_id' => $presentateurId]);
            }

            return redirect()->route('conducteurs.emission.show', ['conducteur' => $exist->id])
                ->with('info', 'Un conducteur d\'émission existe déjà pour cette émission à cette date.');
        }

        $conducteur = Conducteur::create([
            'emission_id' => (int) $valid['emission_id'],
            'date_edition' => $valid['date_edition'],
            'presentateur_id' => $presentateurId,
            'owner_id' => auth()->id(),
            'type' => 'emission',
            'status' => StatutConducteurEnum::Brouillon,
            'active_version' => true,
            'target_duration_sec' => isset($valid['target_duration_sec']) ? (int) $valid['target_duration_sec'] : 915,
            'opening_text' => $valid['opening_text'] ?? null,
        ]);

        return redirect()->route('conducteurs.emission.show', ['conducteur' => $conducteur->id])
            ->with('success', 'Conducteur d\'émission '.$conducteur->code_conducteur.' créé.');
    }

    // ============== SHOW : Tableau 6 colonnes + Notes Présentateur ==============
    public function updatePresentateur(Request $request, Conducteur $conducteur): RedirectResponse
    {
        abort_if((string) $conducteur->type !== 'emission', 404, 'Ce conducteur n\'est pas un conducteur d\'Émission.');

        Gate::authorize('updateEnTete', $conducteur);

        $valid = $request->validate([
            'presentateur_id' => ['nullable', 'string', 'max:30'],
        ]);

        $conducteur->update([
            'presentateur_id' => $this->resolvePresentateurId($valid['presentateur_id'] ?? null),
        ]);

        // Redirection explicite avec un cache-buster : évite qu'un cache edge
        // renvoie l'ancien sélecteur après la mise à jour.
        return redirect()->route('conducteurs.chef_edition', [
            'conducteurId' => $conducteur->id,
            'maj' => now()->timestamp,
        ])->with('success', 'Présentateur mis à jour.');
    }

    public function show(Request $request, Conducteur $conducteur)
    {
        abort_if((string) $conducteur->type !== 'emission', 404, 'Ce conducteur n\'est pas un conducteur d\'Émission.');

        // Réutilise la vue CE (6 colonnes + Notes Présentateur) portée par ConducteurPresentateurController
        return app(ConducteurPresentateurController::class)->show($request, $conducteur);
    }

    // ============== DESTROY : Suppression en cascade ==============
    public function destroy(Request $request, Conducteur $conducteur): JsonResponse
    {
        abort_if((string) $conducteur->type !== 'emission', 404, 'Ce conducteur n\'est pas un conducteur d\'Émission.');

        Gate::authorize('delete', $conducteur);

        $conducteurId = (int) $conducteur->id;
        $codeConducteur = (string) $conducteur->code_conducteur_affichage;
        $nomConducteur = trim((string) ($conducteur->nom ?? '')) !== ''
            ? $conducteur->nom
            : (optional($conducteur->emission)->nom ?? 'Conducteur #'.$conducteurId);
        $dateEdition = $conducteur->date_edition?->format('d/m/Y') ?? '';
        $nbSequences = (int) $conducteur->sequencesAvecMasques()->count();

        DB::beginTransaction();
        try {
            $conducteur->sequencesAvecMasques()->delete();
            $conducteur->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Echec suppression conducteur d\'émission #'.$conducteurId.' : '.$e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'Erreur technique lors de la suppression. Merci de réessayer.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'redirect' => route('conducteurs.emission.index'),
            'message' => "Conducteur {$codeConducteur} « {$nomConducteur} » du {$dateEdition} supprimé ({$nbSequences} séquence(s) effacée(s)).",
            'nb_supprimes' => $nbSequences,
        ]);
    }

    private function resolvePresentateurId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = (string) $value;

        if (str_starts_with($raw, 'j:')) {
            $journalisteId = (int) substr($raw, 2);
            if ($journalisteId <= 0) {
                return null;
            }

            $journaliste = Journaliste::query()->find($journalisteId);
            if (! $journaliste) {
                return null;
            }

            $existing = null;
            $email = trim((string) ($journaliste->email ?? ''));
            if ($email !== '') {
                $existing = Intervenant::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
            }
            if (! $existing) {
                $existing = Intervenant::query()
                    ->whereRaw('LOWER(COALESCE(prenom, \'\')) = ?', [mb_strtolower(trim((string) ($journaliste->prenom ?? '')))])
                    ->whereRaw('LOWER(COALESCE(nom, \'\')) = ?', [mb_strtolower(trim((string) ($journaliste->nom ?? '')))])
                    ->where(function ($q) {
                        $q->whereRaw('LOWER(COALESCE(fonction, \'\')) LIKE ?', ['%present%'])
                            ->orWhereRaw('LOWER(COALESCE(fonction, \'\')) LIKE ?', ['%animate%']);
                    })
                    ->first();
            }

            if ($existing) {
                return (int) $existing->id;
            }

            $intervenant = Intervenant::create([
                'civilite' => $journaliste->civilite?->value ?? $journaliste->civilite,
                'prenom' => $journaliste->prenom,
                'nom' => $journaliste->nom,
                'fonction' => 'Présentateur',
                'telephone_1' => $journaliste->telephone,
                'email' => $journaliste->email ?: null,
                'photo' => $journaliste->photo,
                'is_active' => true,
            ]);

            return (int) $intervenant->id;
        }

        if (! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        return Intervenant::query()->whereKey($id)->exists() ? $id : null;
    }
}
