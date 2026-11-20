<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VeilleSubject;

class VeilleSubjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Super Admin', 'admin', 'chef_edition', 'superadmin', 'chef_redaction'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'habilleur', 'correspondant', 'Super Admin', 'admin', 'chef_edition']);
    }

    public function view(User $user, VeilleSubject $subject): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'habilleur', 'correspondant', 'Super Admin', 'admin', 'chef_edition']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, VeilleSubject $subject): bool
    {
        return false;
    }

    public function delete(User $user, VeilleSubject $subject): bool
    {
        return false;
    }
}
