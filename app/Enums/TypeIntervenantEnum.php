<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Type d'intervenant externe (Invité / Expert / Politique / etc.)
 * Spec §3.3 Liste + Formulaire
 */
enum TypeIntervenantEnum: string
{
    case Invite        = 'invite';
    case Expert        = 'expert';
    case Politicien    = 'politicien';
    case SocieteCivil  = 'societe_civile';
    case Artiste       = 'artiste';
    case Animateur     = 'animateur';
    case Sportif       = 'sportif';
    case Correspondant = 'correspondant';
    case Autre         = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Invite        => 'Invité plateau',
            self::Expert        => 'Expert / Analyste',
            self::Politicien    => 'Personnalité politique',
            self::SocieteCivil  => 'Société civile / ONG / Syndicat',
            self::Artiste       => 'Artiste / Chanteur / Comédien',
            self::Animateur     => 'Animateur / Présentateur (externe)',
            self::Sportif       => 'Sportif / Entraîneur',
            self::Correspondant => 'Correspondant terrain',
            self::Autre         => 'Autre profil',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Expert, self::Correspondant
                => 'bg-primary-container text-on-primary-container border-primary-container/30',
            self::Politicien  => 'bg-secondary/10 text-secondary border-secondary/30',
            self::SocieteCivil => 'bg-success/10 text-success border-success/30',
            self::Artiste, self::Sportif, self::Animateur
                => 'bg-purple-500/10 text-purple-700 border-purple-500/30',
            default => 'bg-surface-container-highest text-on-surface-variant border-outline-variant/50',
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
