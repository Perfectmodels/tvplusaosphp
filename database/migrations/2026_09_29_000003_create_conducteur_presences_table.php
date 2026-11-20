<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conducteur_presences', function (Blueprint $table) {
            $table->id();

            // Le conducteur de rédaction concerné
            $table->foreignId('conducteur_id')
                ->constrained('conducteurs')
                ->cascadeOnDelete();

            // La personne présente — INTERNE uniquement (journalistes)
            $table->foreignId('journaliste_id')
                ->constrained('journalistes')
                ->cascadeOnDelete();

            // Date + heure d'arrivée à la réunion de rédaction
            $table->dateTime('arrivee_at');

            // Qui a marqué la présence (traçabilité)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Une seule présence par personne par conducteur
            $table->unique(['conducteur_id', 'journaliste_id'], 'cp_conducteur_journaliste_unique');
            $table->index(['conducteur_id', 'arrivee_at'], 'cp_conducteur_arrivee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conducteur_presences');
    }
};
