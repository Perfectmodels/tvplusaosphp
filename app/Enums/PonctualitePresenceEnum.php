<?php

declare(strict_types=1);

namespace App\Enums;

enum PonctualitePresenceEnum: string
{
    case ALHeure = 'a_l_heure';
    case EnRetard = 'en_retard';

    public function label(): string
    {
        return match ($this) {
            self::ALHeure => 'À l’heure',
            self::EnRetard => 'En retard',
        };
    }
}
