<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veille_remixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained('veille_subjects')->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('source_veille')->nullOnDelete();
            $table->string('type', 30)->default('reformulate');
            $table->string('title')->nullable();
            $table->longText('content');
            $table->string('format')->nullable();
            $table->string('language', 5)->default('fr');
            $table->string('ai_model')->nullable();
            $table->longText('prompt_used')->nullable();
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veille_remixes');
    }
};
