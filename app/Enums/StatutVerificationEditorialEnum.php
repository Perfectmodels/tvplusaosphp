<?php

declare(strict_types=1);

namespace App\Enums;

enum StatutVerificationEditorialEnum: string
{
    case EnAttente = 'pending';
    case Confirmee = 'confirmed';
    case Contredite = 'refuted';
    case NonConcluante = 'inconclusive';
}
