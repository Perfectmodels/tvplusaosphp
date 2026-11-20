<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut RH d'un journaliste
 */
enum StatutJournalisteEnum: string
{
    case Actif     = 'actif';
    case Conge     = 'conge';
    case Mission   = 'en_mission';
    case Suspendu  = 'suspendu';
    case Demissionne = 'demissionne';
    case Inactif   = 'inactif';

    public function label(): string
    {
        return match ($this) {
            self::Actif       => '🟢 Actif',
            self::Conge       => '🟡 En congé',
            self::Mission     => '🟡 En mission terrain',
            self::Suspendu    => '🟠 Suspendu',
            self::Demissionne => '⚪ Démissionné',
            self::Inactif     => '🔴 Inactif',
        };
    }

    public function couleurBadge(): string
    {
        return match ($this) {
            self::Actif      => 'bg-success/10 text-success border-success/40',
            self::Conge, self::Mission => 'bg-warning/10 text-warning border-warning/40',
            self::Suspendu   => 'bg-orange-500/10 text-orange-700 border-orange-500/40',
            self::Demissionne => 'bg-surface-container-highest text-on-surface-variant border-outline-variant',
            self::Inactif    => 'bg-error/10 text-error border-error/40',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Actif      => 'badge badge-green',
            self::Conge, self::Mission => 'badge badge-purple',
            self::Suspendu   => 'badge badge-orange',
            self::Demissionne => 'badge badge-gray',
            self::Inactif    => 'badge badge-red',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::Actif      => 'fa-solid fa-circle-check',
            self::Conge      => 'fa-solid fa-umbrella-beach',
            self::Mission    => 'fa-solid fa-plane',
            self::Suspendu   => 'fa-solid fa-triangle-exclamation',
            self::Demissionne => 'fa-solid fa-right-from-bracket',
            self::Inactif    => 'fa-solid fa-circle-xmark',
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
