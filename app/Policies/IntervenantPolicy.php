<?php

namespace App\Policies;

use App\Models\Intervenant;
use App\Models\User;

class IntervenantPolicy
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
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau']);
    }

    public function view(User $user, Intervenant $intervenant): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau']);
    }

    /**
     * Scanner le QR d'un intervenant (postes Invitation / Accueil / Maquillage
     * / Plateau) : réservé aux utilisateurs qui en ont les droits — c'est ce
     * scan qui valide l'arrivée de l'invité (règle « scan obligatoire »).
     */
    public function scan(User $user): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau']);
    }

    public function qr(User $user, Intervenant $intervenant): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Intervenant $intervenant): bool
    {
        return false;
    }

    public function delete(User $user, Intervenant $intervenant): bool
    {
        return false;
    }
}
