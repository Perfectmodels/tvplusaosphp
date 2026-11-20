<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAccountRequest;

class UserAccountRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view', 'web') || $user->hasPermissionTo('users.create', 'web');
    }

    public function view(User $user, UserAccountRequest $accountRequest): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, UserAccountRequest $accountRequest): bool
    {
        return $accountRequest->isPending() && $user->hasPermissionTo('users.create', 'web');
    }

    public function reject(User $user, UserAccountRequest $accountRequest): bool
    {
        return $accountRequest->isPending() && $user->hasPermissionTo('users.create', 'web');
    }

    public function delete(User $user, UserAccountRequest $accountRequest): bool
    {
        return $user->hasPermissionTo('users.delete', 'web');
    }
}
