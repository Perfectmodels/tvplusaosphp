<?php

namespace App\Enums;

enum TypeEmissionEnum: string
{
    case JT = 'JT — Journal télévisé';
    case Mag = 'Mag — Magazine';
    case Talk = 'Talk — Talk-show';
    case Debat = 'Débat — Débat / Table ronde';
    case Sport = 'Sport — Magazine sportif';
    case Culture = 'Culture — Magazine culturel';
    case Radio = 'Radio — Programme radio';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::JT => 'bg-red-100 text-red-800 border border-red-200',
            self::Mag => 'bg-orange-100 text-orange-800 border border-orange-200',
            self::Talk => 'bg-indigo-100 text-indigo-800 border border-indigo-200',
            self::Debat => 'bg-rose-100 text-rose-800 border border-rose-200',
            self::Sport => 'bg-cyan-100 text-cyan-800 border border-cyan-200',
            self::Culture => 'bg-violet-100 text-violet-800 border border-violet-200',
            self::Radio => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::JT => 'fa-newspaper',
            self::Mag => 'fa-book-open',
            self::Talk => 'fa-comments',
            self::Debat => 'fa-people-group',
            self::Sport => 'fa-futbol',
            self::Culture => 'fa-palette',
            self::Radio => 'fa-microphone-lines',
        };
    }

    public function badge(): string
    {
        $label = preg_replace('/^[^\s]+\s+/u', '', $this->label());
        return sprintf(
            '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold %s"><i class="fa-solid %s text-[10px]"></i>%s</span>',
            $this->badgeClass(),
            $this->badgeIcon(),
            e($label)
        );
    }

    public static function selectOptions(): array
    {
        $options = [];
        foreach (self::cases() as $c) {
            $label = preg_replace('/^[^\s]+\s+/u', '', $c->label());
            $options[$c->value] = $label;
        }
        return $options;
    }
}
