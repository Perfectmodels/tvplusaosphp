<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervenant_checkin_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcours_intervenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('intervenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('emission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conducteur_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scan_method', 16);
            $table->string('result', 24)->index();
            $table->text('reason')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamp('validated_at')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['parcours_intervenant_id', 'scanned_at'], 'ica_parcours_scan_idx');
            $table->index(['emission_id', 'scanned_at'], 'ica_emission_scan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervenant_checkin_attempts');
    }
};
