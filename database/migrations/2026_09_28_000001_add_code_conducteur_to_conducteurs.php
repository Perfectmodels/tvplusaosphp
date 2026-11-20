<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            // Identifiant métier unique : CR-2026-0001 (Conducteur de Rédaction) / CE-2026-0001 (Conducteur d'Émission)
            $table->string('code_conducteur', 24)->nullable()->unique()->after('type');
        });

        // --- Backfill des lignes existantes ---
        // Préfixe = CR si type='redaction', sinon CE (tous les autres types sont des conducteurs d'émission)
        $annee = date('Y');
        $compteurs = ['CR' => 0, 'CE' => 0];

        DB::table('conducteurs')
            ->whereNull('code_conducteur')
            ->orderBy('id')
            ->get(['id', 'type'])
            ->each(function ($row) use (&$compteurs, $annee) {
                $prefixe = ((string) $row->type === 'redaction') ? 'CR' : 'CE';
                $compteurs[$prefixe]++;
                DB::table('conducteurs')
                    ->where('id', (int) $row->id)
                    ->update(['code_conducteur' => sprintf('%s-%s-%04d', $prefixe, $annee, $compteurs[$prefixe])]);
            });
    }

    public function down(): void
    {
        Schema::table('conducteurs', function (Blueprint $table) {
            $table->dropUnique(['code_conducteur']);
            $table->dropColumn('code_conducteur');
        });
    }
};
