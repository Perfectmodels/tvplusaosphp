<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VeilleRemix;

class VeilleRemixPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Super Admin', 'admin', 'superadmin', 'chef_edition'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'habilleur', 'Super Admin', 'admin', 'superadmin', 'chef_edition']);
    }

    public function view(User $user, VeilleRemix $remix): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'habilleur', 'Super Admin', 'admin', 'superadmin', 'chef_edition']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, VeilleRemix $remix): bool
    {
        return false;
    }

    public function delete(User $user, VeilleRemix $remix): bool
    {
        return false;
    }
}
