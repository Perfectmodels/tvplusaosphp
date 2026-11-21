<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Models\ParcoursIntervenant;
use App\Models\User;

/**
 * Autorisations du parcours intervenant, distinctes de l'accès aux fiches.
 * Le QR d'accueil reste régi par IntervenantPolicy::scan.
 */
class ParcoursIntervenantPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasAnyRole(['Super Admin', 'admin', 'superadmin', 'chef_edition'])
            ? true
            : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'habilleur', 'chef_plateau', 'chef_redaction']);
    }

    public function view(User $user, ParcoursIntervenant $parcours): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'chef_plateau', 'chef_redaction']);
    }

    public function sendInvitation(User $user, ParcoursIntervenant $parcours): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'chef_plateau', 'chef_redaction']);
    }

    public function advance(User $user, ParcoursIntervenant $parcours, EtapeParcoursIntervenantEnum $etape): bool
    {
        return match ($etape) {
            EtapeParcoursIntervenantEnum::Invitation,
            EtapeParcoursIntervenantEnum::Confirme,
            EtapeParcoursIntervenantEnum::QrEnvoye =>
                $this->sendInvitation($user, $parcours),
            EtapeParcoursIntervenantEnum::EnChemin =>
                $user->hasAnyRole(['journaliste', 'presentateur', 'receptionniste', 'chef_plateau']),
            EtapeParcoursIntervenantEnum::EnAttente =>
                $user->hasAnyRole(['receptionniste', 'chef_plateau']),
            EtapeParcoursIntervenantEnum::Maquillage,
            EtapeParcoursIntervenantEnum::PretPlateau =>
                $user->hasAnyRole(['habilleur', 'chef_plateau']),
            EtapeParcoursIntervenantEnum::SurPlateau,
            EtapeParcoursIntervenantEnum::Diffuse =>
                $user->hasAnyRole(['chef_plateau', 'presentateur']),
            EtapeParcoursIntervenantEnum::ArriveAccueil => false, // QR only
        };
    }

    public function assignSequence(User $user, ParcoursIntervenant $parcours): bool
    {
        return $user->hasAnyRole(['journaliste', 'presentateur', 'chef_plateau', 'chef_redaction']);
    }

    public function markDelay(User $user, ParcoursIntervenant $parcours): bool
    {
        return $user->hasAnyRole(['receptionniste', 'chef_plateau', 'journaliste', 'chef_redaction']);
    }

    public function markNoShow(User $user, ParcoursIntervenant $parcours): bool
    {
        return $this->markDelay($user, $parcours);
    }
}
