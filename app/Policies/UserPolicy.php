<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private function managesUsers(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'directeur_general']);
    }

    private function managesTarget(User $user, User $target): bool
    {
        return $this->managesUsers($user) && (! $target->hasRole('superadmin') || $user->hasRole('superadmin'));
    }

    public function viewAny(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->managesTarget($user, $target) || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $this->managesUsers($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->managesTarget($user, $target) || $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->managesTarget($user, $target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->managesTarget($user, $target);
    }

    public function toggleActive(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->managesTarget($user, $target);
    }

    public function canLogin(User $user): bool
    {
        return (bool) $user->is_active;
    }
}
