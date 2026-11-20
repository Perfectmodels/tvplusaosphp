<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            $table->foreignId('presentateur_id')
                ->nullable()
                ->after('date_edition')
                ->constrained('intervenants')
                ->nullOnDelete();
            $table->foreignId('active_sequence_id')
                ->nullable()
                ->after('presentateur_id')
                ->constrained('sequences')
                ->nullOnDelete();
        });

        Schema::table('sequences', function (Blueprint $table) {
            $table->mediumText('notes_presentateur')->nullable()->after('alerte_info');
            $table->string('statut_presentation', 20)->default('Avenir')->after('notes_presentateur');
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->dropColumn(['notes_presentateur', 'statut_presentation']);
        });

        Schema::table('conducteurs', function (Blueprint $table) {
            $table->dropForeign(['presentateur_id']);
            $table->dropForeign(['active_sequence_id']);
            $table->dropColumn(['presentateur_id', 'active_sequence_id']);
        });
    }
};
