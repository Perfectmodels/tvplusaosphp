<?php

declare(strict_types=1);

use App\Enums\PoleRubriqueEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CATÉGORIES DE VEILLE — vocabulaire unique
 *
 * Avant : catégorie en texte libre, libellés sauvages (« International »,
 * « Économie », « finance », « High-Tech »…) et 176 sujets sans catégorie.
 *
 * Après : toutes les valeurs sont normalisées vers PoleRubriqueEnum
 * (les rubriques de la rédaction), les inclassables deviennent NULL
 * (« Non classé »), et les sujets sans catégorie héritent de celle de
 * leur source. Index ajouté pour les filtres.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. Normalisation des valeurs existantes -------------------
        $this->normaliser('source_veille');
        $this->normaliser('veille_subjects');

        // --- 2. Backfill : un sujet sans catégorie hérite de sa source ---
        $sources = DB::table('source_veille')
            ->whereNotNull('category')
            ->pluck('category', 'id');

        DB::table('veille_subjects')
            ->whereNull('category')
            ->whereNotNull('source_id')
            ->orderBy('id')
            ->chunkById(500, function ($sujets) use ($sources) {
                foreach ($sujets as $sujet) {
                    $cat = $sources[$sujet->source_id] ?? null;
                    if ($cat !== null) {
                        DB::table('veille_subjects')
                            ->where('id', $sujet->id)
                            ->update(['category' => $cat]);
                    }
                }
            });

        // --- 3. Index pour les filtres par catégorie --------------------
        // try/catch : la migration peut être rejouée (tests de normalisation).
        try {
            Schema::table('veille_subjects', function (Blueprint $table) {
                $table->index('category', 'veille_subjects_category_index');
            });
        } catch (\Throwable) {
            // index déjà présent
        }
    }

    public function down(): void
    {
        try {
            Schema::table('veille_subjects', function (Blueprint $table) {
                $table->dropIndex('veille_subjects_category_index');
            });
        } catch (\Throwable) {
        }
    }

    /**
     * Ramène chaque valeur de `category` vers une valeur PoleRubriqueEnum
     * (ou NULL si le libellé est inclassable).
     */
    private function normaliser(string $table): void
    {
        DB::table($table)
            ->whereNotNull('category')
            ->orderBy('id')
            ->chunkById(500, function ($lignes) use ($table) {
                foreach ($lignes as $ligne) {
                    $normalise = PoleRubriqueEnum::tryFromLoose((string) $ligne->category)?->value;

                    if ($normalise !== $ligne->category) {
                        DB::table($table)
                            ->where('id', $ligne->id)
                            ->update(['category' => $normalise]);
                    }
                }
            });
    }
};
