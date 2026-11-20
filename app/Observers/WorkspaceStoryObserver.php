<?php

namespace App\Observers;

use App\Models\EditorialStory;
use App\Services\SharedWorkspaceService;

class WorkspaceStoryObserver
{
    public function __construct(private SharedWorkspaceService $workspace) {}

    public function saved(EditorialStory $story): void
    {
        $this->workspace->syncStory($story, auth()->user());
    }

    public function deleted(EditorialStory $story): void
    {
        $this->workspace->sourceRemoved('story:'.$story->id, auth()->user());
    }
}
