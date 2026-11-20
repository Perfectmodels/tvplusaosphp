<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emissions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('jt');
            $table->integer('duration_minutes')->default(30);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('studio')->nullable();
            $table->string('status')->default('draft');
            $table->string('theme_color', 7)->nullable();
            $table->string('thumbnail', 2048)->nullable();
            $table->foreignId('chef_edition_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('presentateur_id')->nullable()->constrained('journalistes')->nullOnDelete();
            $table->integer('season')->default(1);
            $table->integer('episode')->default(1);
            $table->longText('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emissions');
    }
};
