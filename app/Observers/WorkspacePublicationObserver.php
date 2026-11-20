<?php

namespace App\Observers;

use App\Models\DigitalPublication;
use App\Services\SharedWorkspaceService;

class WorkspacePublicationObserver
{
    public function __construct(private SharedWorkspaceService $workspace) {}

    public function saved(DigitalPublication $publication): void
    {
        $this->workspace->publicationChanged($publication, auth()->user());
    }

    public function deleted(DigitalPublication $publication): void
    {
        $this->workspace->publicationChanged($publication, auth()->user());
    }
}
