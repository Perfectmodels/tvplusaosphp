<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise les valeurs héritées de la colonne `civilite` (journalistes + intervenants).
 *
 * La base contient « MR », « MME », « MLLE »… qui ne correspondent à aucune valeur
 * de App\Enums\CiviliteEnum (« Mr », « Mme », « Mlle »…) : chaque lecture levait un
 * ValueError et provoquait une erreur 500 sur les pages émissions.
 *
 * Portable (MySQL + SQLite) : backfill en PHP, pas de SQL spécifique.
 */
return new class extends Migration
{
    /**
     * Variantes héritées (après trim + suppression du point final) → valeur canonique.
     *
     * @var array<string, string>
     */
    private const VARIANTES = [
        'M' => 'Mr',
        'MADAME' => 'Mme',
        'MADEMOISELLE' => 'Mlle',
        'MAITRE' => 'Me',
        'MAÎTRE' => 'Me',
        'MME' => 'Mme',
        'MLLE' => 'Mlle',
        'MONSIEUR' => 'Mr',
        'DOCTEUR' => 'Dr',
        'DR' => 'Dr',
        'ME' => 'Me',
        'MR' => 'Mr',
        'PR' => 'Pr',
        'PROFESSEUR' => 'Pr',
    ];

    public function up(): void
    {
        foreach (['journalistes', 'intervenants'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'civilite')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('civilite')
                ->where('civilite', '<>', '')
                ->orderBy('id')
                ->get(['id', 'civilite'])
                ->each(function ($row) use ($table): void {
                    $brut = trim((string) $row->civilite);
                    if ($brut === '') {
                        return;
                    }
                    if (App\Enums\CiviliteEnum::tryFrom($brut) !== null) {
                        return; // déjà canonique
                    }
                    $canonique = self::VARIANTES[rtrim(mb_strtoupper($brut), '.')] ?? null;
                    if ($canonique !== null) {
                        DB::table($table)->where('id', $row->id)->update(['civilite' => $canonique]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Aucun retour arrière : les variantes héritées étaient invalides.
    }
};
