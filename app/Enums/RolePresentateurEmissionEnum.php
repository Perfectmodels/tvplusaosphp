<?php

namespace App\Enums;

enum RolePresentateurEmissionEnum: string
{
    case Principal = 'Principal (titulaire)';
    case Remplacant = '2nd / Remplaçant';
    case InviteRegulier = 'Invité régulier';

    public function label(): string
    {
        return $this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Principal => 'bg-blue-100 text-blue-800 border border-blue-200',
            self::Remplacant => 'bg-orange-100 text-orange-800 border border-orange-200',
            self::InviteRegulier => 'bg-violet-100 text-violet-800 border border-violet-200',
        };
    }

    public function badgeIcon(): string
    {
        return match ($this) {
            self::Principal => 'fa-user-tie',
            self::Remplacant => 'fa-user-group',
            self::InviteRegulier => 'fa-user-plus',
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
            $options[$c->value] = $c->label();
        }
        return $options;
    }
}
