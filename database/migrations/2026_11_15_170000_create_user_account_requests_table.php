<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_account_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('civilite', 20)->nullable();
            $table->string('prenom', 80);
            $table->string('nom', 100);
            $table->string('email', 200);
            $table->string('phone', 30)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('fonction', 120);
            $table->foreignId('journaliste_id')->nullable()->constrained('journalistes')->nullOnDelete();
            $table->string('matricule', 50)->nullable();
            $table->string('requested_access_type', 120)->nullable();
            $table->text('motivation')->nullable();
            $table->boolean('charter_accepted')->default(false);
            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_account_requests');
    }
};
