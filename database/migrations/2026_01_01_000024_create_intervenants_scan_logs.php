<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervenants_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervenant_id')->constrained('intervenants')->cascadeOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained('intervenants_qr_codes')->nullOnDelete();
            $table->foreignId('sequence_intervenant_id')->nullable()->constrained('sequence_intervenants')->nullOnDelete();
            $table->unsignedBigInteger('conducteur_id')->nullable()->index();
            $table->unsignedBigInteger('scanned_by_user_id')->nullable()->index();
            $table->string('poste', 64);
            $table->string('scan_method', 32)->default('qr_photo');
            $table->string('etat_resultat', 24)->default('ok');
            $table->timestamp('scanned_at');
            $table->json('metadata')->nullable();
            $table->string('ip_terminal', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['intervenant_id', 'scanned_at']);
            $table->index(['poste', 'scanned_at']);
            $table->index(['qr_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervenants_scan_logs');
    }
};
