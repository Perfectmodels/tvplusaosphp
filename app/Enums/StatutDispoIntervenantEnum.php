<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut disponibilité d'un intervenant externe (Spec §3.3)
 */
enum StatutDispoIntervenantEnum: string
{
    case Disponible = 'disponible';
    case Occupe     = 'occupe';
    case Interdit   = 'interdit';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => '🟢 Disponible',
            self::Occupe     => '🟡 Occupé / Indisponible provisoire',
            self::Interdit   => '🔴 Interdit (officieux — blacklist)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Disponible => 'bg-success/10 text-success border-success/30',
            self::Occupe     => 'bg-warning/10 text-warning border-warning/30',
            self::Interdit   => 'bg-error/10 text-error border-error/30',
        };
    }

    /** @return array<string,string> */
    public static function selectOptions(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = preg_replace('/^[^\s]+\s+/u', '', $c->label());
        }
        return $out;
    }
}
