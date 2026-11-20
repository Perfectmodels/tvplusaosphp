<?php

namespace App\Enums;

enum StatutEtapeWorkflowEnum: string
{
    case En_attente = 'En attente';
    case En_cours = 'En cours';
    case Termine = 'Terminé';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::En_attente => 'bg-slate-100 text-slate-500 italic',
            self::En_cours => 'bg-blue-100 text-blue-800 border border-blue-200 hover:bg-blue-200',
            self::Termine => 'bg-green-100 text-green-800 border border-green-200',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::En_attente => 'fa-ellipsis',
            self::En_cours => 'fa-play',
            self::Termine => 'fa-check',
        };
    }

    public function badgeButton(bool $cliquable = true): string
    {
        $disabled = ! $cliquable ? ' opacity-60 cursor-not-allowed pointer-events-none' : '';

        $texte = match ($this) {
            self::En_attente => 'En attente',
            self::En_cours => 'Démarrer',
            self::Termine => 'Terminé',
        };

        return sprintf(
            '<button class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-semibold transition-colors %s%s"><i class="fa-solid %s text-[10px]"></i>%s</button>',
            $this->badgeClass(),
            $disabled,
            $this->badgeIcon(),
            e($texte)
        );
    }

    public function badgeSpan(): string
    {
        return sprintf(
            '<span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-semibold %s"><i class="fa-solid %s text-[10px]"></i>%s</span>',
            $this->badgeClass(),
            $this->badgeIcon(),
            e($this->label())
        );
    }
}
