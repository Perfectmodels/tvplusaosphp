<?php

declare(strict_types=1);

namespace App\Enums;

enum NiveauAlerteEnum: string
{
    case Info = 'info';
    case Avertissement = 'avertissement';
    case Urgence = 'urgence';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Avertissement => 'Avertissement',
            self::Urgence => 'Urgence',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Info => 'badge-blue',
            self::Avertissement => 'badge-orange',
            self::Urgence => 'badge-red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info => 'fa-solid fa-circle-info',
            self::Avertissement => 'fa-solid fa-triangle-exclamation',
            self::Urgence => 'fa-solid fa-bell',
        };
    }
}
