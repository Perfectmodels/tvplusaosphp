<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutSondageEnum: string
{
    case Brouillon = 'brouillon';
    case Ouvert = 'ouvert';
    case Clos = 'clos';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Ouvert => 'Ouvert au vote',
            self::Clos => 'Clos',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Brouillon => 'badge badge-blue',
            self::Ouvert => 'badge badge-green',
            self::Clos => 'badge badge-red',
        };
    }
}
