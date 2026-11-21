<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleSequenceIntervenantEnum;
use App\Enums\StatutSondageEnum;
use App\Enums\TypeEmissionEnum;
use App\Enums\TypeSequenceEnum;
use App\Http\Requests\StoreSondageRequest;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Sequence;
use App\Models\Sondage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class SondageAdminController extends Controller
{
    public function create(Request $request, Emission $emission): View
    {
        $this->authorize('create', [Sondage::class, $emission]);
        abort_unless($emission->type === TypeEmissionEnum::Debat, 404);

        $conducteurs = $this->conducteursDebat($emission);
        $selectedConducteurId = $request->integer('conducteur_id')
            ?: ($conducteurs->count() === 1 ? (int) $conducteurs->first()->id : null);
        $sequence = $selectedConducteurId ? $this->sequenceDebat($emission, $selectedConducteurId) : null;
        $candidats = $sequence ? $this->debatteurs($sequence) : collect();

        return view('sondage.create', compact('emission', 'conducteurs', 'selectedConducteurId', 'candidats'));
    }

    public function store(StoreSondageRequest $request, Emission $emission): RedirectResponse
    {
        $this->authorize('create', [Sondage::class, $emission]);
        abort_unless($emission->type === TypeEmissionEnum::Debat, 404);

        $conducteurs = $this->conducteursDebat($emission);
        $conducteurId = $request->integer('conducteur_id')
            ?: ($conducteurs->count() === 1 ? (int) $conducteurs->first()->id : null);
        if ($conducteurId === null) {
            throw ValidationException::withMessages([
                'conducteur_id' => 'Sélectionnez une édition précise du débat.',
            ]);
        }
        if (! $conducteurs->contains('id', $conducteurId)) {
            throw ValidationException::withMessages([
                'conducteur_id' => 'Le conducteur sélectionné ne correspond pas à cette émission.',
            ]);
        }
        $sequence = $this->sequenceDebat($emission, $conducteurId);
        abort_unless($sequence !== null, 422, 'Aucune séquence DÉBAT ne figure dans cette édition.');

        $candidats = $this->debatteurs($sequence);
        abort_if($candidats->isEmpty(), 422, 'Aucun débatteur n’est associé à la séquence DÉBAT.');

        $sondage = DB::transaction(function () use ($request, $emission, $sequence, $candidats): Sondage {
            $sondage = Sondage::create([
                'emission_id' => $emission->id,
                'sequence_id' => $sequence->id,
                'created_by' => auth()->id(),
                'question' => $request->validated('question'),
            ]);

            foreach ($candidats as $index => $candidat) {
                $sondage->candidats()->create([
                    'intervenant_id' => $candidat->id,
                    'nom' => $candidat->nomComplet,
                    'ordre' => (int) ($candidat->pivot->ordre_passage ?? $index + 1),
                ]);
            }

            return $sondage;
        });

        return redirect()
            ->route('sondages.show', $sondage)
            ->with('success', 'Sondage créé. Ouvrez-le pour permettre le vote.');
    }

    public function show(Sondage $sondage): View
    {
        $this->authorize('view', $sondage);

        $sondage->load('emission');
        $resultats = $sondage->resultats();
        $urlVote = route('sondage.show', $sondage->token);

        return view('sondage.admin', compact('sondage', 'resultats', 'urlVote'));
    }

    public function ouvrir(Sondage $sondage): RedirectResponse
    {
        $this->authorize('update', $sondage);
        abort_unless($sondage->statut === StatutSondageEnum::Brouillon, 422, 'Ce sondage n’est plus au statut brouillon.');

        $sondage->forceFill([
            'statut' => StatutSondageEnum::Ouvert,
            'ouvert_at' => now(),
        ])->save();

        return back()->with('success', 'Sondage ouvert : le QR code est actif.');
    }

    public function clore(Sondage $sondage): RedirectResponse
    {
        $this->authorize('update', $sondage);
        abort_unless($sondage->statut === StatutSondageEnum::Ouvert, 422, 'Ce sondage n’est pas ouvert.');

        $sondage->forceFill([
            'statut' => StatutSondageEnum::Clos,
            'clos_at' => now(),
        ])->save();

        return back()->with('success', 'Sondage clos : les résultats sont figés.');
    }

    public function qr(Sondage $sondage): Response
    {
        $this->authorize('view', $sondage);

        $svg = (string) QrCode::size(560)
            ->margin(2)
            ->errorCorrection('H')
            ->backgroundColor(255, 255, 255)
            ->color(0, 26, 65)
            ->generate(route('sondage.show', $sondage->token));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-sondage-'.$sondage->token.'.svg"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return Collection<int, Conducteur> */
    private function conducteursDebat(Emission $emission): Collection
    {
        return Conducteur::query()
            ->where('emission_id', $emission->id)
            ->where('type', 'emission')
            ->orderByDesc('date_edition')
            ->orderByDesc('id')
            ->get();
    }

    private function sequenceDebat(Emission $emission, int $conducteurId): ?Sequence
    {
        return Sequence::query()
            ->where('conducteur_id', $conducteurId)
            ->where('emission_id', $emission->id)
            ->where('type', TypeSequenceEnum::Debat->value)
            ->orderBy('position')
            ->first();
    }

    /** @return Collection<int, Intervenant> */
    private function debatteurs(Sequence $sequence): Collection
    {
        return $sequence->intervenants()
            ->withPivot('role_dans_sequence', 'ordre_passage')
            ->wherePivot('role_dans_sequence', RoleSequenceIntervenantEnum::Debatteur->value)
            ->orderByPivot('ordre_passage')
            ->get();
    }
}
