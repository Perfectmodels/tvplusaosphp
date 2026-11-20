<?php

use App\Enums\StatutEmissionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emissions', function (Blueprint $table) {
            $table->string('nom', 255)->default('')->after('id');
            $table->string('sous_titre', 500)->nullable()->after('nom');
            $table->string('categorie', 50)->nullable()->after('type');
            $table->string('cible_audience', 50)->nullable()->after('categorie');
            $table->json('langues')->nullable()->after('cible_audience');

            $table->time('heure_debut')->nullable()->after('duration_minutes');
            $table->time('heure_fin')->nullable()->after('heure_debut');
            $table->unsignedInteger('duree_minutes')->nullable()->after('heure_fin');
            $table->string('fuseau_horaire', 60)->nullable()->after('duree_minutes');
            $table->json('jours_diffusion')->nullable()->after('fuseau_horaire');
            $table->date('date_lancement')->nullable()->after('jours_diffusion');
            $table->date('date_fin')->nullable()->after('date_lancement');
            $table->string('format_image', 20)->nullable()->after('date_fin');
            $table->string('qualite', 20)->nullable()->after('format_image');

            $table->string('couleur_habillage', 9)->nullable()->after('theme_color');
            $table->string('police_titres', 60)->nullable()->after('couleur_habillage');
            $table->string('generique_video_path', 500)->nullable()->after('thumbnail');
            $table->string('jingle_audio_path', 500)->nullable()->after('generique_video_path');
            $table->string('statut', 30)->default(StatutEmissionEnum::Planifiee->value)->after('status');

            $table->boolean('is_brouillon')->default(true)->after('notes');
            $table->boolean('is_active')->default(true)->after('is_brouillon');

            $table->index(['statut', 'is_active'], 'idx_emissions_statut_actif');
            $table->index(['type', 'categorie'], 'idx_emissions_type_categorie');
            $table->index(['heure_debut', 'statut'], 'idx_emissions_horaire_statut');
            $table->index(['date_lancement', 'date_fin'], 'idx_emissions_dates');
        });

        Schema::table('emissions', function (Blueprint $table) {
            DB::statement("UPDATE emissions SET nom = title WHERE nom = '' OR nom IS NULL");
            // --- Backfill portable (PHP, sans SQL MySQL-spécifique) ---
            // Les requêtes brutes CONCAT()/CASE avec guillemets doubles cassaient SQLite (tests).
            $mapStatut = [
                'draft' => StatutEmissionEnum::Brouillon->value,
                'scheduled' => StatutEmissionEnum::Planifiee->value,
                'recording' => StatutEmissionEnum::En_cours->value,
                'broadcast' => StatutEmissionEnum::Diffusee->value,
                'replay' => StatutEmissionEnum::Diffusee->value,
                'archived' => StatutEmissionEnum::Archivee->value,
                'cancelled' => StatutEmissionEnum::Annulee->value,
            ];

            DB::table('emissions')
                ->orderBy('id')
                ->get(['id', 'nom', 'title', 'slug', 'status', 'statut', 'duree_minutes', 'duration_minutes', 'theme_color', 'couleur_habillage'])
                ->each(function ($row) use ($mapStatut) {
                    $maj = [];

                    if ($row->duree_minutes === null && $row->duration_minutes !== null) {
                        $maj['duree_minutes'] = $row->duration_minutes;
                    }

                    if ($row->statut === null || $row->statut === '') {
                        $maj['statut'] = $mapStatut[(string) $row->status] ?? StatutEmissionEnum::Planifiee->value;
                    }

                    if ($row->couleur_habillage === null && $row->theme_color !== null) {
                        $maj['couleur_habillage'] = $row->theme_color;
                    }

                    $maj['is_brouillon'] = ((string) $row->status === 'draft') ? 1 : 0;

                    if ($row->slug === null || $row->slug === '') {
                        $base = trim((string) ($row->nom !== null && $row->nom !== '' ? $row->nom : ($row->title ?? '')));
                        if ($base === '') {
                            $base = 'emission-'.$row->id;
                        }
                        $slug = strtolower(str_replace(' ', '-', $base));
                        $slug = str_replace(["'", '?', '!'], '', $slug);
                        $maj['slug'] = $slug !== '' ? $slug : ('emission-'.$row->id);
                    }

                    if ($maj !== []) {
                        DB::table('emissions')->where('id', $row->id)->update($maj);
                    }
                });
        });
    }

    public function down(): void
    {
        Schema::table('emissions', function (Blueprint $table) {
            $indexesToDrop = [
                'idx_emissions_statut_actif',
                'idx_emissions_type_categorie',
                'idx_emissions_horaire_statut',
                'idx_emissions_dates',
            ];
            // NB: listing des index existants en SQL portable (l'ancien "SHOW INDEX FROM" est MySQL-only)
            $existingIndexNames = [];
            try {
                $rows = DB::select(
                    "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'emissions'"
                );
                foreach ($rows as $r) {
                    $existingIndexNames[] = $r->name;
                }
            } catch (Throwable) {
                try {
                    $rows = DB::select('SHOW INDEX FROM emissions');
                    foreach ($rows as $r) {
                        $existingIndexNames[] = $r->Key_name ?? $r->key_name ?? null;
                    }
                    $existingIndexNames = array_values(array_filter($existingIndexNames));
                } catch (Throwable) {
                    $existingIndexNames = [];
                }
            }
            foreach ($indexesToDrop as $idx) {
                if (in_array($idx, $existingIndexNames, true)) {
                    $table->dropIndex($idx);
                }
            }

            $table->dropColumn([
                'nom',
                'sous_titre',
                'categorie',
                'cible_audience',
                'langues',
                'heure_debut',
                'heure_fin',
                'duree_minutes',
                'fuseau_horaire',
                'jours_diffusion',
                'date_lancement',
                'date_fin',
                'format_image',
                'qualite',
                'couleur_habillage',
                'police_titres',
                'generique_video_path',
                'jingle_audio_path',
                'statut',
                'is_brouillon',
                'is_active',
            ]);
        });
    }
};
