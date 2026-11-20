<?php

namespace App\Enums;

enum StatutEmissionEnum: string
{
    case Brouillon = 'Brouillon';
    case Planifiee = 'Planifiée';
    case En_cours = 'En cours';
    case Diffusee = 'Diffusée';
    case Archivee = 'Archivée';
    case Annulee = 'Annulée';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Brouillon => 'bg-slate-100 text-slate-700 border border-slate-200',
            self::Planifiee => 'bg-blue-100 text-blue-800 border border-blue-200',
            self::En_cours => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            self::Diffusee => 'bg-purple-100 text-purple-800 border border-purple-200',
            self::Archivee => 'bg-gray-100 text-gray-700 border border-gray-200',
            self::Annulee => 'bg-red-100 text-red-800 border border-red-200',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::Brouillon => 'fa-pen-to-square',
            self::Planifiee => 'fa-calendar-check',
            self::En_cours => 'fa-circle-dot',
            self::Diffusee => 'fa-check-circle',
            self::Archivee => 'fa-box-archive',
            self::Annulee => 'fa-xmark-circle',
        };
    }

    public function badge(): string
    {
        $pulse = $this === self::En_cours ? ' animate-pulse' : '';

        return sprintf(
            '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold %s%s"><i class="fa-solid %s text-[10px]"></i>%s</span>',
            $this->badgeClass(),
            $pulse,
            $this->badgeIcon(),
            e($this->label())
        );
    }

    public static function selectOptions(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }
}
