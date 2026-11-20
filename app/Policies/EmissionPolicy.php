<?php

namespace App\Policies;

use App\Models\Emission;
use App\Models\User;

class EmissionPolicy
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
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau', 'lecture', 'directeur_general', 'chef_redaction', 'realisateur', 'correspondant', 'monteur']);
    }

    public function view(User $user, Emission $emission): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau', 'lecture', 'directeur_general', 'chef_redaction', 'realisateur', 'correspondant', 'monteur']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Emission $emission): bool
    {
        return false;
    }

    public function delete(User $user, Emission $emission): bool
    {
        return false;
    }
}
