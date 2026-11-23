<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Events\ConducteurChanged;
use App\Models\Conducteur;
use App\Models\Intervenant;
use App\Models\Sequence;
use App\Enums\TypeSequenceEnum;
use App\Services\ConducteurCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SequenceController extends Controller
{
    /**
     * Succès : JSON pour les requêtes AJAX/XHR (le fetch ne suit JAMAIS une
     * redirection → pas de risque de ERR_TOO_MANY_REDIRECTS), redirection
     * back() classique pour un soumission de formulaire navigateur.
     */
    private function succes(Request $request, string $message, int $statut = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message], $statut);
        }

        return back()->with('success', $message);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->normaliserIntervenantIds($request);

        $valid = $request->validate([
            'conducteur_id' => ['required', 'integer', 'exists:conducteurs,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TypeSequenceEnum::class)],
            'duration_minutes' => ['sometimes', 'integer', 'min:0', 'max:600'],
            'duration_seconds' => ['sometimes', 'integer', 'min:0', 'max:36000'],
            'intervenant_ids' => ['sometimes', 'nullable', 'array'],
            'intervenant_ids.*' => ['integer', 'distinct', 'exists:intervenants,id'],
            'nom_prenom_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fonction_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'journaliste_responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:journalistes,id'],
            'note_regie' => ['sometimes', 'nullable', 'string'],
            'angle_editorial' => ['sometimes', 'nullable', 'string'],
            'alerte_info' => ['boolean'],
            'position' => ['sometimes', 'integer', 'min:1'],
        ]);

        $conducteur = Conducteur::findOrFail((int) $valid['conducteur_id']);
        $this->autoriserCreation($conducteur);
        $this->validerDebat($valid['type'], $valid['intervenant_ids'] ?? []);

        $duree = null;
        if (isset($valid['duration_minutes'])) {
            $duree = ((int) $valid['duration_minutes']) * 60 + ((int) ($valid['duration_seconds'] ?? 0));
        } elseif (isset($valid['duration_seconds'])) {
            $duree = (int) $valid['duration_seconds'];
        }

        $this->validerDuree($duree);

        $seq = \Illuminate\Support\Facades\DB::transaction(function () use ($valid, $duree, $conducteur): Sequence {
            // Reserve the requested position under one conductor-level lock.
            $conducteur = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);
            $maxPosition = (int) $conducteur->sequencesAvecMasques()->max('position');
            $position = max(1, min((int) ($valid['position'] ?? ($maxPosition + 1)), $maxPosition + 1));
            Sequence::query()
                ->where('conducteur_id', $conducteur->id)
                ->where('position', '>=', $position)
                ->increment('position');

            $seq = Sequence::create([
                'conducteur_id' => $conducteur->id,
                'title' => $valid['title'],
                'type' => $valid['type'],
                'duration_seconds' => $duree ?? 120,
                'journaliste_responsable_id' => $valid['journaliste_responsable_id'] ?? null,
                'nom_prenom_text' => $valid['nom_prenom_text'] ?? null,
                'fonction_text' => $valid['fonction_text'] ?? null,
                'note_regie' => $valid['note_regie'] ?? null,
                'angle_editorial' => $valid['angle_editorial'] ?? null,
                'alerte_info' => (bool) ($valid['alerte_info'] ?? false),
                'position' => $position,
            ]);

            $ids = array_values(array_map('intval', $valid['intervenant_ids'] ?? []));
            $this->synchroniserIntervenants($seq, $ids);

            if ($ids !== []) {
                $principal = Intervenant::find($ids[0]);
                if ($principal && (empty($seq->nom_prenom_text) || empty($seq->fonction_text))) {
                    $prenomAbrege = $principal->prenom ? mb_substr($principal->prenom, 0, 1).'. ' : '';
                    $seq->updateQuietly([
                        'nom_prenom_text' => $seq->nom_prenom_text ?: trim($prenomAbrege.($principal->nom ?? '')),
                        'fonction_text' => $seq->fonction_text ?: ($principal->fonction ?? ''),
                    ]);
                }
            }

            return $seq;
        });

        ConducteurChanged::dispatch((int) $seq->conducteur_id);

        return $this->succes($request, "Élément « {$seq->title} » ajouté.", 201);
    }

    public function update(Request $request, Sequence $sequence): RedirectResponse|JsonResponse
    {
        $this->autoriserMutation($sequence, false);
        $this->normaliserIntervenantIds($request);

        $valid = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(TypeSequenceEnum::class)],
            'duration_minutes' => ['sometimes', 'integer', 'min:0', 'max:600'],
            'duration_seconds' => ['sometimes', 'integer', 'min:0', 'max:36000'],
            'intervenant_ids' => ['sometimes', 'nullable', 'array'],
            'intervenant_ids.*' => ['integer', 'distinct', 'exists:intervenants,id'],
            'nom_prenom_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fonction_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'journaliste_responsable_id' => ['sometimes', 'nullable', 'integer', 'exists:journalistes,id'],
            'note_regie' => ['sometimes', 'nullable', 'string'],
            'angle_editorial' => ['sometimes', 'nullable', 'string'],
            'alerte_info' => ['sometimes', 'boolean'],
            'masque' => ['sometimes', 'boolean'],
        ]);

        $payload = collect($valid)->except(['duration_minutes', 'duration_seconds', 'intervenant_ids'])->toArray();

        if (isset($valid['duration_minutes'])) {
            $payload['duration_seconds'] = ((int) $valid['duration_minutes']) * 60 + ((int) ($valid['duration_seconds'] ?? 0));
        } elseif (isset($valid['duration_seconds'])) {
            $payload['duration_seconds'] = (int) $valid['duration_seconds'];
        }

        if (array_key_exists('duration_seconds', $payload)) {
            $this->validerDuree((int) $payload['duration_seconds']);
        }

        $sequence = \Illuminate\Support\Facades\DB::transaction(function () use ($sequence, $valid, $payload): Sequence {
            $sequence = Sequence::query()->lockForUpdate()->findOrFail($sequence->id);
            $typeEffectif = $valid['type']
                ?? ($sequence->type instanceof TypeSequenceEnum ? $sequence->type->value : (string) $sequence->type);
            if ($typeEffectif === TypeSequenceEnum::Debat->value) {
                $invitesEffectifs = array_key_exists('intervenant_ids', $valid)
                    ? ($valid['intervenant_ids'] ?? [])
                    : $sequence->intervenants()->pluck('intervenants.id')->all();
                $this->validerDebat($typeEffectif, $invitesEffectifs);
            }

            $sequence->update($payload);
            if (array_key_exists('intervenant_ids', $valid)) {
                $this->synchroniserIntervenants($sequence, array_map('intval', $valid['intervenant_ids'] ?? []));
            }

            return $sequence;
        });

        ConducteurChanged::dispatch((int) $sequence->conducteur_id);

        return $this->succes($request, "Élément « {$sequence->title} » mis à jour.");
    }

    public function destroy(Request $request, Sequence $sequence): RedirectResponse|JsonResponse
    {
        $this->autoriserMutation($sequence, true);
        $titre = $sequence->title;
        $conducteur = $sequence->conducteur;
        try {
            $sequence->intervenants()->detach();
        } catch (\Throwable) {
        }
        $sequence->delete();
        if ($conducteur && (int) $conducteur->active_sequence_id === (int) $sequence->id) {
            $conducteur->updateQuietly(['active_sequence_id' => null]);
        }
        ConducteurChanged::dispatch((int) $sequence->conducteur_id);

        return $this->succes($request, "Élément « {$titre} » supprimé.");
    }

    public function workflowNext(Request $request, Sequence $sequence): JsonResponse
    {
        $valid = $request->validate([
            'etape' => ['required', 'string', 'in:terrain,montage,habillage,livraison,diffusion'],
        ]);

        $mapColonne = [
            'terrain' => 'statut_terrain',
            'montage' => 'statut_montage',
            'habillage' => 'statut_habillage',
            'livraison' => 'statut_livraison',
            'diffusion' => 'statut_diffusion',
        ];

        $colonne = $mapColonne[$valid['etape']];

        $this->authorize('updateWorkflow', [$sequence, $colonne]);

        if (! $sequence->isEtapeCliquable($colonne)) {
            return response()->json([
                'ok' => false,
                'message' => "L'étape {$valid['etape']} n'est pas activable actuellement.",
            ], 409);
        }

        $ok = $sequence->appliquerWorkflowEtapeSuivante($colonne);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'message' => "Erreur interne étape {$valid['etape']}.",
            ], 500);
        }

        // ---------- SET / RESET `livre_at` (auto si statut_livraison TERMINE) ----------
        if ($colonne === 'statut_livraison') {
            $sequence->refresh();
            $termine = $sequence->statut_livraison === StatutEtapeWorkflowEnum::Termine;
            $sequence->timestamps = false;
            $sequence->updateQuietly(['livre_at' => $termine ? now() : null]);
        }

        try {
            ConducteurCalculatorService::recalculerHeures($sequence->conducteur()->first());
        } catch (\Throwable) {
        }

        ConducteurChanged::dispatch((int) $sequence->conducteur_id);

        $seq = $sequence->fresh();
        $livreAtHm = $seq->livre_at ? $seq->livre_at->format('d/m H:i') : null;

        return response()->json([
            'ok' => true,
            'sequence' => [
                'id' => $seq->id,
                'statut_terrain_btn' => $seq->workflowBtnHtml('statut_terrain'),
                'statut_montage_btn' => $seq->workflowBtnHtml('statut_montage'),
                'statut_habillage_btn' => $seq->workflowBtnHtml('statut_habillage'),
                'statut_livraison_btn' => $seq->workflowBtnHtml('statut_livraison'),
                'statut_diffusion_btn' => $seq->workflowBtnHtml('statut_diffusion'),
                'livre_at_hm' => $livreAtHm,
            ],
            'conducteur' => [
                'progression_percent' => $seq->conducteur->fresh()->progression_percent,
                'total_duration_hm' => $seq->conducteur->fresh()->total_duration_hm ?? null,
            ],
        ]);
    }

    public function ajaxPayload(Request $request, Sequence $sequence): JsonResponse
    {
        if (! $request->ajax() && ! $request->expectsJson()) {
            abort(400);
        }

        Gate::authorize('view', $sequence->conducteur);
        if (! Gate::allows('view', $sequence)
            && ! Gate::allows('crudSequencePresentateur', $sequence)
            && ! Gate::allows('crudSequenceRedaction', $sequence)) {
            Gate::authorize('view', $sequence);
        }

        // Trié par position du pivot (orderByPivot) — le 1ᵉʳ est l'invité principal
        $intervenants = $sequence->intervenants;

        return response()->json([
            'id' => $sequence->id,
            'conducteur_id' => $sequence->conducteur_id,
            'position' => (int) $sequence->position,
            'title' => $sequence->title,
            'type_value' => is_object($sequence->type) ? $sequence->type->value : (string) $sequence->type,
            'duration_seconds' => (int) $sequence->duration_seconds,
            'duration_minutes_floor' => (int) floor(((int) $sequence->duration_seconds) / 60),
            'duration_seconds_remain' => ((int) $sequence->duration_seconds) % 60,
            'intervenant_id' => $intervenants->first()?->id,
            'intervenant_ids' => $intervenants->pluck('id')->map(fn ($v) => (int) $v)->values()->all(),
            'intervenants' => $intervenants->map(fn (Intervenant $int) => [
                'id' => (int) $int->id,
                'label' => trim(sprintf('%s %s %s', $this->civiliteEnTexte($int), (string) ($int->prenom ?? ''), (string) ($int->nom ?? ''))),
                'fonction' => (string) ($int->fonction ?? ''),
            ])->values()->all(),
            'nom_prenom_text' => $sequence->nom_prenom_text,
            'fonction_text' => $sequence->fonction_text,
            'journaliste_responsable_id' => $sequence->journaliste_responsable_id,
            'note_regie' => $sequence->note_regie,
            'angle_editorial' => $sequence->angle_editorial,
            'alerte_info' => (bool) $sequence->alerte_info,
            'notes_presentateur' => $sequence->notes_presentateur,
            'statut_presentation' => is_object($sequence->statut_presentation) ? $sequence->statut_presentation->value : (string) $sequence->statut_presentation,
        ]);
    }

    public function updateNotesPresentateur(Request $request, Sequence $sequence): JsonResponse
    {
        if (! $request->ajax() && ! $request->expectsJson()) {
            abort(400);
        }
        $valid = $request->validate([
            'notes_presentateur' => ['nullable', 'string', 'max:65535'],
            'notes_perso' => ['nullable', 'string', 'max:65535'],
        ]);

        Gate::authorize('view', $sequence->conducteur);

        $maj = [];

        // ⚠️ Un champ absent de la requête ne doit JAMAIS effacer la valeur existante
        //    (sinon sauvegarder les notes perso vide les notes présentateur, et vice versa).
        if (array_key_exists('notes_presentateur', $valid)) {
            Gate::authorize('updateNotesPresentateur', $sequence);
            $maj['notes_presentateur'] = $valid['notes_presentateur'];
        }

        // Notes PERSO : champ privé du présentateur (SPEC §5.4) — droit distinct
        if (array_key_exists('notes_perso', $valid)) {
            $this->authorize('updateNotesPerso', $sequence);
            $maj['notes_perso'] = $valid['notes_perso'];
        }

        if ($maj === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Aucune note à enregistrer.',
            ], 422);
        }

        $ok = $sequence->update($maj);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'message' => 'Impossible d\'enregistrer les notes présentateur.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Notes présentateur enregistrées.',
            'notes_preview' => $sequence->notes_preview,
            'notes_perso' => Gate::allows('viewNotesPerso', $sequence) ? (string) ($sequence->notes_perso ?? '') : '',
            'saved_at' => now()->format('H:i:s'),
        ]);
    }

    // =====================================================================
    //  INVITÉS — 1 ou plusieurs intervenants par sujet
    // =====================================================================

    /**
     * Le front envoie `intervenant_ids` sous forme de JSON ("[1,2]" ou "[]")
     * pour que la clé soit TOUJOURS présente en requête : avec le format
     * classique `intervenant_ids[]`, une liste vidée disparaît de la requête
     * PHP et on ne pourrait plus retirer les invités d'un sujet.
     *
     * JSON invalide → la clé reste une chaîne → règle `array` → 422.
     */
    /**
     * The generic sequence endpoints serve both editorial and broadcast conducteurs.
     * Always authorize against the conductor AND the workflow-specific sequence policy.
     */
    private function validerDebat(string $type, array $intervenants): void
    {
        if ($type !== TypeSequenceEnum::Debat->value) {
            return;
        }

        $nombre = count($intervenants);
        if ($nombre < 2 || $nombre > 4) {
            throw ValidationException::withMessages([
                'intervenant_ids' => 'Un débat doit comporter entre 2 et 4 intervenants.',
            ]);
        }
    }

    private function validerDuree(?int $secondes): void
    {
        if ($secondes !== null && ($secondes < 1 || $secondes > 36000)) {
            throw ValidationException::withMessages([
                'duration_minutes' => 'La durée doit être comprise entre 1 seconde et 600 minutes.',
            ]);
        }
    }

    private function autoriserCreation(Conducteur $conducteur): void
    {
        Gate::authorize('view', $conducteur);
        abort_if($conducteur->date_locked_at !== null, 423, 'Ce conducteur est verrouillé.');

        if (in_array((string) $conducteur->type, ['emission', 'chef_edition', 'presentation', 'presentateur'], true)) {
            Gate::authorize('crudSequencePresentateur', Sequence::class);
        } elseif ((string) $conducteur->type === 'redaction') {
            Gate::authorize('crudSequenceRedaction', Sequence::class);
        } else {
            abort(404, 'Type de conducteur non pris en charge.');
        }
    }

    private function autoriserMutation(Sequence $sequence, bool $suppression): void
    {
        $conducteur = $sequence->conducteur;
        abort_unless($conducteur, 404);
        Gate::authorize('view', $conducteur);
        abort_if($conducteur->date_locked_at !== null, 423, 'Ce conducteur est verrouillé.');

        if (in_array((string) $conducteur->type, ['emission', 'chef_edition', 'presentation', 'presentateur'], true)) {
            Gate::authorize($suppression ? 'deleteSequencePresentateur' : 'crudSequencePresentateur', $sequence);
        } elseif ((string) $conducteur->type === 'redaction') {
            Gate::authorize($suppression ? 'deleteSequenceRedaction' : 'crudSequenceRedaction', $sequence);
        } else {
            abort(404, 'Type de conducteur non pris en charge.');
        }
    }

    private function normaliserIntervenantIds(Request $request): void
    {
        if (! $request->has('intervenant_ids')) {
            return;
        }

        $brut = $request->input('intervenant_ids');
        if (! is_string($brut)) {
            return;
        }

        $decode = json_decode($brut, true);
        if (is_array($decode)) {
            $request->merge(['intervenant_ids' => $decode]);
        }
    }

    /**
     * Remplace la liste des intervenants du sujet (position 1..N selon
     * l'ordre de la liste — le 1ᵉʳ est l'invité principal).
     */
    private function synchroniserIntervenants(Sequence $sequence, array $ids): void
    {
        $aSynchroniser = [];
        foreach (array_values($ids) as $index => $id) {
            $aSynchroniser[(int) $id] = ['position' => $index + 1];
        }

        $sequence->intervenants()->sync($aSynchroniser);
    }

    /**
     * `civilite` est castée en CiviliteEnum : sprintf() ne sait pas convertir
     * un enum en string (contrairement au helper e() des Blade).
     */
    private function civiliteEnTexte(Intervenant $intervenant): string
    {
        $civilite = $intervenant->civilite;

        return $civilite instanceof \BackedEnum ? (string) $civilite->value : (string) ($civilite ?? '');
    }

    // =====================================================================
    //  COMPTE À REBOURS ANTENNE
    //  ⚠️ Le chrono est désormais porté par l'ÉMISSION (Conducteur), plus par
    //     la séquence : voir ConducteurPresentateurController (antenne/*).
    //     Les colonnes sequences.presentation_started_at / _ended_at restent
    //     utilisées comme historique réel de passage, renseignées automatiquement.
    // =====================================================================
}
