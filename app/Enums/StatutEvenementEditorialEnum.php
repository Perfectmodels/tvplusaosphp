<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutEvenementEditorialEnum: string
{
    case Brouillon = 'draft';
    case EnVerification = 'in_verification';
    case Verifie = 'verified';
    case Rejete = 'rejected';
}
