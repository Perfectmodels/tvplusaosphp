<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Étapes du parcours intervenant, y compris les états historiques
 * Les noms historiques sont conservés pour les scans et les données existantes.
 */
enum EtapeParcoursIntervenantEnum: string
{
    case Invitation = 'invitation_envoyee';
    case Confirme = 'confirme';
    case QrEnvoye = 'qr_envoye';
    case EnChemin = 'en_chemin';
    case ArriveAccueil = 'arrive_accueil';
    case EnAttente = 'en_attente';
    case Maquillage = 'en_maquillage';
    case PretPlateau = 'pret_pour_plateau';
    case SurPlateau = 'sur_plateau';
    case Diffuse = 'diffuse_termine';

    public const PresenceConfirmee = self::Confirme;

    public const ArriveSurSite = self::ArriveAccueil;

    public const EnPreparation = self::Maquillage;

    public const EnPlateau = self::SurPlateau;

    public const PassageTermine = self::Diffuse;

    public function ordre(): int
    {
        return match ($this) {
            self::Invitation => 1,
            self::Confirme => 2,
            self::QrEnvoye => 3,
            self::EnChemin => 4,
            self::ArriveAccueil => 5,
            self::EnAttente => 6,
            self::Maquillage => 7,
            self::PretPlateau => 8,
            self::SurPlateau => 9,
            self::Diffuse => 10,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Invitation => 'Invitation envoyée',
            self::Confirme => 'Confirmé',
            self::QrEnvoye => 'QR code envoyé',
            self::EnChemin => 'En chemin',
            self::ArriveAccueil => 'Arrivé à l\'accueil',
            self::EnAttente => 'En attente',
            self::Maquillage => 'En maquillage',
            self::PretPlateau => 'Prêt plateau',
            self::SurPlateau => 'Au plateau',
            self::Diffuse => 'Passage terminé',
        };
    }

    public function labelCourt(): string
    {
        return match ($this) {
            self::Invitation => 'Invitation',
            self::Confirme => 'Confirmé',
            self::QrEnvoye => 'QR envoyé',
            self::EnChemin => 'En chemin',
            self::ArriveAccueil => 'Arrivé',
            self::EnAttente => 'En attente',
            self::Maquillage => 'Maquillage',
            self::PretPlateau => 'Prêt',
            self::SurPlateau => 'Plateau',
            self::Diffuse => 'Diffusé',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Invitation => 'fa-regular fa-envelope',
            self::Confirme => 'fa-solid fa-check-circle',
            self::QrEnvoye => 'fa-solid fa-qrcode',
            self::EnChemin => 'fa-solid fa-car',
            self::ArriveAccueil => 'fa-regular fa-user',
            self::EnAttente => 'fa-solid fa-hourglass-half',
            self::Maquillage => 'fa-solid fa-pen',
            self::PretPlateau => 'fa-solid fa-check-double',
            self::SurPlateau => 'fa-solid fa-video',
            self::Diffuse => 'fa-regular fa-circle-check',
        };
    }

    public function couleurBadgeActif(): string
    {
        return 'bg-primary-fixed text-white border-primary-fixed shadow-sm';
    }

    public function couleurBadgeFait(): string
    {
        return 'bg-primary-fixed/10 text-primary-fixed border-primary-fixed/25';
    }

    public function couleurBadgeFutur(): string
    {
        return 'bg-outline-variant/10 text-on-surface-variant border-outline-variant/30';
    }

    public function etatParDefautApres(): ?self
    {
        return match ($this) {
            self::Invitation => self::Confirme,
            self::Confirme => self::QrEnvoye,
            self::QrEnvoye => self::EnChemin,
            self::EnChemin => self::ArriveAccueil,
            self::ArriveAccueil => self::EnAttente,
            self::EnAttente => self::Maquillage,
            self::Maquillage => self::PretPlateau,
            self::PretPlateau => self::SurPlateau,
            self::SurPlateau => self::Diffuse,
            self::Diffuse => null,
        };
    }

    /** @return array<int,string> pair => [value=>label] pour selects */
    public static function selectOptions(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = sprintf('%d. %s', $c->ordre(), $c->label());
        }

        return $out;
    }

    public static function fromOrdre(int $ordre): ?self
    {
        foreach (self::cases() as $c) {
            if ($c->ordre() === $ordre) {
                return $c;
            }
        }

        return null;
    }
}
