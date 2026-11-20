<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('in_verification')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('editorial_event_veille_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('editorial_event_id')->constrained('editorial_events')->cascadeOnDelete();
            $table->foreignId('veille_subject_id')->constrained('veille_subjects')->cascadeOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['editorial_event_id', 'veille_subject_id'], 'event_veille_subject_unique');
        });

        Schema::create('editorial_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('editorial_event_id')->constrained('editorial_events')->cascadeOnDelete();
            $table->string('type', 100);
            $table->text('claim');
            $table->string('status')->default('pending')->index();
            $table->longText('evidence')->nullable();
            $table->string('evidence_url', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('editorial_stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('editorial_event_id')->constrained('editorial_events')->restrictOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('angle')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('assigned_journaliste_id')->nullable()->constrained('journalistes')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('production_started_at')->nullable();
            $table->foreignId('submitted_for_validation_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_for_validation_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('verification_exception_reason')->nullable();
            $table->foreignId('verification_exception_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verification_exception_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::table('sequences', function (Blueprint $table): void {
            $table->foreignId('editorial_story_id')
                ->nullable()
                ->constrained('editorial_stories')
                ->nullOnDelete();
            $table->unique(['editorial_story_id', 'conducteur_id'], 'editorial_story_conducteur_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table): void {
            $table->dropUnique('editorial_story_conducteur_unique');
            $table->dropConstrainedForeignId('editorial_story_id');
        });

        Schema::dropIfExists('editorial_stories');
        Schema::dropIfExists('editorial_verifications');
        Schema::dropIfExists('editorial_event_veille_subject');
        Schema::dropIfExists('editorial_events');
    }
};
