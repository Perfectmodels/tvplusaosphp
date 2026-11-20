<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            $table->dateTime('conference_started_at')->nullable();
            $table->foreignId('conference_started_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('conducteur_presences', function (Blueprint $table) {
            $table->string('ponctualite', 20)->nullable();
            $table->dateTime('ponctualite_marked_at')->nullable();
            $table->foreignId('ponctualite_marked_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conducteur_presences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ponctualite_marked_by');
            $table->dropColumn(['ponctualite', 'ponctualite_marked_at']);
        });
        Schema::table('conducteurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conference_started_by');
            $table->dropColumn('conference_started_at');
        });
    }
};
