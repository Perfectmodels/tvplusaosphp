<?php

namespace App\Policies;

use App\Models\SourceVeille;
use App\Models\User;

class SourceVeillePolicy
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
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau', 'lecture']);
    }

    public function view(User $user, SourceVeille $sourceveille): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SourceVeille $sourceveille): bool
    {
        return false;
    }

    public function delete(User $user, SourceVeille $sourceveille): bool
    {
        return false;
    }

    public function scrape(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'chef_edition']);
    }
}
