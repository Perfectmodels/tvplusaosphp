<?php

namespace App\Policies;

use App\Models\InscriptionPersonnel;
use App\Models\User;

class InscriptionPersonnelPolicy
{
    private function managesInscriptions(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'directeur_general']);
    }

    public function view(User $user, InscriptionPersonnel $inscription): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, InscriptionPersonnel $inscription): bool
    {
        return $this->managesInscriptions($user);
    }

    public function reject(User $user, InscriptionPersonnel $inscription): bool
    {
        return $this->managesInscriptions($user);
    }
}
