<?php

namespace App\Enums;

enum StatutPresentationSequenceEnum: string
{
    case Avenir = 'Avenir';
    case EnCours = 'EnCours';
    case Termine = 'Termine';

    public function label(): string
    {
        return match ($this) {
            self::Avenir => 'À venir',
            self::EnCours => 'En cours',
            self::Termine => 'Terminée',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Avenir => 'bg-surface-container-highest text-on-surface-variant border border-outline/20',
            self::EnCours => 'bg-primary-fixed/40 text-primary border border-primary/30 tv-live-blink',
            self::Termine => 'bg-green-100 text-green-800 border border-green-200',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::Avenir => 'fa-regular fa-clock',
            self::EnCours => 'fa-solid fa-circle-play',
            self::Termine => 'fa-solid fa-check',
        };
    }

    public function badge(): string
    {
        return sprintf(
            '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold %s">
                <i class="%s text-[10px]"></i>%s</span>',
            $this->badgeClass(),
            $this->badgeIcon(),
            e($this->label())
        );
    }

    public function rowClass(): string
    {
        return match ($this) {
            self::Avenir => '',
            self::EnCours => 'bg-primary-fixed/30 border-l-4 border-primary-container',
            self::Termine => 'opacity-70',
        };
    }
}
