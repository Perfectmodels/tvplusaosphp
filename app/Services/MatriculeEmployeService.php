<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MatriculeEmployeService
{
    public static function retenir(string $matricule): void
    {
        if (! preg_match('/^J-(\d{4})-(\d+)$/', $matricule, $matches)) {
            return;
        }

        $annee = (int) $matches[1];
        $numero = (int) $matches[2];
        DB::table('employee_number_sequences')->insertOrIgnore(['year' => $annee, 'last_number' => $numero]);
        DB::table('employee_number_sequences')->where('year', $annee)->where('last_number', '<', $numero)->update(['last_number' => $numero]);
    }

    public static function generer(?string $annee = null): string
    {
        $annee ??= now()->format('Y');
        $racine = 'J-'.$annee.'-';

        return DB::transaction(function () use ($annee, $racine): string {
            DB::table('employee_number_sequences')->insertOrIgnore(['year' => (int) $annee, 'last_number' => 0]);
            $sequence = DB::table('employee_number_sequences')->where('year', (int) $annee)->lockForUpdate()->first();
            $maximum = (int) $sequence->last_number;

            foreach (DB::table('journalistes')->where('matricule', 'like', $racine.'%')->pluck('matricule') as $matricule) {
                if (preg_match('/^'.preg_quote($racine, '/').'(\d+)$/', $matricule, $matches)) {
                    $maximum = max($maximum, (int) $matches[1]);
                }
            }

            $numero = $maximum + 1;
            DB::table('employee_number_sequences')->where('year', (int) $annee)->update(['last_number' => $numero]);

            return sprintf('%s%04d', $racine, $numero);
        }, 3);
    }
}
