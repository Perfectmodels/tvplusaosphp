<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CHRONO ANTENNE — UN SEUL COMPTE À REBOURS POUR TOUTE L'ÉMISSION.
 *
 * Avant : chaque séquence avait son propre chrono (sequences.presentation_started_at).
 * Après : le présentateur démarre UN chrono pour toute l'émission ; le décompte
 *         traverse ensuite les séquences les unes après les autres, dans l'ordre.
 *
 * Les colonnes sequences.presentation_started_at / presentation_ended_at sont
 * CONSERVÉES : elles deviennent l'historique réel de passage de chaque séquence
 * (remplies automatiquement au fil du décompte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            // Départ de l'émission (segment en cours — remis à zéro à chaque pause)
            $table->timestamp('antenne_started_at')->nullable();
            // Temps cumulé AVANT le segment en cours (permet Pause / Reprise)
            $table->integer('antenne_offset_sec')->default(0);
            // Fin d'émission : fige le chrono sur la durée totale
            $table->timestamp('antenne_ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            $table->dropColumn(['antenne_started_at', 'antenne_offset_sec', 'antenne_ended_at']);
        });
    }
};
