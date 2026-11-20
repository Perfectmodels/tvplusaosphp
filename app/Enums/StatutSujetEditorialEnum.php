<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutSujetEditorialEnum: string
{
    case Brouillon = 'draft';
    case Affecte = 'assigned';
    case EnProduction = 'in_production';
    case AValider = 'awaiting_validation';
    case Valide = 'validated';
    case Rejete = 'rejected';
}
