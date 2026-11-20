<?php

namespace App\Enums;

enum StatutConducteurEnum: string
{
    case Brouillon = 'Brouillon';
    case En_construction = 'En construction';
    case A_valider = 'À valider';
    case Valide = 'Validé';
    case Transfere = 'Transféré';
    case En_direct = 'En direct';
    case Cloture = 'Clôturé';
    case Archive = 'Archivé';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Brouillon => 'bg-slate-100 text-slate-700 border border-slate-200',
            self::En_construction => 'bg-blue-100 text-blue-800 border border-blue-200',
            self::A_valider => 'bg-amber-100 text-amber-800 border border-amber-200',
            self::Valide => 'bg-indigo-100 text-indigo-800 border border-indigo-200',
            self::Transfere => 'bg-purple-100 text-purple-800 border border-purple-200',
            self::En_direct => 'bg-red-100 text-red-800 border border-red-200 animate-pulse',
            self::Cloture => 'bg-gray-100 text-gray-700 border border-gray-200',
            self::Archive => 'bg-neutral-100 text-neutral-600 border border-neutral-200',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::Brouillon => 'fa-pen-to-square',
            self::En_construction => 'fa-hammer',
            self::A_valider => 'fa-eye',
            self::Valide => 'fa-circle-check',
            self::Transfere => 'fa-share',
            self::En_direct => 'fa-broadcast-tower',
            self::Cloture => 'fa-lock',
            self::Archive => 'fa-box-archive',
        };
    }

    public function badge(): string
    {
        return sprintf(
            '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold %s"><i class="fa-solid %s text-[10px]"></i>%s</span>',
            $this->badgeClass(),
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
