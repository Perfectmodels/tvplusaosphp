<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conducteurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emission_id')->constrained('emissions')->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->string('type')->default('redaction');
            $table->string('status')->default('draft');
            $table->longText('opening_text')->nullable();
            $table->longText('closing_text')->nullable();
            $table->integer('total_duration')->default(0);
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conducteurs');
    }
};
