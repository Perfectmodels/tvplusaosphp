<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            // Programmation au niveau de l'ÉMISSION (plus besoin de choisir une séquence)
            $table->foreignId('emission_id')
                ->nullable()
                ->after('conducteur_id')
                ->constrained('emissions')
                ->nullOnDelete();

            // Heure prévue de passage à l'antenne (repère pour le calcul du retard)
            $table->dateTime('heure_passage_prevue')->nullable()->after('eta_arrive_minutes');
        });

        // La séquence devient optionnelle : on programme un invité pour une
        // ÉMISSION, la séquence précise sera rattachée plus tard si besoin.
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->unsignedBigInteger('sequence_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('emission_id');
            $table->dropColumn('heure_passage_prevue');
        });

        Schema::table('parcours_intervenants', function (Blueprint $table) {
            $table->unsignedBigInteger('sequence_id')->nullable(false)->change();
        });
    }
};
