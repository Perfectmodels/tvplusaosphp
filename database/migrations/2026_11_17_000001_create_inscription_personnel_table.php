<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscription_personnel', function (Blueprint $table) {
            $table->id();
            $table->string('civilite', 8)->nullable();
            $table->string('prenom', 100);
            $table->string('nom', 100);
            $table->string('email', 200)->unique();
            $table->string('telephone', 30)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('fonction', 100)->nullable();
            $table->text('motivation')->nullable();
            $table->enum('statut', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscription_personnel');
    }
};
