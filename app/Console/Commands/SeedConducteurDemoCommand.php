<?php

namespace App\Console\Commands;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\TypeSequenceEnum;
use Illuminate\Console\Command;

class SeedConducteurDemoCommand extends Command
{
    protected $signature   = 'conducteur:seed-demo {conducteur=1}';
    protected $description = 'Injecte 3 séquences de démo dans un conducteur pour smoke test UI+Workflow';

    public function handle(): int
    {
        $cid = (int) $this->argument('conducteur');
        $c = \App\Models\Conducteur::find($cid);
        if (!$c) { $this->error("Conducteur $cid absent"); return 1; }

        $intId = (int) \App\Models\Intervenant::query()->where('is_active', true)->value('id');
        $jId   = (int) \App\Models\Journaliste::query()->value('id');

        $c->sequencesAvecMasques()->delete();

        $items = [
            [
                'type' => TypeSequenceEnum::Ouverture,
                'title' => 'Ouverture JT + accueil',
                'duration_seconds' => 60,
            ],
            [
                'type' => TypeSequenceEnum::Reportage,
                'title' => 'Reportage: ZLECAf – opportunités régionales',
                'duration_seconds' => 420,
            ],
            [
                'type' => TypeSequenceEnum::Interview,
                'title' => 'Interview invité plateau – bilan COP',
                'duration_seconds' => 300,
            ],
            [
                'type' => TypeSequenceEnum::Meteo,
                'title' => 'Bulletin météo Afrique centrale',
                'duration_seconds' => 90,
            ],
            [
                'type' => TypeSequenceEnum::Conclusion,
                'title' => 'Conclusion + fin JT',
                'duration_seconds' => 45,
            ],
        ];

        $pos = 1;
        foreach ($items as $it) {
            $seq = $c->sequencesAvecMasques()->create([
                'type' => $it['type'],
                'title' => $it['title'],
                'position' => $pos++,
                'duration_seconds' => $it['duration_seconds'],
                'actual_duration_sec' => $it['duration_seconds'],
                'journaliste_responsable_id' => $jId ?: null,
                'intervenant_id' => $intId ?: null,
                'statut_terrain' => StatutEtapeWorkflowEnum::En_cours,
                'statut_montage' => StatutEtapeWorkflowEnum::En_attente,
                'statut_habillage' => StatutEtapeWorkflowEnum::En_attente,
                'statut_livraison' => StatutEtapeWorkflowEnum::En_attente,
                'statut_diffusion' => StatutEtapeWorkflowEnum::En_attente,
            ]);
            if ($intId) {
                $seq->intervenants()->syncWithoutDetaching([$intId => ['position' => 1]]);
            }
            $tv = $seq->type instanceof \App\Enums\TypeSequenceEnum ? $seq->type->value : (string)$seq->type;
            $this->line("CREATED seq #$seq->id pos=$seq->position $tv $seq->title");
        }

        \App\Services\ConducteurCalculatorService::recalculerHeures($c->fresh());
        $c2 = $c->fresh();
        $this->newLine();
        $this->info("Conducteur #$c2->id – total_duration=$c2->total_duration sec | progression=$c2->progression_percent% | seqs=".$c2->sequences->count());
        foreach ($c2->sequences as $s) {
            $tv = $s->type instanceof \App\Enums\TypeSequenceEnum ? $s->type->value : (string)$s->type;
            $this->line("  [$s->position] $s->debut_hm → $s->fin_hm ($s->duree_hm) $tv $s->title");
        }
        return 0;
    }
}
