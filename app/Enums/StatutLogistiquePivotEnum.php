<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut logistique complet d'un intervenant CONVIÉ dans le tableau de suivi
 * (Spec §5.2 colonne 8 État global + colonne 5,6,7 étapes)
 */
enum StatutLogistiquePivotEnum: string
{
    case Programme   = 'programme';
    case Arrive      = 'arrive';
    case Maquillage  = 'en_maquillage';
    case Pret        = 'pret_pour_plateau';
    case SurPlateau  = 'sur_plateau';
    case Termine     = 'termine';
    case EnRetard    = 'en_retard';
    case NoShow      = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Programme  => '⏳ Programmé — en attente',
            self::Arrive     => '🚪 Arrivé',
            self::Maquillage => '💄 Maquillage en cours',
            self::Pret       => '✅ Prêt pour plateau',
            self::SurPlateau => '🎙️ Sur plateau',
            self::Termine    => '⚪ Passage terminé',
            self::EnRetard   => '🟧 En retard',
            self::NoShow     => '🟥 No-show — absent',
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
