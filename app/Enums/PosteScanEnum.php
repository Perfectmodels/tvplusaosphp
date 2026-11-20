<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Poste qui scanne le QR Code — déclenche l'étape correspondante (Spec §6.2)
 */
enum PosteScanEnum: string
{
    case Accueil   = 'accueil';
    case Maquillage = 'maquillage';
    case Plateau   = 'plateau';

    /** Scan d'INVITATION : valide la confirmation + placement (pas une étape logistique). */
    case Invitation = 'invitation';

    public function label(): string
    {
        return match ($this) {
            self::Accueil    => '🚪 Accueil — Arrivée',
            self::Maquillage => '💄 Maquillage / Habillement',
            self::Plateau    => '🎙️ Entrée plateau',
            self::Invitation => '✅ Invitation — Confirmation',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Accueil    => 'bg-blue-500/10 text-blue-700 border-blue-500/30',
            self::Maquillage => 'bg-purple-500/10 text-purple-700 border-purple-500/30',
            self::Plateau    => 'bg-success/10 text-success border-success/30',
            self::Invitation => 'bg-amber-500/10 text-amber-700 border-amber-500/30',
        };
    }

    public function badge(): string
    {
        return sprintf(
            '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border %s">%s</span>',
            $this->badgeClass(),
            $this->label()
        );
    }

    public function etapeLogistique(): string
    {
        return match ($this) {
            self::Accueil    => 'heure_arrive',
            self::Maquillage => 'maquillage_double_scan',
            self::Plateau    => 'heure_plateau',
            self::Invitation => 'confirme_at',
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
