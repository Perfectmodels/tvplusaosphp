<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LOT 12 — Nettoyage des parcours fantômes existants.
 *
 * Avant le durcissement du pivot (hooks `created` / `deleted`), retirer un
 * invité d'un sujet laissait sa ligne `parcours_intervenants` orpheline :
 * la FK `sequence_intervenant_id` passait à NULL (`nullOnDelete`) et la ligne
 * continuait d'apparaître dans Parcours Intervenants.
 *
 * On rejoue ici la règle métier sur les données déjà en base :
 *  1. ligne déliée dont le couple (intervenant, séquence) a TOUJOURS un pivot
 *     → on la relie au pivot (ré-ajout avant correctif, pas de doublon) ;
 *  2. ligne déliée sans pivot correspondant et jamais convoquée
 *     (ni QR ni confirmation) → suppression (vrai fantôme) ;
 *  3. ligne déliée sans pivot mais avec convocation réelle (qr_envoye_at /
 *     confirme_at) → conservée telle quelle (déliée), comme le veut la règle.
 *
 * Les parcours programmés manuellement (`emission_id` renseigné, `sequence_id`
 * NULL) ne sont jamais touchés.
 *
 * Boucle PHP plutôt que DELETE … NOT EXISTS : portable SQLite/MySQL sans
 * dépendre d'une syntaxe de sous-requête DELETE spécifique.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pivot existant par couple (intervenant, séquence).
        $pivotsParCouple = [];
        $pivotsLies = [];

        foreach (DB::table('sequence_intervenants')->select('id', 'intervenant_id', 'sequence_id')->get() as $pivot) {
            $pivotsParCouple[$pivot->intervenant_id.'-'.$pivot->sequence_id] = (int) $pivot->id;
        }

        foreach (DB::table('parcours_intervenants')->whereNotNull('sequence_intervenant_id')->pluck('sequence_intervenant_id') as $pivotId) {
            $pivotsLies[(int) $pivotId] = true;
        }

        $candidats = DB::table('parcours_intervenants')
            ->select('id', 'intervenant_id', 'sequence_id', 'qr_envoye_at', 'confirme_at')
            ->whereNull('sequence_intervenant_id')
            ->whereNull('emission_id')
            ->whereNotNull('sequence_id')
            ->get();

        foreach ($candidats as $parcours) {
            $pivotId = $pivotsParCouple[$parcours->intervenant_id.'-'.$parcours->sequence_id] ?? null;

            // 1. Ré-ajout : le couple a encore un pivot libre → on relie.
            if ($pivotId !== null && ! isset($pivotsLies[$pivotId])) {
                DB::table('parcours_intervenants')
                    ->where('id', $parcours->id)
                    ->update(['sequence_intervenant_id' => $pivotId]);

                $pivotsLies[$pivotId] = true;

                continue;
            }

            // 3. Convocation réelle déjà envoyée → on garde la ligne déliée.
            if ($parcours->qr_envoye_at !== null || $parcours->confirme_at !== null) {
                continue;
            }

            // 2. Fantôme pur → suppression.
            DB::table('parcours_intervenants')->where('id', $parcours->id)->delete();
        }
    }

    public function down(): void
    {
        // Nettoyage de données : irréversible (aucune restauration possible).
    }
};
