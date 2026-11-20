<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Langues parlées par un intervenant (multi-select JSON array)
 */
enum LangueEnum: string
{
    case Francais  = 'fr';
    case Anglais   = 'en';
    case Espagnol  = 'es';
    case Portugais = 'pt';
    case Arabe     = 'ar';
    case Chinois   = 'zh';
    case Lingala   = 'ln';
    case Swahili   = 'sw';
    case Wolof     = 'wo';
    case Pidgin    = 'pdo';

    public function label(): string
    {
        return match ($this) {
            self::Francais  => 'Français',
            self::Anglais   => 'Anglais',
            self::Espagnol  => 'Espagnol',
            self::Portugais => 'Portugais',
            self::Arabe     => 'Arabe',
            self::Chinois   => 'Chinois',
            self::Lingala   => 'Lingala',
            self::Swahili   => 'Swahili',
            self::Wolof     => 'Wolof',
            self::Pidgin    => 'Pidgin anglais ( Nigeria / Ghana )',
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
