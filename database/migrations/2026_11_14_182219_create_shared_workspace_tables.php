<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_dossiers', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->foreignId('editorial_story_id')->nullable()->unique()->constrained('editorial_stories')->nullOnDelete();
            $table->foreignId('sequence_id')->nullable()->unique()->constrained('sequences')->nullOnDelete();
            $table->foreignId('merged_into_id')->nullable()->constrained('workspace_dossiers')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('workspace_dossier_user', function (Blueprint $table): void {
            $table->foreignId('workspace_dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_creator')->default(false);
            $table->boolean('is_assignee')->default(false);
            $table->boolean('is_invited')->default(false);
            $table->timestamps();
            $table->primary(['workspace_dossier_id', 'user_id']);
        });
        Schema::create('workspace_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_dossier_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('type', 32);
            $table->string('status', 32)->default('todo');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status', 'due_at']);
        });
        Schema::create('workspace_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('workspace_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('workspace_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('kind', 64);
            $table->string('message', 500);
            $table->timestamps();
            $table->index(['workspace_dossier_id', 'id']);
        });
        Schema::create('workspace_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
        Schema::table('digital_publications', function (Blueprint $table): void {
            $table->foreignId('editorial_story_id')->nullable()->constrained('editorial_stories')->nullOnDelete();
            $table->foreignId('source_sequence_id')->nullable()->constrained('sequences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('digital_publications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_sequence_id');
            $table->dropConstrainedForeignId('editorial_story_id');
        });
        foreach (['workspace_notifications', 'workspace_activities', 'workspace_attachments', 'workspace_comments', 'workspace_tasks', 'workspace_dossier_user', 'workspace_dossiers'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
