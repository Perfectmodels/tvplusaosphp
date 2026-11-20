<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fonction / Poste d'un Journaliste interne TV+AOS
 */
enum FonctionJournalisteEnum: string
{
    case Journaliste    = 'journaliste';
    case JRI            = 'jri';
    case Redacteur      = 'redacteur';
    case ChefRubrique   = 'chef_rubrique';
    case ChefEdition    = 'chef_edition';
    case DirecteurRedaction = 'directeur_redaction';
    case Pigiste        = 'pigiste';
    case Stagiaire      = 'stagiaire';

    public function label(): string
    {
        return match ($this) {
            self::Journaliste         => 'Journaliste',
            self::JRI                 => 'JRI (Journaliste Reporter d\'Images)',
            self::Redacteur           => 'Rédacteur / Rédactrice',
            self::ChefRubrique        => 'Chef / Cheffe de rubrique',
            self::ChefEdition         => 'Chef / Cheffe d\'édition',
            self::DirecteurRedaction  => 'Directeur / Directrice de la rédaction',
            self::Pigiste             => 'Pigiste',
            self::Stagiaire           => 'Stagiaire',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ChefRubrique, self::ChefEdition, self::DirecteurRedaction
                => 'badge badge-indigo',
            self::Journaliste, self::JRI, self::Redacteur
                => 'badge badge-blue',
            self::Pigiste             => 'badge badge-orange',
            self::Stagiaire           => 'badge badge-gray',
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
