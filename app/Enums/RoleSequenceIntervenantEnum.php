<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rôle d'un intervenant dans une séquence du conducteur (Spec §4.2 B)
 */
enum RoleSequenceIntervenantEnum: string
{
    case Invite        = 'invite';
    case Debatteur     = 'debatteur';
    case Correspondant = 'correspondant_duplex';
    case Presentateur  = 'presentateur_invite';
    case Jury          = 'jury';
    case Artiste       = 'artiste_invite';
    case Autre         = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Invite        => 'Invité principal',
            self::Debatteur     => 'Débatteur',
            self::Correspondant => 'Correspondant duplex / terrain',
            self::Presentateur  => 'Présentateur invité / co-animateur',
            self::Jury          => 'Membre du jury',
            self::Artiste       => 'Artiste — interprète en live',
            self::Autre         => 'Autre rôle',
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
