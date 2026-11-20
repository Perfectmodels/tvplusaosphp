<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\CiviliteEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast tolérant pour CiviliteEnum (journalistes + intervenants).
 *
 * Contexte : la base contient des valeurs héritées (MR, MME, MLLE…) qui ne
 * correspondent à aucune valeur de l'enum. Avec un cast strict
 * `CiviliteEnum::class`, chaque lecture levait un ValueError et provoquait une
 * erreur 500 sur les pages émissions (index/show des conducteurs d'émission).
 *
 * - En écriture : normalise les variantes héritées vers la valeur canonique
 *   de l'enum, pour que toute nouvelle écriture soit valide.
 * - En lecture : une valeur inconnue est normalisée si possible, sinon
 *   renvoyée à null — plus aucune page ne casse sur une donnée héritée.
 */
final class CiviliteCast implements CastsAttributes
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

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CiviliteEnum
    {
        $canonique = $this->versValeurCanonique($value);

        return $canonique !== null ? CiviliteEnum::tryFrom($canonique) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->versValeurCanonique($value);
    }

    /**
     * Traduit n'importe quelle entrée (enum, variante héritée, casse libre)
     * vers la valeur canonique de l'enum, ou null si intraduisible.
     */
    private function versValeurCanonique(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CiviliteEnum) {
            return $value->value;
        }

        $brut = trim((string) $value);
        if ($brut === '') {
            return null;
        }

        if (CiviliteEnum::tryFrom($brut) !== null) {
            return $brut;
        }

        $maj = rtrim(mb_strtoupper($brut), '.');

        return self::VARIANTES[$maj] ?? null;
    }
}
