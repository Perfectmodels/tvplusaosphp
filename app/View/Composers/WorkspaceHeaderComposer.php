<?php

namespace App\View\Composers;

use App\Services\SharedWorkspaceService;
use Illuminate\View\View;

class WorkspaceHeaderComposer
{
    public function __construct(private SharedWorkspaceService $workspace) {}

    public function compose(View $view): void
    {
        $view->with('workspaceUnread', auth()->check() ? $this->workspace->unreadCount(auth()->user()) : 0);
    }
}
