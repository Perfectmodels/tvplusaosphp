<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Pôle / Rubrique de rattachement du journaliste
 */
enum PoleRubriqueEnum: string
{
    case Actualite     = 'actualite';
    case Politique     = 'politique';
    case Societe       = 'societe';
    case Sport         = 'sport';
    case Culture       = 'culture';
    case Economie      = 'economie';
    case Justice       = 'justice';
    case International = 'international';
    case Tech          = 'tech';
    case FaitsDivers   = 'faits_divers';
    case Magazine      = 'magazine';
    case Sante         = 'sante';
    case Environnement = 'environnement';

    /**
     * Libellé COURT (pills de filtre, badges sur les cartes).
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Actualite     => 'Actualités',
            self::Politique     => 'Politique',
            self::Societe       => 'Société',
            self::Sport         => 'Sport',
            self::Culture       => 'Culture',
            self::Economie      => 'Économie',
            self::Justice       => 'Justice',
            self::International => 'International',
            self::Tech          => 'Tech',
            self::FaitsDivers   => 'Faits divers',
            self::Magazine      => 'Magazine',
            self::Sante         => 'Santé',
            self::Environnement => 'Environnement',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Actualite     => 'Actualités / News',
            self::Politique     => 'Politique',
            self::Societe       => 'Société',
            self::Sport         => 'Sport',
            self::Culture       => 'Culture',
            self::Economie      => 'Économie / Finance',
            self::Justice       => 'Justice / Faits Judiciaires',
            self::International => 'International / Afrique / Monde',
            self::Tech          => 'Technologie / Médias / IT',
            self::FaitsDivers   => 'Faits divers',
            self::Magazine      => 'Magazine / Socio / Lifestyle',
            self::Sante         => 'Santé',
            self::Environnement => 'Environnement / Climat',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Politique, self::Justice, self::Economie
                => 'bg-secondary/10 text-secondary border-secondary/30',
            self::Sport, self::Culture, self::Magazine
                => 'bg-success/10 text-success border-success/30',
            self::Tech, self::Environnement, self::Sante
                => 'bg-primary-container text-on-primary-container border-primary-container/40',
            default => 'bg-surface-container-highest text-on-surface-variant border-outline-variant/40',
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

    /**
     * Convertit un libellé SAUVAGE (texte libre, saisie utilisateur, flux
     * scrappé, ancienne donnée « Économie », « finance », « High-Tech »…)
     * en rubrique normalisée. Retourne null si rien ne correspond :
     * le sujet reste alors « Non classé ».
     */
    public static function tryFromLoose(?string $valeur): ?self
    {
        if ($valeur === null) {
            return null;
        }

        $brut = trim($valeur);
        if ($brut === '') {
            return null;
        }

        // Valeur canonique exacte (ex: « faits_divers »)
        if ($c = self::tryFrom($brut)) {
            return $c;
        }

        // Normalisation : minuscules, sans accents, séparateurs → espaces
        $norm = mb_strtolower(\Illuminate\Support\Str::ascii($brut));
        $norm = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $norm));

        if ($norm === '') {
            return null;
        }

        $map = [
            // Actualités
            'actualite' => self::Actualite, 'actualites' => self::Actualite,
            'actualites news' => self::Actualite, 'news' => self::Actualite,
            'info' => self::Actualite, 'infos' => self::Actualite,

            // Politique
            'politique' => self::Politique, 'politiques' => self::Politique,

            // Société
            'societe' => self::Societe, 'societes' => self::Societe,
            'socio' => self::Societe, 'societe socio' => self::Societe,

            // Sport
            'sport' => self::Sport, 'sports' => self::Sport,

            // Culture
            'culture' => self::Culture, 'cultures' => self::Culture,
            'art' => self::Culture, 'arts' => self::Culture,

            // Économie / Finance
            'economie' => self::Economie, 'economies' => self::Economie,
            'economie finance' => self::Economie, 'finance' => self::Economie,
            'finances' => self::Economie, 'eco' => self::Economie,
            'business' => self::Economie,

            // Justice
            'justice' => self::Justice,
            'justice faits judiciaires' => self::Justice,
            'judiciaire' => self::Justice, 'faits judiciaires' => self::Justice,

            // International
            'international' => self::International,
            'international afrique monde' => self::International,
            'afrique' => self::International, 'monde' => self::International,
            'etranger' => self::International, 'geopolitique' => self::International,

            // Tech
            'tech' => self::Tech, 'high tech' => self::Tech, 'hightech' => self::Tech,
            'technologie' => self::Tech,
            'technologie medias it' => self::Tech,
            'medias' => self::Tech, 'it' => self::Tech,
            'numerique' => self::Tech, 'digital' => self::Tech,

            // Faits divers
            'faits divers' => self::FaitsDivers, 'fait divers' => self::FaitsDivers,

            // Magazine
            'magazine' => self::Magazine, 'lifestyle' => self::Magazine,
            'magazine socio lifestyle' => self::Magazine,

            // Santé
            'sante' => self::Sante, 'sante publique' => self::Sante,
            'health' => self::Sante, 'medical' => self::Sante,

            // Environnement
            'environnement' => self::Environnement,
            'environnement climat' => self::Environnement,
            'climat' => self::Environnement, 'ecologie' => self::Environnement,
            'environment' => self::Environnement,
        ];

        return $map[$norm] ?? null;
    }
}
