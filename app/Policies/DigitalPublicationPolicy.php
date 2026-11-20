<?php

namespace App\Policies;

use App\Models\DigitalPublication;
use App\Models\User;

class DigitalPublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'directeur_general', 'chef_redaction', 'chef_edition', 'community_manager']);
    }

    public function view(User $user, DigitalPublication $publication): bool
    {
        return $this->viewAny($user) && ($this->approve($user, $publication) || $user->hasRole('directeur_general') || $publication->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'community_manager']);
    }

    public function update(User $user, DigitalPublication $publication): bool
    {
        return $this->create($user) && ($publication->created_by === $user->id || $user->hasAnyRole(['admin', 'superadmin'])) && in_array($publication->status, ['draft', 'changes_requested'], true);
    }

    public function approve(User $user, DigitalPublication $publication): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'chef_redaction', 'chef_edition']);
    }

    public function publish(User $user, DigitalPublication $publication): bool
    {
        return $this->create($user) && ($publication->created_by === $user->id || $user->hasAnyRole(['admin', 'superadmin']));
    }
}
