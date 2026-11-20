<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Civilités (titre de civilité) — Journalistes + Intervenants
 */
enum CiviliteEnum: string
{
    case Monsieur = 'Mr';
    case Madame   = 'Mme';
    case Mademoiselle = 'Mlle';
    case Docteur  = 'Dr';
    case Maitre   = 'Me';
    case Professeur = 'Pr';

    public function label(): string
    {
        return match ($this) {
            self::Monsieur     => 'Monsieur',
            self::Madame       => 'Madame',
            self::Mademoiselle => 'Mademoiselle',
            self::Docteur      => 'Docteur',
            self::Maitre       => 'Maître',
            self::Professeur   => 'Professeur',
        };
    }

    /** @return array<string,string> */
    public static function selectOptions(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }
        return $out;
    }
}
