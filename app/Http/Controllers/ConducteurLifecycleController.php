<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatutConducteurEnum;
use App\Models\Conducteur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Explicit workflow: preparation -> validation -> clôture -> archives.
 * No destructive operation and no implicit creation of a broadcast conductor.
 */
final class ConducteurLifecycleController extends Controller
{
    public function soumettre(Request $request, Conducteur $conducteur): RedirectResponse
    {
        Gate::authorize('update', $conducteur);

        $this->transition($conducteur, [
            StatutConducteurEnum::Brouillon,
            StatutConducteurEnum::En_construction,
        ], StatutConducteurEnum::A_valider);

        return back()->with('success', 'Conducteur soumis pour validation.');
    }

    public function valider(Request $request, Conducteur $conducteur): RedirectResponse
    {
        Gate::authorize('validateConducteur', $conducteur);

        $this->transition(
            $conducteur,
            [StatutConducteurEnum::A_valider],
            StatutConducteurEnum::Valide,
            [
                'validated_at' => now(),
                'validated_by' => $request->user()->id,
            ],
        );

        return back()->with('success', 'Conducteur validé.');
    }

    public function cloturer(Request $request, Conducteur $conducteur): RedirectResponse
    {
        Gate::authorize('validateConducteur', $conducteur);

        $this->transition(
            $conducteur,
            [
                StatutConducteurEnum::Valide,
                StatutConducteurEnum::Transfere,
                StatutConducteurEnum::En_direct,
            ],
            StatutConducteurEnum::Cloture,
            [],
            true,
        );

        return back()->with('success', 'Conducteur clôturé. Il peut maintenant être archivé.');
    }

    public function archiver(Request $request, Conducteur $conducteur): RedirectResponse
    {
        Gate::authorize('delete', $conducteur);

        $this->transition(
            $conducteur,
            [StatutConducteurEnum::Cloture],
            StatutConducteurEnum::Archive,
            [
                'date_locked_at' => now(),
                'active_version' => false,
            ],
        );

        return back()->with('success', 'Conducteur archivé sans suppression des séquences.');
    }

    public function restaurer(Request $request, Conducteur $conducteur): RedirectResponse
    {
        Gate::authorize('unlock', $conducteur);

        $this->transition(
            $conducteur,
            [StatutConducteurEnum::Archive],
            StatutConducteurEnum::Cloture,
            [
                'date_locked_at' => null,
                'active_version' => true,
            ],
        );

        return back()->with('success', 'Conducteur restauré. Les séquences sont conservées.');
    }

    /**
     * @param list<StatutConducteurEnum> $autorisations
     * @param array<string, mixed> $champs
     */
    private function transition(
        Conducteur $conducteur,
        array $autorisations,
        StatutConducteurEnum $cible,
        array $champs = [],
        bool $interdireAntenneActive = false,
    ): void {
        DB::transaction(static function () use ($conducteur, $autorisations, $cible, $champs, $interdireAntenneActive): void {
            $locked = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);
            abort_unless(in_array($locked->status, $autorisations, true), 422, 'Transition impossible depuis le statut actuel.');
            abort_if(
                $locked->date_locked_at !== null && $locked->status !== StatutConducteurEnum::Archive,
                423,
                'Ce conducteur est verrouillé.',
            );
            if ($interdireAntenneActive) {
                abort_if($locked->antenne_en_marche
                    || ($locked->status === StatutConducteurEnum::En_direct && ! $locked->antenne_terminee),
                    422,
                    'Terminez le direct, même s’il est en pause, avant la clôture.');
            }
            $locked->update(array_merge(['status' => $cible], $champs));
        });
    }
}
