<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emission_journaliste', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emission_id')->constrained('emissions')->cascadeOnDelete();
            $table->foreignId('journaliste_id')->constrained('journalistes')->cascadeOnDelete();
            $table->string('role', 40)->default(\App\Enums\RolePresentateurEmissionEnum::Principal->value);
            $table->string('statut_affectation', 30)->default('En_attente');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['emission_id', 'journaliste_id'], 'uniq_emission_journaliste');
            $table->index(['emission_id', 'role', 'ordre'], 'idx_emission_role_ordre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emission_journaliste');
    }
};
