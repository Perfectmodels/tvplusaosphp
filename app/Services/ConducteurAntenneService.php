<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatutPresentationSequenceEnum;
use App\Events\ConducteurChanged;
use App\Models\Conducteur;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles the live conductor against server time.
 *
 * A browser never has to stay open for an elapsed sequence to be recognised:
 * reading the live snapshot advances the stored state. Can also be called by
 * the scheduler when server cron is configured.
 */
final class ConducteurAntenneService
{
    public function reconcilier(Conducteur $conducteur): void
    {
        if ($conducteur->antenne_started_at === null || $conducteur->antenne_ended_at !== null) {
            return;
        }

        $changed = false;

        try {
            DB::transaction(function () use ($conducteur, &$changed): void {
                $conducteur = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);

                if ($conducteur->antenne_started_at === null || $conducteur->antenne_ended_at !== null) {
                    return;
                }

                $sequences = $conducteur->sequences()->orderBy('position')->get();
                if ($sequences->isEmpty()) {
                    return;
                }

                $total = (int) $sequences->sum(fn (Sequence $s) => $s->duree_antenne_sec);
                $elapsed = (int) $conducteur->antenne_ecoule_sec;
                $terminee = $total > 0 && $elapsed >= $total;

                $cumul = 0;
                $active = $sequences->first();
                if ($terminee) {
                    $active = $sequences->last();
                } else {
                    foreach ($sequences as $sequence) {
                        $cumul += $sequence->duree_antenne_sec;
                        $active = $sequence;
                        if ($elapsed < $cumul) {
                            break;
                        }
                    }
                }

                foreach ($sequences as $sequence) {
                    $before = (int) $sequence->position < (int) $active->position;
                    $after = (int) $sequence->position > (int) $active->position;
                    $target = ($terminee || $before)
                        ? StatutPresentationSequenceEnum::Termine
                        : ($after ? StatutPresentationSequenceEnum::Avenir : StatutPresentationSequenceEnum::EnCours);

                    if ($sequence->statut_presentation !== $target) {
                        $sequence->timestamps = false;
                        $sequence->updateQuietly([
                            'statut_presentation' => $target,
                            'presentation_started_at' => $target === StatutPresentationSequenceEnum::Avenir
                                ? null
                                : ($sequence->presentation_started_at ?? now()),
                            'presentation_ended_at' => $target === StatutPresentationSequenceEnum::Termine
                                ? ($sequence->presentation_ended_at ?? now())
                                : null,
                        ]);
                        $changed = true;
                    }
                }

                if ((int) $conducteur->active_sequence_id !== (int) $active->id || $terminee) {
                    $changes = ['active_sequence_id' => (int) $active->id];
                    if ($terminee) {
                        $changes += [
                            'antenne_started_at' => null,
                            'antenne_offset_sec' => $total,
                            'antenne_ended_at' => now(),
                        ];
                    }
                    $conducteur->timestamps = false;
                    $conducteur->updateQuietly($changes);
                    $changed = true;
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Synchronisation antenne impossible', [
                'conducteur_id' => $conducteur->id,
                'message' => $e->getMessage(),
            ]);
            return;
        }

        if ($changed) {
            ConducteurChanged::dispatch((int) $conducteur->id);
        }
    }
}
