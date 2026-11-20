<?php

namespace App\Http\Controllers;

use App\Enums\StatutConducteurEnum;
use App\Enums\TypeSequenceEnum;
use App\Events\ConducteurChanged;
use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Emission;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use App\Services\ConducteurCalculatorService;
use App\Services\ConducteurEditionService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * CONDUCTEUR RÉDACTION (VRAI) — Suivi de production éditoriale
 * 11 cols: Sujet · Angle · Émission/Édition · Type · Journaliste/JRI · Terrain · Montage · Habillage · Livraison · Livré le · Diffusion
 * Approche A: Réutilise Model Sequence (pas de nouveau modèle)
 */
final class ConducteurRedactionVraiController extends Controller
{
    // ============== INDEX : Liste cartes conducteurs ==============
    public function index(Request $request, ConducteurEditionService $editionService)
    {
        Gate::authorize('viewAny', Conducteur::class);

        return view('conducteur_redaction_index', $editionService->index(Conducteur::redaction(), $request));
    }

    // ============== CREATE : Nouveau conducteur (comme presentateur mais sans présentateur requis) ==============
    public function create(Request $request)
    {
        Gate::authorize('create', Conducteur::class);
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $initialDateEdition = $validated['date'] ?? today()->toDateString();

        // Journalistes actifs → sélecteur de présences à la réunion de rédaction
        $journalistes = $this->journalistesPourSelecteur();

        // Dénominateur du ratio « X / N journalistes présents »
        $totalJournalistes = $this->totalJournalistesActifs();

        return view('conducteur_redaction_create', compact('journalistes', 'totalJournalistes', 'initialDateEdition'));
    }

    /**
     * Nombre total de journalistes du vivier actif — sert de dénominateur
     * au ratio « présents / total » affiché à la définition du conducteur.
     */
    private function totalJournalistesActifs(): int
    {
        return (int) Journaliste::query()->where('is_active', true)->count();
    }

    /**
     * Liste des journalistes actifs, formatée pour les sélecteurs (présences).
     * Chaque entrée : { id, nom_complet, fonction, pole }.
     */
    private function journalistesPourSelecteur()
    {
        return Journaliste::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->get(['id', 'civilite', 'prenom', 'nom', 'fonction', 'pole'])
            ->map(function ($j) {
                $civ = '';
                try {
                    $civ = is_object($j->civilite) && method_exists($j->civilite, 'label')
                        ? $j->civilite->label()
                        : (string) ($j->civilite?->value ?? $j->civilite ?? '');
                } catch (\Throwable) {
                    $civ = '';
                }

                $fonction = '';
                try {
                    $f = $j->fonction;
                    $fonction = is_object($f) && method_exists($f, 'label') ? $f->label() : (string) ($f?->value ?? $f ?? '');
                } catch (\Throwable) {
                    $fonction = '';
                }

                $pole = '';
                try {
                    $p = $j->pole;
                    $pole = is_object($p) && method_exists($p, 'label') ? $p->label() : (string) ($p?->value ?? $p ?? '');
                } catch (\Throwable) {
                    $pole = '';
                }

                return [
                    'id' => (int) $j->id,
                    'nom_complet' => trim($civ.' '.$j->prenom.' '.$j->nom) ?: ('Journaliste #'.$j->id),
                    'fonction' => $fonction !== '' ? $fonction : '—',
                    'pole' => $pole !== '' ? $pole : '—',
                ];
            })
            ->values();
    }

    public function storeConducteur(Request $request): RedirectResponse
    {
        Gate::authorize('create', Conducteur::class);
        $valid = $request->validate([
            'nom' => ['required', 'string', 'min:3', 'max:255'],
            'date_edition' => ['required', 'date_format:Y-m-d'],
            // Présences à la réunion de rédaction (personnes INTERNES uniquement)
            'presences' => ['sometimes', 'array', 'max:60'],
            'presences.*.journaliste_id' => ['required_with:presences', 'integer', 'exists:journalistes,id', 'distinct'],
            'presences.*.arrivee_at' => ['required_with:presences', 'date'],
        ], [
            'nom.required' => 'Veuillez donner un nom au conducteur.',
            'nom.min' => 'Le nom du conducteur doit faire au moins 3 caractères.',
            'date_edition.required' => 'Veuillez indiquer la date d\'édition.',
            'presences.*.journaliste_id.exists' => 'Une des personnes sélectionnées n\'existe pas.',
            'presences.*.arrivee_at.date' => 'Une des heures d\'arrivée est invalide.',
        ]);

        $exist = Conducteur::redaction()
            ->where('nom', $valid['nom'])
            ->whereDate('date_edition', $valid['date_edition'])
            ->first();
        if ($exist) {
            return redirect()->route('conducteurs.redaction.show', ['conducteur' => $exist->id])
                ->with('info', 'Conducteur de rédaction déjà existant avec ce nom et cette date.');
        }

        [$conducteur, $nbPresences] = DB::transaction(function () use ($valid): array {
            $conducteur = Conducteur::create([
                'nom' => $valid['nom'],
                'date_edition' => $valid['date_edition'],
                'owner_id' => auth()->id(),
                'type' => 'redaction',
                'status' => StatutConducteurEnum::Brouillon,
                'active_version' => true,
            ]);

            // --- Enregistrement des présences à la réunion de rédaction ---
            $nbPresences = 0;
            if (! empty($valid['presences']) && is_array($valid['presences'])) {
                $vus = [];
                foreach ($valid['presences'] as $ligne) {
                    $jId = (int) ($ligne['journaliste_id'] ?? 0);
                    if ($jId <= 0 || isset($vus[$jId])) {
                        continue;
                    }
                    $vus[$jId] = true;

                    ConducteurPresence::create([
                        'conducteur_id' => $conducteur->id,
                        'journaliste_id' => $jId,
                        'arrivee_at' => $ligne['arrivee_at'] ?? now(),
                        'created_by' => auth()->id(),
                    ]);
                    $nbPresences++;
                }
            }

            return [$conducteur, $nbPresences];
        });

        $message = $nbPresences > 0
            ? "Conducteur de Rédaction créé avec {$nbPresences} présence(s). Ajoutez vos sujets de production."
            : 'Conducteur de Rédaction créé. Ajoutez vos sujets de production.';

        return redirect()->route('conducteurs.redaction.show', ['conducteur' => $conducteur->id])
            ->with('success', $message);
    }

    // ============== SHOW : Tableau 11 cols suivi production ==============
    public function show(Request $request, Conducteur $conducteur)
    {
        abort_if((string) $conducteur->type !== 'redaction', 404, 'Ce conducteur n\'est pas un conducteur de Rédaction.');

        $conducteur->loadMissing(['presentateur']);
        $sequences = $conducteur->sequences()
            ->with(['journalisteResponsable.user', 'emission', 'sharedDossier', 'sujetEditorial.sharedDossier'])
            ->orderBy('position')
            ->get();

        $emissions = Emission::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->pluck('nom', 'id');

        // Journalistes actifs formatés pour le sélecteur de présences
        // ({ id, nom_complet, fonction, pole }) — ⚠️ ne pas renvoyer les modèles bruts.
        $journalistes = $this->journalistesPourSelecteur();

        $typeOptions = TypeSequenceEnum::selectOptionsRedaction();
        $progression = (int) ($conducteur->progression_percent ?? 0);
        $user = auth()->user();
        $canCrudRedac = $user ? $user->can('crudSequenceRedaction', Sequence::class) : false;
        $canReorder = $user ? $user->can('reorderSujetsRedaction', $conducteur) : false;

        // --- Présences à la réunion de rédaction (personnes internes) ---
        $presencesModels = $conducteur->presences()->with('journaliste.user')->get();
        $presences = $presencesModels->map(fn (ConducteurPresence $presence) => $presence->pointagePayload())->values()->all();

        // --- Sélecteur JRI : UNIQUEMENT les présents à la réunion de rédaction ---
        // Règle métier : seul un journaliste présent à la réunion peut être assigné à un sujet.
        // (Le panneau Présences, lui, continue de recevoir TOUS les journalistes pour pouvoir
        //  ajouter un retardataire.)
        // Les responsables d'édition peuvent assigner n'importe quel journaliste
        // actif : la règle de présence ne s'applique qu'aux contributeurs.
        if ($this->estResponsableEdition($user)) {
            $jrOptions = Journaliste::query()
                ->where('is_active', true)
                ->with('user')
                ->orderBy('nom')
                ->get()
                ->map(function (Journaliste $j): array {
                    $nom = trim(($j->prenom ?? '').' '.($j->nom ?? '')) ?: ('Journaliste #'.$j->id);
                    $role = $j->user?->role ?? 'journaliste';
                    if ($role === 'journaliste_chef' || $role === 'jri') {
                        $nom .= ' ('.mb_strtoupper($role).')';
                    }
                    $fonction = $j->fonction && is_object($j->fonction) ? ($j->fonction->value ?? '') : (string) ($j->fonction ?? '');

                    return [
                        'value' => (int) $j->id,
                        'label' => $nom,
                        'role' => $role,
                        'fonction' => $fonction,
                    ];
                })->values()->all();
        } else {
            $jrOptions = $presencesModels->map(function ($p) {
                $j = $p->journaliste;
                if (! $j) {
                    return null;
                }
                $nom = trim(($j->prenom ?? '').' '.($j->nom ?? '')) ?: ('Journaliste #'.$j->id);
                $role = $j->user?->role ?? 'journaliste';
                if ($role === 'journaliste_chef' || $role === 'jri') {
                    $nom .= ' ('.mb_strtoupper($role).')';
                }
                $fonction = $j->fonction && is_object($j->fonction) ? ($j->fonction->value ?? '') : (string) ($j->fonction ?? '');

                return [
                    'value' => (int) $j->id,
                    'label' => $nom,
                    'role' => $role,
                    'fonction' => $fonction,
                ];
            })->filter()->values()->all();
        }

        $canGererPresences = $user
            ? $user->can('update', $conducteur)
            : false;

        // Dénominateur du ratio « X / N journalistes présents »
        $totalJournalistes = $this->totalJournalistesActifs();

        $sujets = $sequences->map(function (Sequence $s) use ($user): array {
            return $this->sujetForAlpine($s, $user);
        })->values()->all();

        return view('conducteur_redaction_show', compact(
            'conducteur',
            'sujets',
            'emissions',
            'journalistes',
            'typeOptions',
            'progression',
            'canCrudRedac',
            'canReorder',
            'presences',
            'canGererPresences',
            'jrOptions',
            'totalJournalistes'
        ));
    }

    public function liveState(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('view', $conducteur);

        abort_if((string) $conducteur->type !== 'redaction', 404);

        $sujets = $conducteur->sequences()
            ->with(['journalisteResponsable.user', 'emission', 'sharedDossier', 'sujetEditorial.sharedDossier'])
            ->orderBy('position')
            ->get();

        return response()->json([
            'sujets' => $sujets
                ->map(fn (Sequence $sequence): array => $this->sujetForAlpine($sequence, $request->user()))
                ->values()
                ->all(),
            'progression' => (int) $conducteur->progression_percent,
        ]);
    }

    // ============== CRUD SUJETS (Séquence dans Model, appelé Sujet dans UI Rédaction) ==============

    public function storeSujet(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('crudSequenceRedaction', Sequence::class);

        $valid = $request->validate([
            'position' => ['sometimes', 'integer', 'min:1'],
            'emission_id' => ['sometimes', 'nullable', 'integer', 'exists:emissions,id'],
            'title' => ['required', 'string', 'min:3', 'max:500'],
            'angle_editorial' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type' => ['required', 'string'],
            'journaliste_responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:journalistes,id'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:36000'],
        ]);

        // RÈGLE : seul un journaliste PRÉSENT à la réunion de rédaction est assignable
        $this->assertJournalistePresent($conducteur, isset($valid['journaliste_responsable_id']) ? (int) $valid['journaliste_responsable_id'] : null);

        $pos = (int) ($valid['position'] ?? max(1, $conducteur->sequences()->count() + 1));
        $type = TypeSequenceEnum::tryFrom($valid['type']) ?? TypeSequenceEnum::Sujet;

        // Bulk shift positions >= pos (+1) → insérer à position exacte
        Sequence::where('conducteur_id', $conducteur->id)
            ->where('position', '>=', $pos)
            ->orderBy('position', 'desc')
            ->eachById(function (Sequence $s) {
                $s->timestamps = false;
                $s->updateQuietly(['position' => $s->position + 1]);
            }, 100);

        $duree = (int) ($valid['duration_seconds'] ?? 120);
        $start = $this->fusionDateTime($conducteur->date_edition ?? now(), '18:00');
        $end = $start?->copy()->addSeconds($duree);

        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'emission_id' => isset($valid['emission_id']) ? (int) $valid['emission_id'] : null,
            'title' => $valid['title'],
            'angle_editorial' => $valid['angle_editorial'] ?? null,
            'type' => $type->value,
            'position' => $pos,
            'journaliste_responsable_id' => isset($valid['journaliste_responsable_id']) ? (int) $valid['journaliste_responsable_id'] : null,
            'duration_seconds' => $duree,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        // Override recalculateur (saved hook réécrit les champs heures)
        $hdNorm = $this->normaliserHM($this->hmOf($start));
        $hfNorm = $this->normaliserHM($this->hmOf($end));
        $sequence->timestamps = false;
        $sequence->updateQuietly([
            'heure_debut_calculee' => $hdNorm,
            'heure_fin_calculee' => $hfNorm,
            'duration_seconds' => $duree,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        ConducteurChanged::dispatch((int) $conducteur->id);

        $user = auth()->user();

        return response()->json([
            'ok' => true,
            'message' => "Sujet « {$sequence->title} » ajouté.",
            'sequence' => $this->sujetForAlpine($sequence->fresh(['journalisteResponsable.user', 'emission']), $user),
        ], 201);
    }

    public function updateSujet(Request $request, Conducteur $conducteur, Sequence $sequence): JsonResponse
    {
        Gate::authorize('crudSequenceRedaction', $sequence);

        if ((int) $sequence->conducteur_id !== (int) $conducteur->id) {
            return response()->json(['ok' => false, 'message' => 'Sujet non rattaché à ce conducteur.'], 409);
        }

        $valid = $request->validate([
            'position' => ['sometimes', 'integer', 'min:1'],
            'emission_id' => ['sometimes', 'nullable', 'integer', 'exists:emissions,id'],
            'title' => ['required', 'string', 'min:3', 'max:500'],
            'angle_editorial' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type' => ['required', 'string'],
            'journaliste_responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:journalistes,id'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:36000'],
        ]);

        // RÈGLE : seul un journaliste PRÉSENT à la réunion de rédaction est assignable.
        // Une affectation existante est conservée même si la personne n'est plus présente.
        $this->assertJournalistePresent(
            $conducteur,
            isset($valid['journaliste_responsable_id']) ? (int) $valid['journaliste_responsable_id'] : null,
            'journaliste_responsable_id',
            $sequence->journaliste_responsable_id ? (int) $sequence->journaliste_responsable_id : null
        );

        $type = TypeSequenceEnum::tryFrom($valid['type']) ?? TypeSequenceEnum::Sujet;
        $anciennePos = (int) $sequence->position;
        $nouvellePos = (int) ($valid['position'] ?? $anciennePos);
        $duree = (int) ($valid['duration_seconds'] ?? (int) $sequence->duration_seconds ?? 120);
        $start = $this->fusionDateTime($conducteur->date_edition ?? now(), '18:00');
        $end = $start?->copy()->addSeconds($duree);

        // Bulk shift intervalle positions
        if ($nouvellePos !== $anciennePos) {
            if ($nouvellePos < $anciennePos) {
                Sequence::where('conducteur_id', $conducteur->id)
                    ->where('position', '>=', $nouvellePos)
                    ->where('position', '<', $anciennePos)
                    ->orderBy('position', 'desc')
                    ->eachById(function (Sequence $s) {
                        $s->timestamps = false;
                        $s->updateQuietly(['position' => $s->position + 1]);
                    }, 100);
            } else {
                Sequence::where('conducteur_id', $conducteur->id)
                    ->where('position', '>', $anciennePos)
                    ->where('position', '<=', $nouvellePos)
                    ->orderBy('position')
                    ->eachById(function (Sequence $s) {
                        $s->timestamps = false;
                        $s->updateQuietly(['position' => $s->position - 1]);
                    }, 100);
            }
        }

        $payload = [
            'position' => $nouvellePos,
            'emission_id' => isset($valid['emission_id']) ? (int) $valid['emission_id'] : null,
            'title' => $valid['title'],
            'angle_editorial' => $valid['angle_editorial'] ?? null,
            'type' => $type->value,
            'duration_seconds' => $duree,
        ];

        // Le responsable n'est modifié que s'il est explicitement fourni
        // (sinon on préserve l'affectation existante).
        if (array_key_exists('journaliste_responsable_id', $valid)) {
            $payload['journaliste_responsable_id'] = $valid['journaliste_responsable_id'] !== null
                ? (int) $valid['journaliste_responsable_id']
                : null;
        }

        $sequence->update($payload);

        // Override recalculateur
        $hdNorm = $this->normaliserHM($this->hmOf($start));
        $hfNorm = $this->normaliserHM($this->hmOf($end));
        $sequence->timestamps = false;
        $sequence->updateQuietly([
            'heure_debut_calculee' => $hdNorm,
            'heure_fin_calculee' => $hfNorm,
            'duration_seconds' => $duree,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        ConducteurChanged::dispatch((int) $conducteur->id);

        $user = auth()->user();

        return response()->json([
            'ok' => true,
            'message' => "Sujet « {$sequence->title} » mis à jour.",
            'sequence' => $this->sujetForAlpine($sequence->fresh(['journalisteResponsable.user', 'emission']), $user),
        ]);
    }

    public function destroySujet(Request $request, Conducteur $conducteur, Sequence $sequence): JsonResponse
    {
        Gate::authorize('deleteSequenceRedaction', $sequence);
        if ((int) $sequence->conducteur_id !== (int) $conducteur->id) {
            return response()->json(['ok' => false, 'message' => 'Sujet non rattaché à ce conducteur.'], 409);
        }

        $deletedId = (int) $sequence->id;
        $deletedPos = (int) $sequence->position;
        $titre = $sequence->title;
        $sequence->delete();

        // Bulk compact positions 1..N
        Sequence::where('conducteur_id', $conducteur->id)
            ->where('position', '>', $deletedPos)
            ->orderBy('position')
            ->eachById(function (Sequence $s) {
                $s->timestamps = false;
                $s->updateQuietly(['position' => $s->position - 1]);
            }, 100);

        try {
            ConducteurCalculatorService::recalculerHeures($conducteur->fresh());
        } catch (\Throwable) {
        }

        ConducteurChanged::dispatch((int) $conducteur->id);

        return response()->json([
            'ok' => true,
            'message' => "Sujet « {$titre} » supprimé.",
            'deletedId' => $deletedId,
        ]);
    }

    public function destroyConducteur(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('delete', $conducteur);

        $conducteurId = (int) $conducteur->id;
        $nomConducteur = trim($conducteur->nom ?? '') !== '' ? $conducteur->nom : (optional($conducteur->emission)->nom ?? 'Conducteur #'.$conducteurId);
        $dateEdition = $conducteur->date_edition?->format('d/m/Y') ?? '';
        $nbSujets = (int) $conducteur->sequences()->count();

        \DB::beginTransaction();
        try {
            $conducteur->sequences()->delete();
            $conducteur->delete();
            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('Echec suppression conducteur #'.$conducteurId.' : '.$e->getMessage());

            return response()->json([
                'ok' => false,
                'message' => 'Erreur technique lors de la suppression. Merci de réessayer.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'redirect' => route('conducteurs.redaction.index'),
            'message' => "Conducteur « {$nomConducteur} » du {$dateEdition} supprimé avec succès ({$nbSujets} sujet(s) effacé(s)).",
            'nb_supprimes' => $nbSujets,
        ]);
    }

    public function reorderSujets(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('reorderSujetsRedaction', $conducteur);
        $valid = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);
        $orderedIds = array_values(array_map('intval', $valid['ordered_ids']));
        $position = 1;
        foreach ($orderedIds as $seqId) {
            $seq = Sequence::where('conducteur_id', $conducteur->id)
                ->whereKey($seqId)
                ->first(['id', 'position']);
            if ($seq) {
                $seq->timestamps = false;
                $seq->updateQuietly(['position' => $position]);
            }
            $position++;
        }
        ConducteurChanged::dispatch((int) $conducteur->id);

        try {
            ConducteurCalculatorService::recalculerHeures($conducteur->fresh());
        } catch (\Throwable) {
        }

        return response()->json(['ok' => true, 'message' => 'Ordre mis à jour.']);
    }

    // ================= HELPERS =================
    /**
     * RÈGLE MÉTIER : seul un journaliste PRÉSENT à la réunion de rédaction
     * peut être assigné à un sujet du Conducteur de Rédaction.
     *
     * Exception : une affectation EXISTANTE est conservée telle quelle (on ne
     * force pas la réaffectation si la personne a quitté la liste des présents).
     * Seul un CHANGEMENT vers un non-présent est refusé.
     *
     * @throws ValidationException
     */
    private function estResponsableEdition(?User $user): bool
    {
        return $user?->hasAnyRole(['admin', 'superadmin', 'directeur_general', 'chef_edition', 'chef_redaction']) ?? false;
    }

    private function assertJournalistePresent(
        Conducteur $conducteur,
        ?int $journalisteId,
        string $champ = 'journaliste_responsable_id',
        ?int $actuelId = null
    ): void {
        if ($journalisteId === null) {
            return;
        }

        if ($this->estResponsableEdition(auth()->user())) {
            return;
        }

        // Affectation inchangée → on conserve (règle « garder la valeur »)
        if ($actuelId !== null && $journalisteId === $actuelId) {
            return;
        }

        $present = ConducteurPresence::where('conducteur_id', $conducteur->id)
            ->where('journaliste_id', $journalisteId)
            ->exists();

        if (! $present) {
            throw ValidationException::withMessages([
                $champ => 'Ce journaliste n\'est pas présent à la réunion de rédaction. Ajoutez-le d\'abord dans la liste des présents.',
            ]);
        }
    }

    private function sujetForAlpine(Sequence $s, ?User $user): array
    {
        try {
            $cliqTerrain = $s->isEtapeCliquable('statut_terrain') && $user?->can('updateWorkflow', [$s, 'statut_terrain']);
        } catch (\Throwable) {
            $cliqTerrain = false;
        }
        try {
            $cliqMontage = $s->isEtapeCliquable('statut_montage') && $user?->can('updateWorkflow', [$s, 'statut_montage']);
        } catch (\Throwable) {
            $cliqMontage = false;
        }
        try {
            $cliqHabillage = $s->isEtapeCliquable('statut_habillage') && $user?->can('updateWorkflow', [$s, 'statut_habillage']);
        } catch (\Throwable) {
            $cliqHabillage = false;
        }
        try {
            $cliqLivraison = $s->isEtapeCliquable('statut_livraison') && $user?->can('updateWorkflow', [$s, 'statut_livraison']);
        } catch (\Throwable) {
            $cliqLivraison = false;
        }
        try {
            $cliqDiffusion = $s->isEtapeCliquable('statut_diffusion') && $user?->can('updateWorkflow', [$s, 'statut_diffusion']);
        } catch (\Throwable) {
            $cliqDiffusion = false;
        }

        $jr = $s->journalisteResponsable;
        $jrNom = $jr?->nom_complet ?? '—';
        $jrRole = $jr?->user?->role;
        if ($jr && ($jrRole === 'journaliste_chef' || $jrRole === 'jri')) {
            $jrNom .= ' <small class="opacity-70">('.mb_strtoupper($jrRole).')</small>';
        }

        $emissionNom = $s->relationLoaded('emission')
            ? ($s->emission?->nom ?? '—')
            : ($s->emission()->first(['nom'])?->nom ?? '—');

        return [
            'id' => $s->id,
            'code_conducteur' => (string) $s->code_conducteur_affichage,
            'code_ligne' => (string) $s->code_ligne,
            'position' => (int) $s->position,
            'emission_id' => $s->emission_id ? (int) $s->emission_id : null,
            'emission_nom' => (string) $emissionNom,
            'title' => $s->title,
            'title_truncated' => mb_strlen((string) $s->title) > 80 ? mb_substr($s->title, 0, 77).'…' : $s->title,
            'angle_editorial' => $s->angle_editorial ?? '',
            'angle_truncated' => $s->angle_editorial && mb_strlen((string) $s->angle_editorial) > 100 ? mb_substr($s->angle_editorial, 0, 97).'…' : ($s->angle_editorial ?? ''),
            'emission_edition' => (string) $emissionNom,
            'type_value' => is_object($s->type) ? $s->type->value : (string) $s->type,
            'type_badge_html' => $s->type_badge_html ?? '',
            'journaliste_nom' => $jrNom,
            'journaliste_responsable_id' => $s->journaliste_responsable_id ? (int) $s->journaliste_responsable_id : null,
            'duration_seconds' => (int) ($s->duration_seconds ?? 0),
            'statut_terrain_btn' => $s->workflowBtnHtml('statut_terrain', $cliqTerrain),
            'statut_montage_btn' => $s->workflowBtnHtml('statut_montage', $cliqMontage),
            'statut_habillage_btn' => $s->workflowBtnHtml('statut_habillage', $cliqHabillage),
            'statut_livraison_btn' => $s->workflowBtnHtml('statut_livraison', $cliqLivraison),
            'statut_diffusion_btn' => $s->workflowBtnHtml('statut_diffusion', $cliqDiffusion),
            'shared_dossier_url' => ($dossier = ($s->sujetEditorial?->sharedDossier ?? $s->sharedDossier)) && $user?->can('view', $dossier) ? route('workspace.show', $dossier) : null,
            'can_delete_redaction' => $user?->can('deleteSequenceRedaction', $s) ?? false,
            'can_workflow' => [
                'statut_terrain' => $cliqTerrain,
                'statut_montage' => $cliqMontage,
                'statut_habillage' => $cliqHabillage,
                'statut_livraison' => $cliqLivraison,
                'statut_diffusion' => $cliqDiffusion,
            ],
            'livre_at_hm' => $s->livre_at ? $s->livre_at->format('d/m H:i') : null,
        ];
    }

    // ============ helpers réutilisés depuis ConducteurPresentateur (normaliser heures) ============
    public function normaliserHM(string $hm): string
    {
        $hm = trim($hm);
        if ($hm === '' || $hm === '0') {
            return '00:00:00';
        }
        if (preg_match('#^(\d{1,2}):(\d{1,2})$#', $hm, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.str_pad($m[2], 2, '0', STR_PAD_LEFT).':00';
        }
        if (preg_match('#^(\d{1,2}):(\d{1,2}):(\d{1,2})$#', $hm, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.str_pad($m[2], 2, '0', STR_PAD_LEFT).':'.str_pad($m[3], 2, '0', STR_PAD_LEFT);
        }

        return '00:00:00';
    }

    public function hmOf($carbonOrNull): string
    {
        if (! $carbonOrNull) {
            return '00:00';
        }
        try {
            return $carbonOrNull->format('H:i');
        } catch (\Throwable) {
            return '00:00';
        }
    }

    public function fusionDateTime($dateCarbonOrString, string $hm): ?CarbonInterface
    {
        try {
            $d = is_string($dateCarbonOrString) ? Carbon::parse($dateCarbonOrString) : $dateCarbonOrString;
            if (! $d) {
                return null;
            }
            [$h, $m] = array_pad(explode(':', $this->normaliserHM($hm)), 3, 0);

            return $d->copy()->setTime((int) $h, (int) $m, 0);
        } catch (\Throwable) {
            return null;
        }
    }
}
