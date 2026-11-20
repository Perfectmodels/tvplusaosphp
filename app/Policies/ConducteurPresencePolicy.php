<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\User;

/**
 * Droits sur les PRÉSENCES à la réunion de rédaction.
 *
 * ────────────────────────────────────────────────────────────────
 *  Lecture          → tout le monde connecté.
 *  Ajout / Édition  → Chef d'Édition (et au-dessus), ou la personne
 *                      concernée pour SA PROPRE ligne.
 *  Suppression      → Chef d'Édition (et au-dessus), ou la personne
 *                      concernée pour SA PROPRE ligne.
 * ────────────────────────────────────────────────────────────────
 */
class ConducteurPresencePolicy
{
    private const ADMIN_ROLES = ['admin', 'superadmin', 'directeur_general'];

    private const MANAGER_ROLES = ['chef_edition', 'chef_redaction'];

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(array_merge(self::ADMIN_ROLES, self::MANAGER_ROLES))) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ConducteurPresence $presence): bool
    {
        return true;
    }

    public function create(User $user, ?Conducteur $conducteur = null): bool
    {
        // Chef d'édition : géré par before().
        // Sinon : on ne peut créer QUE sa propre présence.
        return true; // le contrôle fin se fait sur journaliste_id dans le contrôleur
    }

    public function update(User $user, ConducteurPresence $presence): bool
    {
        return $this->estProprietaire($user, $presence);
    }

    public function delete(User $user, ConducteurPresence $presence): bool
    {
        return $this->estProprietaire($user, $presence);
    }

    // =====================================================================
    //  HELPERS
    // =====================================================================

    /** La présence appartient-elle au journaliste rattaché à l'utilisateur ? */
    private function estProprietaire(User $user, ConducteurPresence $presence): bool
    {
        $journaliste = method_exists($user, 'journalisteLie') ? $user->journalisteLie() : null;

        return $journaliste !== null
            && (int) $journaliste->id === (int) $presence->journaliste_id;
    }
}
