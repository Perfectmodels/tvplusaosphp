<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veille_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('source_veille')->nullOnDelete();
            $table->foreignId('assigned_journaliste_id')->nullable()->constrained('journalistes')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique()->nullable();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('url', 768)->nullable()->unique();
            $table->string('image_url', 1000)->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->integer('priority')->default(5);
            $table->string('status')->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->boolean('promoted_to_conducteur')->default(false);
            $table->json('ai_angles')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veille_subjects');
    }
};
