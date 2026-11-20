<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout colonne `livre_at` datetime nullable dans table `sequences`
 * But: enregistrer L'HEURE RÉELLE où un sujet a été marqué "Livraison = Terminé"
 * (affiché dans le VRAI Conducteur Rédaction, col #10 "Livré le")
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sequences', 'livre_at')) {
            Schema::table('sequences', function (Blueprint $table) {
                $table->timestamp('livre_at')
                    ->nullable()
                    ->after('heure_livraison_prevue')
                    ->comment('Heure réelle de livraison (statut_livraison=Terniné) — NULL tant que pas livré');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sequences', 'livre_at')) {
            Schema::table('sequences', function (Blueprint $table) {
                $table->dropColumn('livre_at');
            });
        }
    }
};
