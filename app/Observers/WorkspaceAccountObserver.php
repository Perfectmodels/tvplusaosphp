<?php

namespace App\Observers;

use App\Models\Journaliste;
use App\Models\User;
use App\Services\SharedWorkspaceService;

class WorkspaceAccountObserver
{
    public function __construct(private SharedWorkspaceService $workspace) {}

    public function saved(User|Journaliste $account): void
    {
        if ($account instanceof User) {
            if (! $account->wasChanged('journaliste_id') && ! ($account->wasRecentlyCreated && $account->journaliste_id)) {
                return;
            }
            $ids = array_filter([$account->journaliste_id, $account->getOriginal('journaliste_id'), ...Journaliste::where('user_id', $account->id)->pluck('id')->all()]);
        } else {
            if (! $account->wasChanged('user_id') && ! ($account->wasRecentlyCreated && $account->user_id)) {
                return;
            }
            $ids = [$account->id];
        }
        $this->workspace->syncStaff($ids, auth()->user());
    }
}
