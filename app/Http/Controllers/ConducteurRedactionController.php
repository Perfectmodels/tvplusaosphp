<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatutConducteurEnum;
use App\Events\ConducteurChanged;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\Sondage;
use App\Services\ConducteurCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ConducteurRedactionController extends Controller
{
    public function index(Request $request, ?int $conducteurId = null): View|RedirectResponse
    {
        Gate::authorize('viewAny', Conducteur::class);

        $conducteur = $this->resoudreConducteur($conducteurId, $request);
        if ($conducteur instanceof RedirectResponse) {
            return $conducteur;
        }
        if ($conducteurId === null) {
            return redirect()->route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id]);
        }

        $sequences = $conducteur->sequences->load(['intervenants', 'journalisteResponsable']);

        $emissions = Emission::query()
            ->orderByDesc('is_active')
            ->orderBy('nom')
            ->get()
            ->mapWithKeys(fn ($e) => [$e->id => $e->nom.($e->is_active ? '' : ' (inactive)')]);
        // Liste des autres conducteurs d'émission (navigation)
        $conducteursEmission = Conducteur::typeEmission()
            ->whereKeyNot($conducteur->id)
            ->with('emission')
            ->orderByDesc('date_edition')
            ->limit(20)
            ->get();
        $intervenants = Intervenant::query()->where('is_active', true)->orderBy('nom')->get(['id', 'civilite', 'prenom', 'nom', 'fonction']);
        $journalistes = Journaliste::query()->orderBy('nom')->get(['id', 'civilite', 'prenom', 'nom', 'fonction']);
        $typeOptions = TypeSequenceEnum::selectOptions();
        $sondage = $conducteur->emission_id
            ? Sondage::query()->where('emission_id', $conducteur->emission_id)->latest()->first()
            : null;

        $interactionMessages = collect();
        $interactionMessagesCount = 0;
        if ((string) $conducteur->type === 'emission') {
            $interactionMessagesCount = $conducteur->emissionInteractionMessages()->count();
            $interactionMessages = $conducteur->emissionInteractionMessages()->latest()->limit(20)->get();
        }

        return view('conducteur_redaction', [
            'conducteur' => $conducteur,
            'sequences' => $sequences,
            'emissions' => $emissions,
            'conducteursEmission' => $conducteursEmission,
            'intervenants' => $intervenants,
            'journalistes' => $journalistes,
            'typeOptions' => $typeOptions,
            'dureeTotaleCible' => ConducteurCalculatorService::formatDuree((int) $conducteur->target_duration_sec),
            'dureeActuelle' => ConducteurCalculatorService::formatDuree((int) $conducteur->total_duration),
            'heureDebutEmission' => optional($conducteur->emission)->heure_debut ?? '12:00:00',
            'heureFinCible' => $this->calculerHeureFinCible($conducteur),
            'sondage' => $sondage,
            'interactionMessages' => $interactionMessages,
            'interactionMessagesCount' => $interactionMessagesCount,
        ]);
    }

    /** État temps réel du conducteur partagé : même contrat que la vue antenne. */
    public function liveState(Conducteur $conducteur): JsonResponse
    {
        return app(ConducteurPresentateurController::class)->liveState($conducteur);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Conducteur::class);

        $valid = $request->validate([
            'emission_id' => ['required', 'integer', 'exists:emissions,id'],
            'date_edition' => ['required', 'date'],
        ]);

        $exist = Conducteur::typeEmission()
            ->where('emission_id', (int) $valid['emission_id'])
            ->where('date_edition', $valid['date_edition'])
            ->first();
        if ($exist) {
            return redirect()->route('conducteurs.chef_edition', ['conducteurId' => $exist->id])
                ->with('info', 'Conducteur d\'émission déjà existant pour cette édition.');
        }

        $conducteur = Conducteur::create([
            'emission_id' => (int) $valid['emission_id'],
            'date_edition' => $valid['date_edition'],
            'owner_id' => auth()->id(),
            'type' => 'emission',
            'status' => StatutConducteurEnum::Brouillon,
            'active_version' => true,
        ]);

        return redirect()->route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id])
            ->with('success', 'Conducteur d\'émission créé.');
    }

    public function update(Request $request, Conducteur $conducteur): RedirectResponse
    {
        abort_if(
            (string) $conducteur->type !== 'chef_edition' && (string) $conducteur->type !== 'emission',
            404,
            'Ce conducteur n\'est pas un conducteur Chef d\'Édition / Émission.'
        );

        Gate::authorize('update', $conducteur);

        $valid = $request->validate([
            'date_edition' => ['sometimes', 'date'],
            'presenter_on_air_id' => ['sometimes', 'nullable', 'integer', 'exists:journalistes,id'],
            'target_duration_sec' => ['sometimes', 'integer', 'min:60', 'max:21600'],
            'opening_text' => ['sometimes', 'nullable', 'string'],
            'closing_text' => ['sometimes', 'nullable', 'string'],
        ]);

        $conducteur->update($valid);

        return back()->with('success', 'Conducteur mis à jour.');
    }

    public function reorder(Request $request, Conducteur $conducteur): JsonResponse
    {
        abort_if(
            (string) $conducteur->type !== 'chef_edition' && (string) $conducteur->type !== 'emission',
            404,
            'Ce conducteur n\'est pas un conducteur Chef d\'Édition / Émission.'
        );

        Gate::authorize('reorderSequences', $conducteur);
        Gate::authorize('view', $conducteur);
        abort_if($conducteur->date_locked_at !== null, 423, 'Ce conducteur est verrouillé.');

        $valid = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct', 'exists:sequences,id'],
        ]);
        $orderedIds = array_values(array_map('intval', $valid['ordered_ids']));
        // The editor only sends visible sequences; preserve masked items after them.
        $allIds = $conducteur->sequences()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        if (collect($orderedIds)->sort()->values()->all() !== $allIds) {
            return response()->json(['ok' => false, 'message' => 'Toutes les séquences du conducteur doivent être présentes une seule fois.'], 422);
        }

        DB::transaction(function () use ($conducteur, $orderedIds): void {
            Conducteur::query()->whereKey($conducteur->id)->lockForUpdate()->firstOrFail();
            $maskedIds = $conducteur->sequencesAvecMasques()->where('masque', true)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach (array_merge($orderedIds, $maskedIds) as $position => $id) {
                \App\Models\Sequence::query()->where('conducteur_id', $conducteur->id)
                    ->whereKey($id)->update(['position' => $position + 1]);
            }
            ConducteurCalculatorService::recalculerHeures($conducteur->fresh());
        });
        ConducteurChanged::dispatch((int) $conducteur->id);

        return response()->json([
            'ok' => true,
            'sequences' => $conducteur->fresh()->sequences->map(fn ($s) => [
                'id' => $s->id,
                'position' => $s->position,
                'heure_debut_calculee' => $s->debut_hm,
                'heure_fin_calculee' => $s->fin_hm,
                'duree_hm' => $s->duree_hm,
            ]),
            'duree_totale_hm' => $conducteur->fresh()->total_duration_hm,
        ]);
    }

    private function resoudreConducteur(?int $conducteurId, Request $request): Conducteur|RedirectResponse
    {
        if ($conducteurId) {
            $c = Conducteur::typeEmission()->find($conducteurId);
            if (! $c) {
                return redirect()->route('conducteurs.emission.index')
                    ->withErrors(['Conducteur introuvable ou n\'étant pas un conducteur d\'émission.']);
            }

            return $c;
        }

        // PAS de création automatique sur simple GET : on affiche un état vide explicite.
        $c = Conducteur::typeEmission()
            ->latest('date_edition')
            ->latest('id')
            ->first();

        if (! $c) {
            return redirect()->route('conducteurs.emission.index')
                ->with('info', 'Aucun conducteur d\'émission existant. Créez-en un d\'abord.');
        }

        return $c;
    }

    private function calculerHeureFinCible(Conducteur $conducteur): string
    {
        $hd = '12:00:00';
        try {
            if ($conducteur->emission && $conducteur->emission->heure_debut) {
                $hd = is_string($conducteur->emission->heure_debut)
                    ? $conducteur->emission->heure_debut
                    : $conducteur->emission->heure_debut->format('H:i:s');
            }
        } catch (\Throwable) {
        }
        $parts = array_map('intval', explode(':', $hd));
        $sec = ($parts[0] ?? 12) * 3600 + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0);
        $sec += (int) ($conducteur->target_duration_sec ?? 3600);
        $sec = $sec % (24 * 3600);
        $h = (int) floor($sec / 3600);
        $r = $sec % 3600;
        $m = (int) floor($r / 60);
        $s = $r % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
