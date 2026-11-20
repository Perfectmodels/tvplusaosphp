<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkspaceDossier;

class WorkspaceDossierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->checkPermissionTo('workspace.view', 'web');
    }

    public function view(User $user, WorkspaceDossier $dossier): bool
    {
        return $this->viewAny($user) && ($user->checkPermissionTo('workspace.view_all', 'web') || $dossier->hasMember($user));
    }

    private function mayWrite(User $user, WorkspaceDossier $dossier, string $permission): bool
    {
        return $this->view($user, $dossier) && ! $dossier->merged_into_id && $dossier->source_available
            && $user->checkPermissionTo($permission, 'web');
    }

    public function comment(User $user, WorkspaceDossier $dossier): bool
    {
        return $this->mayWrite($user, $dossier, 'workspace.comment');
    }

    public function createTask(User $user, WorkspaceDossier $dossier): bool
    {
        return $this->mayWrite($user, $dossier, 'workspace.create_tasks');
    }

    public function upload(User $user, WorkspaceDossier $dossier): bool
    {
        return $this->mayWrite($user, $dossier, 'workspace.upload');
    }

    public function manageMembers(User $user, WorkspaceDossier $dossier): bool
    {
        return $this->mayWrite($user, $dossier, 'workspace.manage_members');
    }

    public function changeTask(User $user, WorkspaceDossier $dossier): bool
    {
        return $this->mayWrite($user, $dossier, 'workspace.change_tasks');
    }
}
