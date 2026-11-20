<?php

namespace App\Observers;

use App\Models\Sequence;
use App\Services\SharedWorkspaceService;

class WorkspaceSequenceObserver
{
    public function __construct(private SharedWorkspaceService $workspace) {}

    public function saved(Sequence $sequence): void
    {
        $this->workspace->syncSequence($sequence, auth()->user());
    }

    public function deleted(Sequence $sequence): void
    {
        $this->workspace->sourceRemoved('sequence:'.$sequence->id, auth()->user());
    }
}
