<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_intervenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->foreignId('intervenant_id')->constrained('intervenants')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->string('time_slot')->nullable();
            $table->string('status')->default('invité');
            $table->text('notes')->nullable();
            $table->timestamp('heure_arrive')->nullable()->comment('Scan Poste Accueil');
            $table->timestamp('heure_maq_debut')->nullable()->comment('Scan Poste Maquillage Début');
            $table->timestamp('heure_maq_fin')->nullable()->comment('Scan Poste Maquillage Fin');
            $table->timestamp('heure_plateau')->nullable()->comment('Scan Poste Plateau');
            $table->timestamp('heure_diffusion')->nullable()->comment('Heure passage effectif');
            $table->unsignedBigInteger('scanned_by_user_id')->nullable()->comment('Dernier user qui a scanne');
            $table->timestamps();

            $table->unique(['sequence_id', 'intervenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_intervenants');
    }
};
