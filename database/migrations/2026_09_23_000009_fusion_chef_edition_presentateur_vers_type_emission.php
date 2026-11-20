<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // --- Étape 1 : Transférer sequences des presentateurs vers leur jumeau chef_edition
            $presentateurs = DB::table('conducteurs')
                ->whereIn('type', ['presentateur', 'presentation'])
                ->whereNotNull('emission_id')
                ->whereNotNull('date_edition')
                ->get(['id', 'type', 'emission_id', 'date_edition']);

            foreach ($presentateurs as $p) {
                $chef = DB::table('conducteurs')
                    ->where('type', 'chef_edition')
                    ->where('emission_id', (int) $p->emission_id)
                    ->where('date_edition', $p->date_edition)
                    ->first();

                $targetId = $chef?->id;

                // Si aucun chef correspondant, on essaie emission existant
                if (! $targetId) {
                    $emission = DB::table('conducteurs')
                        ->where('type', 'emission')
                        ->where('emission_id', (int) $p->emission_id)
                        ->where('date_edition', $p->date_edition)
                        ->first();
                    $targetId = $emission?->id;
                }

                // Si toujours aucune cible -> on transforme le presentateur directement en emission
                if (! $targetId) {
                    DB::table('conducteurs')
                        ->where('id', (int) $p->id)
                        ->update(['type' => 'emission', 'updated_at' => now()]);

                    continue;
                }

                // Transfert sequences
                DB::table('sequences')
                    ->where('conducteur_id', (int) $p->id)
                    ->update([
                        'conducteur_id' => (int) $targetId,
                        'emission_id'   => (int) $p->emission_id,
                        'updated_at'    => now(),
                    ]);

                // Suppression row presentateur devenue orpheline
                DB::table('conducteurs')->where('id', (int) $p->id)->delete();
            }

            // --- Étape 2 : Transformer tous les chef_edition restants en emission
            DB::table('conducteurs')
                ->where('type', 'chef_edition')
                ->update([
                    'type'       => 'emission',
                    'updated_at' => now(),
                ]);

            // --- Étape 3 : Sécurité - renommer les legacy 'presentation' en 'presentateur'
            DB::table('conducteurs')
                ->where('type', 'presentation')
                ->update([
                    'type'       => 'presentateur',
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // Non réversible sans perte. On restaure juste les noms de types Chef/Present (pas les fusions/liens).
        DB::table('conducteurs')
            ->where('type', 'emission')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sequences as s')
                    ->whereColumn('s.conducteur_id', 'conducteurs.id')
                    ->whereNotNull('s.notes_presentateur');
            })
            ->update(['type' => 'chef_edition', 'updated_at' => now()]);
    }
};
