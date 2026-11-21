<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conducteur;

final class ConducteurCalculatorService
{
    public static function recalculerHeures(Conducteur $conducteur): void
    {
        $heureDebutEmission = self::parseHeureDebutEmission($conducteur);
        $cumulSec = 0;

        foreach ($conducteur->sequences()->orderBy('position')->get() as $seq) {
            $debutSec = $heureDebutEmission + $cumulSec;
            $duree = max(0, (int) ($seq->duration_seconds ?? 120));
            $finSec = $debutSec + $duree;

            $heureDebut = self::secToTime($debutSec);
            $heureFin = self::secToTime($finSec);
            if ((string) $seq->heure_debut_calculee !== $heureDebut
                || (string) $seq->heure_fin_calculee !== $heureFin) {
                $seq->timestamps = false;
                $seq->updateQuietly([
                    'heure_debut_calculee' => $heureDebut,
                    'heure_fin_calculee' => $heureFin,
                ]);
            }

            $cumulSec += $duree;
        }

        if ((int) $conducteur->total_duration !== $cumulSec) {
            $conducteur->timestamps = false;
            $conducteur->updateQuietly(['total_duration' => $cumulSec]);
        }
    }

    public static function formatDuree(int $secondes): string
    {
        if ($secondes <= 0) {
            return '00:00';
        }
        $heures = (int) floor($secondes / 3600);
        $reste  = $secondes - ($heures * 3600);
        $min    = (int) floor($reste / 60);
        $sec    = $reste % 60;

        if ($heures > 0) {
            return sprintf('%02d:%02d:%02d', $heures, $min, $sec);
        }

        return sprintf('%02d:%02d', $min, $sec);
    }

    public static function formatHeure(?string $timeSql): string
    {
        if (! $timeSql) {
            return '--:--:--';
        }

        return $timeSql;
    }

    private static function parseHeureDebutEmission(Conducteur $conducteur): int
    {
        if ($conducteur->heure_debut_emission_calc !== null) {
            return self::timeToSec($conducteur->heure_debut_emission_calc);
        }

        try {
            if ($conducteur->emission && $conducteur->emission->heure_debut) {
                $val = $conducteur->emission->heure_debut;
                if (is_string($val)) {
                    return self::timeToSec($val);
                }
                if ($val instanceof \DateTimeInterface) {
                    return (int) $val->format('H') * 3600 + (int) $val->format('i') * 60 + (int) $val->format('s');
                }
            }
        } catch (\Throwable) {
        }

        return 12 * 3600;
    }

    private static function timeToSec(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));
        $h = $parts[0] ?? 0;
        $m = $parts[1] ?? 0;
        $s = $parts[2] ?? 0;

        return $h * 3600 + $m * 60 + $s;
    }

    private static function secToTime(int $sec): string
    {
        $sec = max(0, $sec % (24 * 3600));
        $h   = (int) floor($sec / 3600);
        $r   = $sec % 3600;
        $m   = (int) floor($r / 60);
        $s   = $r % 60;

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}
