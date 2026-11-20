<?php

use App\Enums\StatutSondageEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sondages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('emission_id')->constrained('emissions')->cascadeOnDelete();
            $table->foreignId('sequence_id')->nullable()->constrained('sequences')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question', 255);
            $table->string('token', 48)->unique();
            $table->string('statut', 16)->default(StatutSondageEnum::Brouillon->value)->index('sondages_statut_idx');
            $table->timestamp('ouvert_at')->nullable();
            $table->timestamp('clos_at')->nullable();
            $table->timestamps();

            $table->index(['emission_id', 'created_at'], 'sondages_emission_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sondages');
    }
};
