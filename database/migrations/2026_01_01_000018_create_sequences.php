<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conducteur_id')->nullable()->constrained('conducteurs')->cascadeOnDelete();
            $table->foreignId('emission_id')->nullable()->constrained('emissions')->cascadeOnDelete();
            $table->foreignId('journaliste_id')->nullable()->constrained('journalistes')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('type')->default('reportage');
            $table->integer('position')->default(0);
            $table->integer('duration_seconds')->default(120);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->text('summary')->nullable();
            $table->longText('script_text')->nullable();
            $table->string('status')->default('draft');
            $table->integer('priority')->default(5);
            $table->string('video_url', 1000)->nullable();
            $table->string('live_url', 1000)->nullable();
            $table->longText('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
