<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            $table->string('public_interaction_token', 48)->nullable()->unique();
        });

        DB::table('conducteurs')
            ->where('type', 'emission')
            ->whereNull('public_interaction_token')
            ->pluck('id')
            ->each(function (int $conducteurId): void {
                do {
                    $token = Str::random(48);
                } while (DB::table('conducteurs')->where('public_interaction_token', $token)->exists());

                DB::table('conducteurs')
                    ->where('id', $conducteurId)
                    ->update(['public_interaction_token' => $token]);
            });

        Schema::create('emission_interaction_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conducteur_id')->constrained('conducteurs')->cascadeOnDelete();
            $table->string('nom', 100)->nullable();
            $table->string('destinataire', 120)->nullable();
            $table->text('message');
            $table->timestamps();
            $table->index(['conducteur_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emission_interaction_messages');

        Schema::table('conducteurs', function (Blueprint $table) {
            $table->dropUnique(['public_interaction_token']);
            $table->dropColumn('public_interaction_token');
        });
    }
};
