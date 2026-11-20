<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Code d'accès court (6 chiffres) pour la saisie manuelle au scanner.
 *
 * Le jeton `qr_token` de 48 caractères reste la référence sécurisée du QR.
 * Ce code séparé, unique par invitation, est plus simple à lire et à dicter.
 */
return new class extends Migration
{
    private const TENTATIVES_GENERATION = 50;

    public function up(): void
    {
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->string('code_manuel', 16)->nullable()->after('qr_token');
        });

        $this->remplirCodesManuels();

        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->unique('code_manuel');
        });
    }

    public function down(): void
    {
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->dropUnique(['code_manuel']);
            $table->dropColumn('code_manuel');
        });
    }

    private function remplirCodesManuels(): void
    {
        $codes = DB::table('parcours_intervenants')
            ->whereNotNull('code_manuel')
            ->pluck('code_manuel')
            ->mapWithKeys(fn ($code): array => [(string) $code => true])
            ->all();

        DB::table('parcours_intervenants')
            ->whereNull('code_manuel')
            ->orderBy('id')
            ->chunkById(100, function ($parcours) use (&$codes): void {
                foreach ($parcours as $item) {
                    $code = $this->codeDisponible($codes);

                    DB::table('parcours_intervenants')
                        ->where('id', $item->id)
                        ->update(['code_manuel' => $code]);

                    $codes[$code] = true;
                }
            });
    }

    /**
     * @param  array<string, bool>  $codes
     */
    private function codeDisponible(array $codes): string
    {
        for ($tentative = 0; $tentative < self::TENTATIVES_GENERATION; $tentative++) {
            $code = (string) random_int(100000, 999999);

            if (! isset($codes[$code])) {
                return $code;
            }
        }

        throw new RuntimeException('Impossible de générer un code manuel unique pour les parcours existants.');
    }
};
