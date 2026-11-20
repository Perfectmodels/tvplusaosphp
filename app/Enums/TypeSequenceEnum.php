<?php

namespace App\Enums;

enum TypeSequenceEnum: string
{
    case Ouverture = 'OUVERTURE';
    case Generique = 'GÉNÉRIQUE';
    case Titres = 'TITRES';
    case Sujet = 'SUJET';
    case Reportage = 'REPORTAGE';
    case Interview = 'INTERVIEW';
    case Invite_plateau = 'INVITÉ PLATEAU';
    case Duplex = 'DUPLEX';
    case Debat = 'DÉBAT';
    case Chronique = 'CHRONIQUE';
    case Breve = 'BRÈVE';
    case Meteo = 'MÉTÉO';
    case Agenda = 'AGENDA';
    case Alerte_info = 'ALERTE INFO';
    case Annonce = 'ANNONCE';
    case Publicite = 'PUBLICITÉ';
    case Conclusion = 'CONCLUSION';
    case Autre = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::Breve => 'Brève Capsule',
            default      => $this->value,
        };
    }

    public static function redactionCases(): array
    {
        return [
            self::Reportage,
            self::Duplex,
            self::Chronique,
            self::Breve,
            self::Debat,
            self::Interview,
        ];
    }

    public static function selectOptionsRedaction(): array
    {
        $cases = self::redactionCases();
        return array_combine(
            array_map(fn ($c) => $c->value, $cases),
            array_map(fn ($c) => $c->label(), $cases)
        );
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Ouverture, self::Generique, self::Titres, self::Annonce => 'badge badge-blue',
            self::Sujet, self::Interview, self::Invite_plateau, self::Debat => 'badge badge-blue',
            self::Reportage, self::Duplex, self::Meteo, self::Agenda => 'badge badge-green',
            self::Chronique => 'badge badge-orange',
            self::Breve, self::Alerte_info => 'badge badge-red',
            self::Publicite => 'bg-yellow-100 text-yellow-800 border border-yellow-200 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold',
            self::Conclusion, self::Autre => 'badge badge-blue',
        };
    }

    public function badge(): string
    {
        return sprintf(
            '<span class="%s">%s</span>',
            $this->badgeClass(),
            e($this->label())
        );
    }

    public static function selectOptions(): array
    {
        return array_combine(
            array_map(fn ($c) => $c->value, self::cases()),
            array_map(fn ($c) => $c->label(), self::cases())
        );
    }
}
