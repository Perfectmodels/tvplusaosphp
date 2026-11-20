<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Emission;
use App\Models\Sondage;
use App\Models\User;

class SondagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'chef_edition']);
    }

    public function view(User $user, Sondage $sondage): bool
    {
        return $this->manageEmission($user, $sondage->emission);
    }

    public function create(User $user, Emission $emission): bool
    {
        return $this->manageEmission($user, $emission);
    }

    public function update(User $user, Sondage $sondage): bool
    {
        return $this->manageEmission($user, $sondage->emission);
    }

    public function delete(User $user, Sondage $sondage): bool
    {
        return $this->manageEmission($user, $sondage->emission);
    }

    private function manageEmission(User $user, Emission $emission): bool
    {
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return true;
        }

        return $user->hasRole('chef_edition')
            && (int) $emission->chef_edition_id === (int) $user->id;
    }
}
