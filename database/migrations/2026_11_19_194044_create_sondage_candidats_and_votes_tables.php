<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sondage_candidats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sondage_id')->constrained('sondages')->cascadeOnDelete();
            $table->foreignId('intervenant_id')->constrained('intervenants')->cascadeOnDelete();
            $table->string('nom', 200);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['sondage_id', 'intervenant_id'], 'sondage_candidats_unique');
            $table->index(['sondage_id', 'ordre'], 'sondage_candidats_ordre_idx');
        });

        Schema::create('vote_sondages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sondage_id')->constrained('sondages')->cascadeOnDelete();
            $table->foreignId('candidat_id')->constrained('sondage_candidats')->cascadeOnDelete();
            $table->string('voter_id', 36);
            $table->string('voter_hash', 64);
            $table->timestamps();

            $table->unique(['sondage_id', 'voter_id'], 'vote_sondages_voter_unique');
            $table->unique(['sondage_id', 'voter_hash'], 'vote_sondages_hash_unique');
            $table->index(['sondage_id', 'candidat_id'], 'vote_sondages_candidat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_sondages');
        Schema::dropIfExists('sondage_candidats');
    }
};
