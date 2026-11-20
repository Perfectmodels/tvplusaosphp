<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('veille_subjects', function (Blueprint $table) {
            $table->json('watch_analysis')->nullable();
            $table->timestamp('watch_analyzed_at')->nullable();
            $table->timestamp('watch_attempted_at')->nullable();
            $table->string('watch_error', 500)->nullable();
            $table->string('watch_model', 120)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('veille_subjects', function (Blueprint $table) {
            $table->dropColumn(['watch_analysis', 'watch_analyzed_at', 'watch_attempted_at', 'watch_error', 'watch_model']);
        });
    }
};
