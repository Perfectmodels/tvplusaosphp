<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conducteur;
use App\Models\User;

class ConducteurPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('conducteurs.view', 'web');
    }

    public function view(User $user, Conducteur $conducteur): bool
    {
        return $this->viewAny($user)
            || ($user->hasPermissionTo('conducteurs.edit_own', 'web')
                && (int) $conducteur->owner_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('conducteurs.create', 'web');
    }

    public function update(User $user, Conducteur $conducteur): bool
    {
        if ($conducteur->date_locked_at) {
            return $user->hasPermissionTo('conducteurs.edit_all', 'web');
        }

        return $user->hasPermissionTo('conducteurs.edit_all', 'web')
            || ($user->hasPermissionTo('conducteurs.edit_own', 'web')
                && (int) $conducteur->owner_id === (int) $user->id);
    }

    public function updateEnTete(User $user, Conducteur $conducteur): bool
    {
        return $this->update($user, $conducteur);
    }

    public function validateConducteur(User $user, Conducteur $conducteur): bool
    {
        return $user->hasPermissionTo('conducteurs.validate', 'web');
    }

    public function transfererVersEmission(User $user, Conducteur $conducteur): bool
    {
        return $user->hasPermissionTo('conducteurs.transfer', 'web');
    }

    public function unlock(User $user, Conducteur $conducteur): bool
    {
        return $user->hasPermissionTo('conducteurs.unlock', 'web');
    }

    public function delete(User $user, Conducteur $conducteur): bool
    {
        return $user->hasPermissionTo('conducteurs.delete', 'web');
    }

    public function reorderSequences(User $user, Conducteur $conducteur): bool
    {
        if ($conducteur->date_locked_at) {
            return $this->unlock($user, $conducteur);
        }

        return $user->hasPermissionTo('conducteurs.reorder', 'web')
            || ($user->hasPermissionTo('conducteurs.edit_own', 'web')
                && (int) $conducteur->owner_id === (int) $user->id);
    }

    public function piloterAntenne(User $user, Conducteur $conducteur): bool
    {
        return ! $conducteur->date_locked_at
            && $user->hasPermissionTo('conducteurs.pilot_air', 'web');
    }
}
