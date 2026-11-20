<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            // Horodatage de démarrage du compte à rebours antenne.
            // Posé quand le présentateur « pointe » la séquence (démarrage manuel).
            // Le chrono JS compare (now - presentation_started_at) à duration_seconds.
            $table->timestamp('presentation_started_at')->nullable()->after('statut_presentation');
            $table->timestamp('presentation_ended_at')->nullable()->after('presentation_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->dropColumn(['presentation_started_at', 'presentation_ended_at']);
        });
    }
};
