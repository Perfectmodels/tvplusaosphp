<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ConducteurChanged;
use App\Models\Sequence;

final class SequenceObserver
{
    public function saved(Sequence $sequence): void
    {
        ConducteurChanged::dispatch((int) $sequence->conducteur_id);
    }

    public function deleted(Sequence $sequence): void
    {
        ConducteurChanged::dispatch((int) $sequence->conducteur_id);
    }
}
