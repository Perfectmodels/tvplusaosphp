<?php

use App\Enums\RoleSequenceIntervenantEnum;
use App\Enums\StatutLogistiquePivotEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot centrale (SPEC §7.2 sequence_intervenants)
 * Ajoute les 5 colonnes d'horodatages scan logistique + statut + no_show (SPEC §5.2 §4.6)
 * Migration indépendante séparée de l'upgrade 031
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sequence_intervenants', function (Blueprint $table) {

            if (!Schema::hasColumn('sequence_intervenants', 'role_dans_sequence')) {
                $table->string('role_dans_sequence', 40)
                    ->nullable()
                    ->default(RoleSequenceIntervenantEnum::Invite->value)
                    ->after('intervenant_id');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'ordre_passage')) {
                $table->unsignedTinyInteger('ordre_passage')->nullable()->default(1)->after('role_dans_sequence');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'duree_allouee_sec')) {
                $table->unsignedInteger('duree_allouee_sec')->nullable()->default(300)->after('ordre_passage');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'heure_passage_prevue')) {
                $table->time('heure_passage_prevue')->nullable()->after('duree_allouee_sec');
            }

            // 3 étapes scan — heures (SPEC §5.2 colonnes 5,6,7 + SPEC §6.3 workflow)
            if (!Schema::hasColumn('sequence_intervenants', 'heure_arrive')) {
                $table->dateTime('heure_arrive')->nullable()->after('heure_passage_prevue');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'heure_maquillage_debut')) {
                $table->dateTime('heure_maquillage_debut')->nullable()->after('heure_arrive');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'heure_maquillage_fin')) {
                $table->dateTime('heure_maquillage_fin')->nullable()->after('heure_maquillage_debut');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'heure_plateau')) {
                $table->dateTime('heure_plateau')->nullable()->after('heure_maquillage_fin');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'heure_termine_passage')) {
                $table->dateTime('heure_termine_passage')->nullable()->after('heure_plateau');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'statut_logistique')) {
                $table->string('statut_logistique', 32)
                    ->nullable()
                    ->default(StatutLogistiquePivotEnum::Programme->value)
                    ->after('heure_termine_passage');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'no_show')) {
                $table->boolean('no_show')->default(false)->after('statut_logistique');
            }
            if (!Schema::hasColumn('sequence_intervenants', 'observations_logistique')) {
                $table->text('observations_logistique')->nullable()->after('no_show');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sequence_intervenants', function (Blueprint $table) {
            $drops = [
                'role_dans_sequence','ordre_passage','duree_allouee_sec','heure_passage_prevue',
                'heure_arrive','heure_maquillage_debut','heure_maquillage_fin','heure_plateau',
                'heure_termine_passage','statut_logistique','no_show','observations_logistique',
            ];
            foreach ($drops as $c) {
                if (Schema::hasColumn('sequence_intervenants', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
