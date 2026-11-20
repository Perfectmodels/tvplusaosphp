<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleSequenceIntervenantEnum;
use App\Enums\StatutSondageEnum;
use App\Enums\TypeEmissionEnum;
use App\Enums\TypeSequenceEnum;
use App\Http\Requests\StoreSondageRequest;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Sequence;
use App\Models\Sondage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class SondageAdminController extends Controller
{
    public function create(Emission $emission): View
    {
        $this->authorize('create', [Sondage::class, $emission]);
        abort_unless($emission->type === TypeEmissionEnum::Debat, 404);

        $sequence = $this->sequenceDebat($emission);
        $candidats = $sequence ? $this->debatteurs($sequence) : collect();

        return view('sondage.create', compact('emission', 'candidats'));
    }

    public function store(StoreSondageRequest $request, Emission $emission): RedirectResponse
    {
        $this->authorize('create', [Sondage::class, $emission]);
        abort_unless($emission->type === TypeEmissionEnum::Debat, 404);

        $sequence = $this->sequenceDebat($emission);
        abort_unless($sequence !== null, 422, 'Aucune séquence DÉBAT n’est associée à cette émission.');

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

    private function sequenceDebat(Emission $emission): ?Sequence
    {
        return $emission->sequences()
            ->where('type', TypeSequenceEnum::Debat->value)
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
