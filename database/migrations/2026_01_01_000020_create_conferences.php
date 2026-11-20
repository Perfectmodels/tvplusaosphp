<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conferences', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique()->nullable();
            $table->string('type')->default('redaction');
            $table->timestamp('scheduled_at')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->string('location')->nullable();
            $table->string('video_conference_url', 1000)->nullable();
            $table->string('status')->default('scheduled');
            $table->text('summary')->nullable();
            $table->json('agenda')->nullable();
            $table->longText('outcome')->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_emission_id')->nullable()->constrained('emissions')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conferences');
    }
};
