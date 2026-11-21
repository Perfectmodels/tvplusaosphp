<?php

namespace App\Http\Controllers;

use App\Enums\StatutConducteurEnum;
use App\Enums\StatutPresentationSequenceEnum;
use App\Enums\TypeSequenceEnum;
use App\Events\ConducteurChanged;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Services\ConducteurEditionService;
use App\Services\ConducteurAntenneService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ConducteurPresentateurController extends Controller
{
    public function index(Request $request, ConducteurEditionService $editionService)
    {
        Gate::authorize('viewAny', Conducteur::class);

        return view('conducteur_presentateur_index', $editionService->index(Conducteur::query()->whereIn('type', ['presentateur', 'presentation', 'emission']), $request));
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Conducteur::class);
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $initialDateEdition = $validated['date'] ?? today()->toDateString();

        $emissions = Emission::query()
            ->orderByDesc('is_active')
            ->orderBy('nom')
            ->get(['id', 'nom', 'slug', 'couleur_habillage', 'theme_color', 'is_active']);

        $presentateurs = Intervenant::query()
            ->where('is_active', true)
            ->whereNotNull('fonction')
            ->where(function ($q) {
                $q->whereRaw("LOWER(fonction) LIKE '%present%'")
                    ->orWhereRaw("LOWER(fonction) LIKE '%animate%'");
            })
            ->orderBy('nom')
            ->get(['id', 'civilite', 'prenom', 'nom', 'fonction', 'photo']);

        $presentateursJournalistes = Journaliste::query()
            ->presentateurs()
            ->orderBy('nom')
            ->get(['id', 'civilite', 'prenom', 'nom', 'fonction', 'photo', 'email', 'telephone']);

        return view('conducteur_presentateur_create', compact('emissions', 'presentateurs', 'presentateursJournalistes', 'initialDateEdition'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Conducteur::class);
        $valid = $request->validate([
            'emission_id' => ['required', 'integer', 'exists:emissions,id'],
            'date_edition' => ['required', 'date_format:Y-m-d'],
            'presentateur_id' => ['required', 'string', 'max:30'],
            'target_duration_sec' => ['sometimes', 'nullable', 'integer', 'min:60', 'max:21600'],
            'opening_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $presentateurId = $this->resolvePresentateurId($valid['presentateur_id'] ?? null);
        if ($presentateurId === null) {
            return back()->withInput()->withErrors(['presentateur_id' => 'Veuillez sélectionner un présentateur valide.']);
        }

        $exist = Conducteur::typeEmission()
            ->where('emission_id', (int) $valid['emission_id'])
            ->whereDate('date_edition', $valid['date_edition'])
            ->first();
        if (! $exist) {
            $exist = Conducteur::query()
                ->whereIn('type', ['presentateur', 'presentation'])
                ->where('emission_id', (int) $valid['emission_id'])
                ->whereDate('date_edition', $valid['date_edition'])
                ->first();
        }
        if ($exist) {
            if ($exist->presentateur_id === null && $presentateurId !== null) {
                Gate::authorize('updateEnTete', $exist);
                $exist->update(['presentateur_id' => $presentateurId]);
            }

            return redirect()->route('conducteurs.presentateur.show', ['conducteur' => $exist->id])
                ->with('info', 'Un conducteur existe déjà pour cette édition. Ouvrez sa fiche pour modifier son présentateur.');
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

        return redirect()->route('conducteurs.presentateur.show', ['conducteur' => $conducteur->id])
            ->with('success', 'Conducteur d’émission créé.');
    }

    public function show(Request $request, Conducteur $conducteur)
    {
        abort_if(
            ! in_array((string) $conducteur->type, ['presentateur', 'presentation', 'emission'], true),
            404,
            'Ce conducteur n\'est pas un conducteur d\'émission (Partage Présentateur ↔ Chef Édition).'
        );
        if ((string) $conducteur->type === 'emission') {
            Gate::authorize('view', $conducteur);
        }

        // Vue unique : administration, chef d'édition et présentateur partagent
        // la même interface. Les actions sont filtrées dans la vue par les
        // policies, et le panneau antenne apporte le chrono et les notes.
        return app(ConducteurRedactionController::class)->index($request, $conducteur->id);
    }

    public function interactionMessages(Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('view', $conducteur);
        abort_if((string) $conducteur->type !== 'emission', 404);

        $messages = $conducteur->emissionInteractionMessages()
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'count' => $conducteur->emissionInteractionMessages()->count(),
            'messages' => $messages->map(static fn ($message): array => [
                'id' => (int) $message->id,
                'name' => $message->nom,
                'recipient' => $message->destinataire,
                'message' => $message->message,
                'created_at' => $message->created_at?->toIso8601String(),
                'created_at_label' => $message->created_at?->format('d/m/Y à H:i'),
            ])->values(),
        ]);
    }

    public function liveState(Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('view', $conducteur);

        abort_if(
            ! in_array((string) $conducteur->type, ['presentateur', 'presentation', 'emission'], true),
            404
        );

        // Reconcile elapsed sequences on the server before returning a snapshot.
        app(ConducteurAntenneService::class)->reconcilier($conducteur);
        $conducteur->refresh();
        $sequences = $conducteur->sequences()
            ->with('intervenants')
            ->orderBy('position')
            ->get();

        return response()->json([
            'activeSequenceId' => (int) ($conducteur->active_sequence_id ?? 0),
            'dureeTotaleHM' => (string) $conducteur->total_duration_hm,
            'sequences' => $sequences
                ->map(fn (Sequence $sequence) => self::sequenceForAlpine($sequence))
                ->values()
                ->all(),
            'antenne' => [
                'startedAt' => $conducteur->antenne_started_at?->toIso8601String(),
                'offsetSec' => (int) ($conducteur->antenne_offset_sec ?? 0),
                'endedAt' => $conducteur->antenne_ended_at?->toIso8601String(),
            ],
        ]);
    }

    // =====================================================================
    //  CHRONO ANTENNE — UN SEUL COMPTE À REBOURS POUR TOUTE L'ÉMISSION

    // =====================================================================

    /**
     * Démarre (ou reprend, après une pause) le compte à rebours de l'émission.
     * Un seul départ pour toute l'émission : le chrono décompte ensuite
     * automatiquement les séquences les unes après les autres, dans l'ordre.
     */
    public function demarrerAntenne(Request $request, Conducteur $conducteur): JsonResponse
    {
        $this->autoriserPilotageAntenne($request, $conducteur);

        return DB::transaction(function () use ($conducteur, $request): JsonResponse {
            $conducteur = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);

        if ($conducteur->antenne_started_at !== null && $conducteur->antenne_ended_at === null) {
            return response()->json(array_merge([
                'ok' => true, 'message' => 'Chronomètre déjà en marche.',
            ], self::payloadAntenne($conducteur)));
        }

        $sequences = $conducteur->sequences()->get();
        if ($sequences->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Aucune séquence à décompter.'], 422);
        }

        $offset = max(0, (int) ($conducteur->antenne_offset_sec ?? 0));
        $total = (int) $conducteur->antenne_total_sec;
        $enPause = $conducteur->antenne_started_at === null
            && $conducteur->antenne_ended_at === null
            && $offset > 0
            && $offset < $total;

        try {
            $conducteur->timestamps = false;
            $conducteur->updateQuietly([
                'antenne_started_at' => now(),
                'antenne_ended_at' => null,
                'antenne_offset_sec' => $enPause ? $offset : 0,
                'status' => StatutConducteurEnum::En_direct,
            ]);

            if (! $enPause) {
                // Nouveau départ : tout repart de la première séquence.
                self::appliquerSequenceCourante($conducteur, (int) $sequences->first()->id);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur au démarrage du chrono : '.$e->getMessage(),
            ], 500);
        }

        DB::afterCommit(static fn () => ConducteurChanged::dispatch((int) $conducteur->id));

        return response()->json(array_merge([
            'ok' => true,
            'message' => $enPause ? 'Compte à rebours repris.' : 'Compte à rebours de l\'émission démarré.',
        ], self::payloadAntenne($conducteur->fresh(), $sequences)));
        });
    }

    /** Met le compte à rebours de l'émission en pause (le temps est gelé). */
    public function pauseAntenne(Request $request, Conducteur $conducteur): JsonResponse
    {
        $this->autoriserPilotageAntenne($request, $conducteur);

        return DB::transaction(function () use ($conducteur, $request): JsonResponse {
            $conducteur = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);

        if ($conducteur->antenne_started_at === null) {
            return response()->json(['ok' => false, 'message' => 'Le compte à rebours n\'est pas démarré.'], 422);
        }

        $offset = max(0, (int) ($conducteur->antenne_offset_sec ?? 0))
            + max(0, (int) $conducteur->antenne_started_at->diffInSeconds(now()));

        $conducteur->timestamps = false;
        $conducteur->updateQuietly([
            'antenne_offset_sec' => $offset,
            'antenne_started_at' => null,
        ]);

        DB::afterCommit(static fn () => ConducteurChanged::dispatch((int) $conducteur->id));

        return response()->json(array_merge([
            'ok' => true,
            'message' => 'Compte à rebours en pause.',
        ], self::payloadAntenne($conducteur->fresh())));
        });
    }

    /** Réinitialise le compte à rebours : tout revient à zéro (séquences « À venir »). */
    public function reinitialiserAntenne(Request $request, Conducteur $conducteur): JsonResponse
    {
        $this->autoriserPilotageAntenne($request, $conducteur);

        return DB::transaction(function () use ($conducteur, $request): JsonResponse {
            $conducteur = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);

        $conducteur->timestamps = false;
        $conducteur->updateQuietly([
            'antenne_started_at' => null,
            'antenne_offset_sec' => 0,
            'antenne_ended_at' => null,
            'active_sequence_id' => null,
            'status' => $conducteur->status === StatutConducteurEnum::Cloture
                || $conducteur->status === StatutConducteurEnum::En_direct
                    ? StatutConducteurEnum::Valide
                    : $conducteur->status,
        ]);

        foreach ($conducteur->sequences()->get() as $sequence) {
            $sequence->timestamps = false;
            $sequence->updateQuietly([
                'statut_presentation' => StatutPresentationSequenceEnum::Avenir,
                'presentation_started_at' => null,
                'presentation_ended_at' => null,
            ]);
        }

        DB::afterCommit(static fn () => ConducteurChanged::dispatch((int) $conducteur->id));

        return response()->json(array_merge([
            'ok' => true,
            'message' => 'Compte à rebours réinitialisé.',
        ], self::payloadAntenne($conducteur->fresh())));
        });
    }

    /**
     * Le client signale la séquence courant à l'antenne (bascule automatique)
     * et, le cas échéant, la FIN de l'émission. Persiste l'état pour que le
     * Chef d'Édition et un rechargement de page retrouvent la même progression.
     */
    public function marquerSequenceCourante(Request $request, Conducteur $conducteur): JsonResponse
    {
        $this->autoriserPilotageAntenne($request, $conducteur);

        return DB::transaction(function () use ($conducteur, $request): JsonResponse {
            $conducteur = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);

        $valid = $request->validate([
            'sequence_id' => ['required', 'integer'],
            'fin' => ['sometimes', 'boolean'],
        ]);

        $sequences = $conducteur->sequences()->get();
        $cible = $sequences->firstWhere('id', (int) $valid['sequence_id']);
        if (! $cible) {
            return response()->json(['ok' => false, 'message' => 'Séquence hors conducteur.'], 409);
        }

        self::appliquerSequenceCourante($conducteur, (int) $cible->id);

        // Fin d'émission : on fige le chrono sur la durée totale.
        if (! empty($valid['fin'])) {
            $derniere = $sequences->last();
            if ($derniere) {
                $derniere->timestamps = false;
                $derniere->updateQuietly([
                    'statut_presentation' => StatutPresentationSequenceEnum::Termine,
                    'presentation_ended_at' => $derniere->presentation_ended_at ?? now(),
                ]);
            }
            $conducteur->timestamps = false;
            $conducteur->updateQuietly([
                'antenne_started_at' => null,
                'antenne_offset_sec' => (int) $conducteur->antenne_total_sec,
                'antenne_ended_at' => now(),
                'status' => StatutConducteurEnum::Cloture,
            ]);
        }

        DB::afterCommit(static fn () => ConducteurChanged::dispatch((int) $conducteur->id));

        return response()->json(array_merge([
            'ok' => true,
            'message' => 'Progression antenne mise à jour.',
        ], self::payloadAntenne($conducteur->fresh())));
        });
    }

    /**
     * Marque la séquence cible « En cours » : tout ce qui précède passe
     * « Terminée », tout ce qui suit repasse « À venir ».
     */
    protected static function appliquerSequenceCourante(Conducteur $conducteur, int $sequenceId): void
    {
        $sequences = $conducteur->sequences()->get();
        $cible = $sequences->firstWhere('id', $sequenceId);
        if (! $cible) {
            return;
        }

        foreach ($sequences as $sequence) {
            $sequence->timestamps = false;

            if ((int) $sequence->id === $sequenceId) {
                $sequence->updateQuietly([
                    'statut_presentation' => StatutPresentationSequenceEnum::EnCours,
                    'presentation_started_at' => $sequence->presentation_started_at ?? now(),
                    'presentation_ended_at' => null,
                ]);
            } elseif ((int) $sequence->position < (int) $cible->position) {
                $sequence->updateQuietly([
                    'statut_presentation' => StatutPresentationSequenceEnum::Termine,
                    'presentation_ended_at' => $sequence->presentation_ended_at ?? now(),
                ]);
            } else {
                $sequence->updateQuietly([
                    'statut_presentation' => StatutPresentationSequenceEnum::Avenir,
                    'presentation_started_at' => null,
                    'presentation_ended_at' => null,
                ]);
            }
        }

        $conducteur->timestamps = false;
        $conducteur->updateQuietly(['active_sequence_id' => $sequenceId]);
    }

    /** État complet du chrono émission + statuts des séquences, pour Alpine. */
    protected static function payloadAntenne(Conducteur $conducteur, ?Collection $sequences = null): array
    {
        $sequences ??= $conducteur->sequences()->get();

        return [
            'antenne' => [
                'started_at' => $conducteur->antenne_started_at?->toIso8601String(),
                'ended_at' => $conducteur->antenne_ended_at?->toIso8601String(),
                'offset_sec' => (int) ($conducteur->antenne_offset_sec ?? 0),
                'ecoule_sec' => (int) $conducteur->antenne_ecoule_sec,
                'total_sec' => (int) $conducteur->antenne_total_sec,
                'en_marche' => (bool) $conducteur->antenne_en_marche,
                'demarree' => (bool) $conducteur->antenne_demarree,
                'terminee' => (bool) $conducteur->antenne_terminee,
            ],
            'active_id' => $conducteur->active_sequence_id ? (int) $conducteur->active_sequence_id : null,
            'statuts' => $sequences->map(fn (Sequence $s) => [
                'id' => (int) $s->id,
                'statut_presentation' => is_object($s->statut_presentation)
                    ? $s->statut_presentation->value
                    : (string) $s->statut_presentation,
                'row_presentation_class' => is_object($s->statut_presentation) && method_exists($s->statut_presentation, 'rowClass')
                    ? $s->statut_presentation->rowClass()
                    : '',
            ])->values()->all(),
        ];
    }

    /** Le chrono antenne est réservé au présentateur (et aux chefs). */
    protected function autoriserPilotageAntenne(Request $request, Conducteur $conducteur): void
    {
        if (! $request->ajax() && ! $request->expectsJson()) {
            abort(400);
        }

        Gate::authorize('piloterAntenne', $conducteur);
    }

    public function storeSequence(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('crudSequencePresentateur', Sequence::class);

        if ($conducteur->date_locked_at) {
            return response()->json(['ok' => false, 'message' => 'Conducteur verrouillé (diffusion passé).'], 403);
        }

        $valid = $request->validate([
            'position' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'heure_debut' => ['required', 'string', 'regex:/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/'],
            'heure_fin' => ['required', 'string', 'regex:/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/'],
            'type' => ['required', 'string', Rule::enum(TypeSequenceEnum::class)],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'intervenant_ids' => ['sometimes', 'nullable', 'array'],
            'intervenant_ids.*' => ['integer', 'distinct', 'exists:intervenants,id'],
        ]);

        try {
            $type = TypeSequenceEnum::tryFrom($valid['type']) ?? TypeSequenceEnum::Sujet;
        } catch (\Throwable) {
            $type = TypeSequenceEnum::Sujet;
        }

        // Normaliser HH:MM → HH:MM:SS
        $hdNorm = self::normaliserHM($valid['heure_debut']);
        $hfNorm = self::normaliserHM($valid['heure_fin']);
        $duree = self::diffHeuresEnSecondes($hdNorm, $hfNorm);
        if ($duree === 0) {
            return response()->json(['ok' => false, 'message' => 'Les heures doivent définir une durée non nulle.'], 422);
        }
        if (in_array(mb_strtoupper((string) $type->value), ['DÉBAT', 'DEBAT'], true)) {
            $nbInvites = count($valid['intervenant_ids'] ?? []);
            if ($nbInvites < 2 || $nbInvites > 4) {
                return response()->json(['ok' => false, 'message' => 'Un débat doit avoir entre 2 et 4 intervenants.'], 422);
            }
        }
        $start = self::fusionDateTime($conducteur->date_edition, $hdNorm);
        $end = self::fusionDateTime($conducteur->date_edition, $hfNorm);
        $pos = isset($valid['position']) && (int) $valid['position'] > 0
            ? (int) $valid['position']
            : (int) Sequence::where('conducteur_id', $conducteur->id)->max('position') + 1;

        // Décaler toute séquence existante dont la position >= $pos
        Sequence::where('conducteur_id', $conducteur->id)
            ->where('position', '>=', $pos)
            ->orderBy('position', 'desc')
            ->eachById(function (Sequence $s) {
                $s->timestamps = false;
                $s->updateQuietly(['position' => $s->position + 1]);
            }, 100);

        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'emission_id' => $conducteur->emission_id,
            'title' => $valid['title'],
            'slug' => Str::limit(Str::slug($valid['title']), 80, '').'-'.$pos,
            'type' => $type,
            'position' => $pos,
            'duration_seconds' => $duree,
            'start_time' => $start,
            'end_time' => $end,
            'heure_debut_calculee' => $hdNorm,
            'heure_fin_calculee' => $hfNorm,
            'summary' => $valid['summary'] ?? null,
            'user_last_modif_id' => auth()->id(),
        ]);

        $sequence->refresh();

        // Synchroniser les intervenants si fournis
        if ($request->has('intervenant_ids')) {
            $ids = array_values(array_map('intval', $valid['intervenant_ids'] ?? []));
            $sequence->intervenants()->sync($ids);
        }

        // ⚠️ Le Model::saved déclenche ConducteurCalculatorService::recalculerHeures()
        // qui ÉCRASE heure_debut_calculee / heure_fin_calculee / duration_seconds.
        // On reforce donc les valeurs sources de vérité (CP = 6 cols éditables par l'utilisateur)
        $sequence->timestamps = false;
        $sequence->updateQuietly([
            'heure_debut_calculee' => $hdNorm,
            'heure_fin_calculee' => $hfNorm,
            'duration_seconds' => $duree,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        // Synchroniser les intervenants si fournis
        if ($request->has('intervenant_ids')) {
            $ids = array_values(array_map('intval', $valid['intervenant_ids'] ?? []));
            $sequence->intervenants()->sync($ids);
        }

        $conducteur->touch();
        ConducteurChanged::dispatch((int) $conducteur->id);

        return response()->json([
            'ok' => true,
            'message' => "Séquence #{$sequence->position} créée : ".$sequence->title,
            'sequence' => self::sequenceForAlpine($sequence->fresh()),
        ]);
    }

    public function updateSequence(Request $request, Conducteur $conducteur, Sequence $sequence): JsonResponse
    {
        if ((int) $sequence->conducteur_id !== (int) $conducteur->id) {
            return response()->json(['ok' => false, 'message' => 'Séquence hors conducteur.'], 409);
        }

        Gate::authorize('crudSequencePresentateur', $sequence);

        if ($conducteur->date_locked_at) {
            return response()->json(['ok' => false, 'message' => 'Conducteur verrouillé.'], 403);
        }

        $valid = $request->validate([
            'position' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'heure_debut' => ['required', 'string', 'regex:/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/'],
            'heure_fin' => ['required', 'string', 'regex:/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/'],
            'type' => ['required', 'string', Rule::enum(TypeSequenceEnum::class)],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'intervenant_ids' => ['sometimes', 'nullable', 'array'],
            'intervenant_ids.*' => ['integer', 'distinct', 'exists:intervenants,id'],
        ]);

        try {
            $type = TypeSequenceEnum::tryFrom($valid['type']) ?? $sequence->type ?? TypeSequenceEnum::Sujet;
        } catch (\Throwable) {
            $type = $sequence->type ?? TypeSequenceEnum::Sujet;
        }

        $hdNorm = self::normaliserHM($valid['heure_debut']);
        $hfNorm = self::normaliserHM($valid['heure_fin']);
        $duree = self::diffHeuresEnSecondes($hdNorm, $hfNorm);
        if ($duree === 0) {
            return response()->json(['ok' => false, 'message' => 'Les heures doivent définir une durée non nulle.'], 422);
        }
        if (in_array(mb_strtoupper((string) $type->value), ['DÉBAT', 'DEBAT'], true)) {
            $nbInvites = count($valid['intervenant_ids'] ?? []);
            if ($nbInvites < 2 || $nbInvites > 4) {
                return response()->json(['ok' => false, 'message' => 'Un débat doit avoir entre 2 et 4 intervenants.'], 422);
            }
        }
        $start = self::fusionDateTime($conducteur->date_edition, $hdNorm);
        $end = self::fusionDateTime($conducteur->date_edition, $hfNorm);

        $nouvPos = isset($valid['position']) && (int) $valid['position'] > 0 ? (int) $valid['position'] : null;
        if ($nouvPos !== null && (int) $sequence->position !== $nouvPos) {
            $ancienPos = (int) $sequence->position;
            Sequence::where('conducteur_id', $conducteur->id)
                ->whereKeyNot($sequence->id)
                ->whereBetween('position', $ancienPos < $nouvPos ? [$ancienPos + 1, $nouvPos] : [$nouvPos, $ancienPos - 1])
                ->eachById(function (Sequence $s) use ($ancienPos, $nouvPos) {
                    $s->timestamps = false;
                    $s->updateQuietly(['position' => $ancienPos < $nouvPos ? $s->position - 1 : $s->position + 1]);
                });
            $sequence->timestamps = false;
            $sequence->updateQuietly(['position' => $nouvPos]);
        }

        $sequence->timestamps = true;
        $sequence->update([
            'title' => $valid['title'],
            'type' => $type,
            'duration_seconds' => $duree,
            'start_time' => $start,
            'end_time' => $end,
            'heure_debut_calculee' => $hdNorm,
            'heure_fin_calculee' => $hfNorm,
            'summary' => $valid['summary'] ?? null,
            'user_last_modif_id' => auth()->id(),
        ]);

        $sequence->refresh();

        // ⚠️ Contre-ordre recalculateur automatique (override après Model::saved)
        $sequence->timestamps = false;
        $sequence->updateQuietly([
            'heure_debut_calculee' => $hdNorm,
            'heure_fin_calculee' => $hfNorm,
            'duration_seconds' => $duree,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        // Synchroniser les intervenants si fournis
        if ($request->has('intervenant_ids')) {
            $ids = array_values(array_map('intval', $valid['intervenant_ids'] ?? []));
            $sequence->intervenants()->sync($ids);
        }

        $conducteur->touch();
        ConducteurChanged::dispatch((int) $conducteur->id);

        return response()->json([
            'ok' => true,
            'message' => "Séquence #{$sequence->position} mise à jour.",
            'sequence' => self::sequenceForAlpine($sequence->fresh()),
        ]);
    }

    public function destroySequence(Request $request, Conducteur $conducteur, Sequence $sequence): JsonResponse
    {
        if ((int) $sequence->conducteur_id !== (int) $conducteur->id) {
            return response()->json(['ok' => false, 'message' => 'Séquence hors conducteur.'], 409);
        }

        Gate::authorize('deleteSequencePresentateur', $sequence);

        if ($conducteur->date_locked_at) {
            return response()->json(['ok' => false, 'message' => 'Conducteur verrouillé.'], 403);
        }

        $ancienPos = (int) $sequence->position;
        $label = "#{$ancienPos} — {$sequence->title}";
        // Sequence::deleted already compacts positions and recalculates durations.
        $sequence->delete();

        if ((int) ($conducteur->active_sequence_id ?? 0) === (int) $sequence->id) {
            $conducteur->timestamps = false;
            $conducteur->updateQuietly(['active_sequence_id' => null]);
        }

        ConducteurChanged::dispatch((int) $conducteur->id);

        return response()->json([
            'ok' => true,
            'message' => "Séquence supprimée : {$label}",
            'deletedId' => $sequence->id,
            'activeSequenceId' => $conducteur->active_sequence_id,
        ]);
    }

    public function reorderSequences(Request $request, Conducteur $conducteur): JsonResponse
    {
        Gate::authorize('reorderSequences', $conducteur);
        Gate::authorize('view', $conducteur);
        abort_if($conducteur->date_locked_at !== null, 423, 'Ce conducteur est verrouillé.');
        abort_unless((string) $conducteur->type === 'emission', 404);

        $valid = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $ids = array_map('intval', $valid['ordered_ids']);
        $success = DB::transaction(function () use ($conducteur, $ids): bool {
            Conducteur::query()->whereKey($conducteur->id)->lockForUpdate()->firstOrFail();
            $existing = Sequence::query()->where('conducteur_id', $conducteur->id)
                ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($existing !== collect($ids)->sort()->values()->all()) return false;

            foreach ($ids as $position => $id) {
                Sequence::query()->where('conducteur_id', $conducteur->id)
                    ->whereKey($id)->update(['position' => $position + 1]);
            }

            \App\Services\ConducteurCalculatorService::recalculerHeures($conducteur->fresh());
            return true;
        });
        if (! $success) {
            return response()->json(['ok' => false, 'message' => 'Séquences absentes ou étrangères à ce conducteur.'], 422);
        }

        ConducteurChanged::dispatch((int) $conducteur->id);
        $sequences = $conducteur->sequences()->orderBy('position')->get();

        return response()->json([
            'ok' => true,
            'message' => count($ids).' séquences réordonnées.',
            'ordered_ids' => $ids,
            'sequences' => $sequences->map(fn (Sequence $s) => [
                'id' => $s->id,
                'position' => $s->position,
                'heure_debut_calculee' => $s->heure_debut_calculee,
                'heure_fin_calculee' => $s->heure_fin_calculee,
                'duree_hm' => $s->duree_hm,
            ])->values()->all(),
            'duree_totale_hm' => $conducteur->fresh()->total_duration_hm,
        ]);
    }

    public static function sequenceForAlpine(Sequence $s): array
    {
        $s->loadMissing(['intervenants', 'journalisteResponsable']);
        try {
            $typeLabel = is_object($s->type) && method_exists($s->type, 'label')
                ? $s->type->label()
                : (string) ($s->type?->value ?? 'Sujet');
            $typeBadge = is_object($s->type) && method_exists($s->type, 'badgeClass')
                ? $s->type->badgeClass()
                : 'bg-slate-100 text-slate-700';
            $typeValue = is_object($s->type) ? ($s->type->value ?? null) : $s->type;
        } catch (\Throwable) {
            $typeLabel = (string) $s->type;
            $typeBadge = 'bg-slate-100 text-slate-700';
            $typeValue = $s->type;
        }

        try {
            $rowClass = is_object($s->statut_presentation) && method_exists($s->statut_presentation, 'rowClass')
                ? $s->statut_presentation->rowClass()
                : '';
        } catch (\Throwable) {
            $rowClass = '';
        }

        $intIds = $s->intervenants?->pluck('id')->map(fn ($v) => (int) $v)->values()->all() ?? [];
        $summary = (string) ($s->summary ?? '');

        // Intervenants formatés (concat "Avec X, Y et Z")
        $intervenantsList = collect([]);
        foreach (($s->intervenants ?? collect()) as $i) {
            try {
                $civ = '';
                if (! empty($i->civilite)) {
                    if (is_object($i->civilite) && method_exists($i->civilite, 'label')) {
                        $civ = $i->civilite->label();
                    } elseif (is_object($i->civilite) && isset($i->civilite->value)) {
                        $civ = (string) $i->civilite->value;
                    } else {
                        $civ = (string) $i->civilite;
                    }
                }
                $nomComplet = trim(($civ ? $civ.' ' : '').($i->prenom ?? '').' '.($i->nom ?? ''));
                if ($nomComplet) {
                    $intervenantsList->push($nomComplet);
                }
            } catch (\Throwable) {
                continue;
            }
        }
        if (! $intervenantsList->count() && ! empty($s->nom_prenom_text)) {
            $intervenantsList->push(trim((string) $s->nom_prenom_text));
        }
        $intervenant_soustitre = $intervenantsList->count() ? $intervenantsList->implode(', ') : '';

        // Statut d'invitation agrégé pour les intervenants de cette séquence
        // (sur l'émission du conducteur courant).
        $emissionId = (int) ($s->conducteur?->emission_id ?? 0);
        $statutInvitation = '';
        if ($s->intervenants && $s->intervenants->count()) {
            $parcours = collect();
            foreach ($s->intervenants as $iv) {
                $p = $iv->parcoursIntervenants
                    ? $iv->parcoursIntervenants->firstWhere('emission_id', $emissionId)
                    : null;
                if ($p) {
                    $parcours->push($p);
                }
            }
            if ($parcours->isEmpty()) {
                $statutInvitation = 'non_programme';
            } elseif ($parcours->contains(fn ($p) => (bool) $p->no_show)) {
                $statutInvitation = 'absent';
            } elseif ($parcours->every(fn ($p) => ! empty($p->confirme_at))) {
                $statutInvitation = 'confirmee';
            } else {
                $statutInvitation = 'en_attente';
            }
        }

        // Journaliste responsable
        $jr = $s->journalisteResponsable;
        $journaliste_nom = '';
        if ($jr) {
            try {
                $civ = '';
                if (! empty($jr->civilite)) {
                    $civ = is_object($jr->civilite) && method_exists($jr->civilite, 'label')
                        ? $jr->civilite->label()
                        : (string) ($jr->civilite?->value ?? $jr->civilite);
                }
                $journaliste_nom = trim(($civ ? $civ.' ' : '').($jr->prenom ?? '').' '.($jr->nom ?? ''));
            } catch (\Throwable) {
                $journaliste_nom = '';
            }
        }

        return [
            'id' => (int) $s->id,
            'code_conducteur' => (string) $s->code_conducteur_affichage,
            'code_ligne' => (string) $s->code_ligne,
            'conducteur_id' => (int) $s->conducteur_id,
            'position' => (int) $s->position,
            'heure_debut_calculee' => (string) ($s->heure_debut_calculee ?? self::heureFromCarbon($s->start_time)),
            'heure_fin_calculee' => (string) ($s->heure_fin_calculee ?? self::heureFromCarbon($s->end_time)),
            'heure_debut_hm' => self::hmOf($s->heure_debut_calculee ?? self::heureFromCarbon($s->start_time)),
            'heure_fin_hm' => self::hmOf($s->heure_fin_calculee ?? self::heureFromCarbon($s->end_time)),
            'duree_secondes' => (int) ($s->duration_seconds ?? 0),
            'duree_hm' => (string) ($s->duree_hm ?? self::hmFromSeconds((int) ($s->duration_seconds ?? 0))),
            'type' => $typeValue,
            'type_label' => $typeLabel,
            'type_badge_class' => $typeBadge,
            'title' => (string) $s->title,
            'summary' => $summary,
            'alerte_info' => (bool) ($s->alerte_info ?? false),
            'invitation_statut' => $statutInvitation,
            'journaliste_nom' => $journaliste_nom,
            'notes_preview' => (string) ($s->notes_preview ?? Str::limit(strip_tags((string) ($s->notes_presentateur ?? '')), 160)),
            'notes_presentateur' => (string) ($s->notes_presentateur ?? ''),
            'notes_perso' => Gate::allows('viewNotesPerso', $s)
                ? (string) ($s->notes_perso ?? '')
                : '',
            'angle_editorial' => (string) ($s->angle_editorial ?? ''),
            'journaliste_id' => $s->journaliste_responsable_id ? (int) $s->journaliste_responsable_id : null,
            'intervenant_ids' => $intIds,
            'intervenant_soustitre' => $intervenant_soustitre,
            'statut_presentation' => is_object($s->statut_presentation) ? ($s->statut_presentation->value ?? 'Avenir') : ((string) ($s->statut_presentation ?? 'Avenir')),
            'row_presentation_class' => $rowClass,
            'can_edit_notes' => Gate::allows('updateNotesPresentateur', $s),
            'can_edit_notes_perso' => Gate::allows('updateNotesPerso', $s),
            'can_crud_cp' => Gate::allows('crudSequencePresentateur', $s),
            'can_delete_cp' => Gate::allows('deleteSequencePresentateur', $s),

            // ---- CHRONO ANTENNE (décompte global de l'émission) ----
            // Durée réellement décomptée pour cette séquence (60 s si non renseignée).
            'duree_antenne_sec' => (int) $s->duree_antenne_sec,
        ];
    }

    public static function normaliserHM(string $hm): string
    {
        $hm = trim($hm);
        if ($hm === '') {
            return '00:00:00';
        }
        if (substr_count($hm, ':') === 1) {
            $hm .= ':00';
        }
        if (substr_count($hm, ':') === 0) {
            $hm .= ':00:00';
        }
        $parts = explode(':', $hm);
        $h = min(23, max(0, (int) ($parts[0] ?? 0)));
        $m = min(59, max(0, (int) ($parts[1] ?? 0)));
        $s = min(59, max(0, (int) ($parts[2] ?? 0)));

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    private static function diffHeuresEnSecondes(string $debut, string $fin): int
    {
        $debut = self::normaliserHM($debut);
        $fin = self::normaliserHM($fin);
        try {
            $d = Carbon::createFromFormat('H:i:s', $debut);
            $f = Carbon::createFromFormat('H:i:s', $fin);
        } catch (\Throwable) {
            return 0;
        }
        if (! $d || ! $f) {
            return 0;
        }
        if ($f->lessThan($d)) {
            $f->addDay();
        }

        return max(0, (int) $d->diffInSeconds($f));
    }

    private static function fusionDateTime($dateEdition, string $hm): ?Carbon
    {
        try {
            if (! $dateEdition instanceof CarbonInterface) {
                $date = \Illuminate\Support\Carbon::parse((string) $dateEdition);
            } else {
                $date = \Illuminate\Support\Carbon::instance($dateEdition);
            }
        } catch (\Throwable) {
            return null;
        }
        if (substr_count($hm, ':') === 1) {
            $hm .= ':00';
        }
        [$h, $i, $s] = array_pad(explode(':', $hm, 3), 3, 0);

        return $date->copy()->setTime((int) $h, (int) $i, (int) $s);
    }

    private static function heureFromCarbon($c): string
    {
        if (! $c) {
            return '00:00';
        }
        try {
            $carb = $c instanceof CarbonInterface ? $c : \Illuminate\Support\Carbon::parse($c);

            return $carb->format('H:i:s');
        } catch (\Throwable) {
            return '00:00';
        }
    }

    private static function hmOf(?string $hms): string
    {
        if (! $hms) {
            return '--:--';
        }
        if (strpos($hms, ' ') !== false) {
            [$d, $t] = array_pad(explode(' ', $hms, 2), 2, '00:00:00');
            $hms = $t;
        }
        $parts = explode(':', (string) $hms);

        return str_pad((string) ($parts[0] ?? '0'), 2, '0', STR_PAD_LEFT)
            .':'
            .str_pad((string) ($parts[1] ?? '0'), 2, '0', STR_PAD_LEFT);
    }

    private static function hmFromSeconds(int $sec): string
    {
        $sec = max(0, $sec);
        $h = intdiv($sec, 3600);
        $m = intdiv($sec - $h * 3600, 60);
        if ($h > 0) {
            return str_pad((string) $h, 2, '0', STR_PAD_LEFT).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        }

        return '00:'.str_pad((string) $m, 2, '0', STR_PAD_LEFT);
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
