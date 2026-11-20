<?php

use App\Enums\StatutEtapeWorkflowEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            if (! Schema::hasColumn('sequences', 'journaliste_responsable_id')) {
                $table->foreignId('journaliste_responsable_id')->nullable()->after('journaliste_id')
                    ->constrained('journalistes')->nullOnDelete();
            }
            if (! Schema::hasColumn('sequences', 'actual_duration_sec')) {
                $table->integer('actual_duration_sec')->nullable()->after('duration_seconds');
            }
            if (! Schema::hasColumn('sequences', 'heure_debut_calculee')) {
                $table->time('heure_debut_calculee')->nullable()->after('actual_duration_sec');
            }
            if (! Schema::hasColumn('sequences', 'heure_fin_calculee')) {
                $table->time('heure_fin_calculee')->nullable()->after('heure_debut_calculee');
            }
            if (! Schema::hasColumn('sequences', 'nom_prenom_text')) {
                $table->string('nom_prenom_text')->nullable()->after('title');
            }
            if (! Schema::hasColumn('sequences', 'fonction_text')) {
                $table->string('fonction_text')->nullable()->after('nom_prenom_text');
            }
            if (! Schema::hasColumn('sequences', 'note_regie')) {
                $table->text('note_regie')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('sequences', 'angle_editorial')) {
                $table->mediumText('angle_editorial')->nullable()->after('note_regie');
            }

            // 5 colonnes Workflow Terrain→Montage→Habillage→Livraison→Diffusion
            $workflowCols = [
                'statut_terrain',
                'statut_montage',
                'statut_habillage',
                'statut_livraison',
                'statut_diffusion',
            ];
            foreach ($workflowCols as $col) {
                if (! Schema::hasColumn('sequences', $col)) {
                    $table->string($col, 50)->default(StatutEtapeWorkflowEnum::En_attente->value);
                }
            }

            if (! Schema::hasColumn('sequences', 'heure_livraison_prevue')) {
                $table->time('heure_livraison_prevue')->nullable()->after('statut_livraison');
            }
            if (! Schema::hasColumn('sequences', 'alerte_info')) {
                $table->boolean('alerte_info')->default(false)->after('statut_diffusion');
            }
            if (! Schema::hasColumn('sequences', 'masque')) {
                $table->boolean('masque')->default(false)->after('alerte_info');
            }
            if (! Schema::hasColumn('sequences', 'user_last_modif_id')) {
                $table->foreignId('user_last_modif_id')->nullable()->after('masque')->constrained('users')->nullOnDelete();
            }

            $table->string('type', 50)->default('SUJET')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $dropCols = [
                'journaliste_responsable_id',
                'actual_duration_sec',
                'heure_debut_calculee',
                'heure_fin_calculee',
                'nom_prenom_text',
                'fonction_text',
                'note_regie',
                'angle_editorial',
                'statut_terrain',
                'statut_montage',
                'statut_habillage',
                'statut_livraison',
                'statut_diffusion',
                'heure_livraison_prevue',
                'alerte_info',
                'masque',
                'user_last_modif_id',
            ];
            foreach ($dropCols as $c) {
                if (! Schema::hasColumn('sequences', $c)) {
                    continue;
                }
                if (in_array($c, ['journaliste_responsable_id', 'user_last_modif_id'])) {
                    try {
                        $table->dropForeign(["{$c}_foreign"]);
                    } catch (\Throwable) {
                    }
                }
                $table->dropColumn($c);
            }
        });
    }
};
