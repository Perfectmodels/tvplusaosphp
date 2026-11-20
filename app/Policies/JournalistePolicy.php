<?php

namespace App\Policies;

use App\Models\Journaliste;
use App\Models\User;

class JournalistePolicy
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
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau', 'responsable_administratif']);
    }

    public function view(User $user, Journaliste $journaliste): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau', 'responsable_administratif']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Journaliste $journaliste): bool
    {
        return false;
    }

    public function delete(User $user, Journaliste $journaliste): bool
    {
        return false;
    }

    public function promote(User $user, Journaliste $journaliste): bool
    {
        return false;
    }

    public function demote(User $user, Journaliste $journaliste): bool
    {
        return false;
    }
}
