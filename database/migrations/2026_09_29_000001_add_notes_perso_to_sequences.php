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
            // Notes PERSO du présentateur — champ privé (SPEC §5.4 « Notes perso »)
            // Visible/éditable par : présentateur + chef d'édition. Les autres rôles : aucun accès.
            $table->mediumText('notes_perso')->nullable()->after('notes_presentateur');
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->dropColumn('notes_perso');
        });
    }
};
