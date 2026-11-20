<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conducteur;
use App\Models\Sequence;
use App\Models\User;

class SequencePolicy
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
        return $user->hasPermissionTo('sequences.view', 'web');
    }

    public function view(User $user, Sequence $sequence): bool
    {
        return $this->viewAny($user)
            || ($user->hasPermissionTo('sequences.edit_own', 'web')
                && $this->estJournalisteResponsable($user, $sequence));
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sequences.create_editorial', 'web');
    }

    public function update(User $user, Sequence $sequence): bool
    {
        if ($sequence->conducteur?->date_locked_at) {
            return false;
        }

        return $user->hasPermissionTo('sequences.edit_all', 'web')
            || ($user->hasPermissionTo('sequences.edit_own', 'web')
                && $this->estJournalisteResponsable($user, $sequence));
    }

    public function delete(User $user, Sequence $sequence): bool
    {
        if ($sequence->conducteur?->date_locked_at) {
            return false;
        }

        return $user->hasPermissionTo('sequences.delete_all', 'web')
            || ($user->hasPermissionTo('sequences.delete_own', 'web')
                && $this->estJournalisteResponsable($user, $sequence));
    }

    public function reorder(User $user, Sequence $sequence): bool
    {
        return ! $sequence->conducteur?->date_locked_at
            && ($user->hasPermissionTo('sequences.reorder_broadcast', 'web')
                || $user->hasPermissionTo('sequences.reorder_editorial', 'web'));
    }

    public function crudSequencePresentateur(User $user, ?Sequence $sequence = null): bool
    {
        return ! $sequence?->conducteur?->date_locked_at
            && $user->hasPermissionTo('sequences.manage_broadcast', 'web');
    }

    public function deleteSequencePresentateur(User $user, Sequence $sequence): bool
    {
        return ! $sequence->conducteur?->date_locked_at
            && $user->hasPermissionTo('sequences.delete_broadcast', 'web');
    }

    public function reorderSequences(User $user, ?Conducteur $conducteur = null): bool
    {
        return ! $conducteur?->date_locked_at
            && $user->hasPermissionTo('sequences.reorder_broadcast', 'web');
    }

    public function crudSequenceRedaction(User $user, ?Sequence $sequence = null): bool
    {
        if ($sequence?->conducteur?->date_locked_at) {
            return false;
        }

        if (! $sequence) {
            return $user->hasPermissionTo('sequences.create_editorial', 'web');
        }

        return $user->hasPermissionTo('sequences.edit_all', 'web')
            || ($user->hasPermissionTo('sequences.edit_own', 'web')
                && $this->estJournalisteResponsable($user, $sequence));
    }

    public function deleteSequenceRedaction(User $user, Sequence $sequence): bool
    {
        if ($sequence->conducteur?->date_locked_at) {
            return false;
        }

        return $user->hasPermissionTo('sequences.delete_all', 'web')
            || ($user->hasPermissionTo('sequences.delete_own', 'web')
                && $this->estJournalisteResponsable($user, $sequence));
    }

    public function reorderSujetsRedaction(User $user, ?Conducteur $conducteur = null): bool
    {
        return ! $conducteur?->date_locked_at
            && $user->hasPermissionTo('sequences.reorder_editorial', 'web');
    }

    public function updateChampsProduction(User $user, Sequence $sequence, string $colonne): bool
    {
        if ($sequence->conducteur?->date_locked_at) {
            return false;
        }

        if ($user->hasPermissionTo('sequences.edit_all', 'web')) {
            return true;
        }

        $isOwner = $this->estJournalisteResponsable($user, $sequence);

        return match ($colonne) {
            'statut_terrain' => $isOwner && $user->hasPermissionTo('sequences.workflow.terrain', 'web'),
            'statut_montage' => $isOwner && $user->hasPermissionTo('sequences.workflow.montage', 'web')
                || $user->hasPermissionTo('sequences.workflow.montage_all', 'web'),
            'statut_habillage' => $user->hasPermissionTo('sequences.workflow.habillage', 'web'),
            'statut_livraison' => $isOwner && $user->hasPermissionTo('sequences.workflow.livraison', 'web')
                || $user->hasPermissionTo('sequences.workflow.livraison_all', 'web'),
            'statut_diffusion' => $user->hasPermissionTo('sequences.workflow.diffusion', 'web'),
            default => $this->update($user, $sequence),
        };
    }

    public function updateWorkflow(User $user, Sequence $sequence, string $colonne): bool
    {
        return $this->updateChampsProduction($user, $sequence, $colonne);
    }

    public function updateNotesPresentateur(User $user, Sequence $sequence): bool
    {
        return ! $sequence->conducteur?->date_locked_at
            && $user->hasPermissionTo('sequences.presenter_notes', 'web');
    }

    public function updateNotesPerso(User $user, Sequence $sequence): bool
    {
        return ! $sequence->conducteur?->date_locked_at
            && $user->hasPermissionTo('sequences.private_notes', 'web');
    }

    public function viewNotesPerso(User $user, Sequence $sequence): bool
    {
        return $user->hasPermissionTo('sequences.private_notes', 'web');
    }

    private function estJournalisteResponsable(User $user, Sequence $sequence): bool
    {
        $journaliste = $user->journalisteLie();

        return $journaliste !== null
            && in_array((int) $journaliste->id, [(int) $sequence->journaliste_responsable_id, (int) $sequence->journaliste_id], true);
    }
}
