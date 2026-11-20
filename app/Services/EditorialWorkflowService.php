<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\StatutEvenementEditorialEnum;
use App\Enums\StatutSujetEditorialEnum;
use App\Enums\StatutVerificationEditorialEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\EditorialEvent;
use App\Models\EditorialStory;
use App\Models\EditorialVerification;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditorialWorkflowService
{
    private const RESPONSABLE_ROLES = [
        'admin',
        'directeur_general',
        'chef_edition',
        'chef_redaction',
    ];

    private const CONTRIBUTEUR_ROLES = [
        ...self::RESPONSABLE_ROLES,
        'journaliste',
        'correspondant',
    ];

    public function ouvrirEvenementDepuisVeille(
        VeilleSubject $veilleSubject,
        User $user,
        ?string $title = null,
        ?string $description = null
    ): EditorialEvent {
        $this->autoriserRoles($user, self::CONTRIBUTEUR_ROLES);

        return DB::transaction(function () use ($veilleSubject, $user, $title, $description): EditorialEvent {
            $veilleSubject = VeilleSubject::query()->lockForUpdate()->findOrFail($veilleSubject->id);
            $existingEvent = $veilleSubject->evenementsEditoriaux()
                ->orderByPivot('created_at', 'desc')
                ->first();

            if ($existingEvent) {
                return $existingEvent;
            }

            $event = EditorialEvent::create([
                'title' => trim((string) $title) !== '' ? trim((string) $title) : $veilleSubject->title,
                'description' => $description ?? $veilleSubject->summary,
                'status' => StatutEvenementEditorialEnum::EnVerification,
                'created_by' => $user->id,
            ]);

            $event->sourcesVeille()->attach($veilleSubject->id, ['linked_by' => $user->id]);

            return $event->load('sourcesVeille');
        });
    }

    /**
     * @param  array{type: string, claim: string, evidence?: ?string, evidence_url?: ?string}  $attributes
     */
    public function ajouterVerification(EditorialEvent $event, array $attributes, User $user): EditorialVerification
    {
        $this->autoriserRoles($user, self::CONTRIBUTEUR_ROLES);

        return DB::transaction(function () use ($event, $attributes, $user): EditorialVerification {
            $event = EditorialEvent::query()->lockForUpdate()->findOrFail($event->id);
            if (in_array($event->status, [StatutEvenementEditorialEnum::Verifie, StatutEvenementEditorialEnum::Rejete], true)) {
                $this->echecWorkflow('Cet événement est clôturé et ne peut plus recevoir de vérification.');
            }

            return $event->verifications()->create([
                'type' => trim($attributes['type']),
                'claim' => trim($attributes['claim']),
                'status' => StatutVerificationEditorialEnum::EnAttente,
                'evidence' => $attributes['evidence'] ?? null,
                'evidence_url' => $attributes['evidence_url'] ?? null,
                'created_by' => $user->id,
            ]);
        });
    }

    public function terminerVerification(
        EditorialVerification $verification,
        StatutVerificationEditorialEnum $status,
        User $user,
        ?string $evidence = null,
        ?string $evidenceUrl = null
    ): EditorialVerification {
        $this->autoriserRoles($user, self::CONTRIBUTEUR_ROLES);

        return DB::transaction(function () use ($verification, $status, $user, $evidence, $evidenceUrl): EditorialVerification {
            $event = EditorialEvent::query()->lockForUpdate()->findOrFail($verification->editorial_event_id);
            $verification = EditorialVerification::query()->lockForUpdate()->findOrFail($verification->id);

            if ($status === StatutVerificationEditorialEnum::EnAttente) {
                $this->echecWorkflow('Une vérification terminée doit avoir une conclusion.');
            }

            if ($verification->status !== StatutVerificationEditorialEnum::EnAttente) {
                $this->echecWorkflow('Cette vérification est déjà clôturée ; créez une nouvelle vérification pour conserver son historique.');
            }

            if (in_array($event->status, [StatutEvenementEditorialEnum::Verifie, StatutEvenementEditorialEnum::Rejete], true)) {
                $this->echecWorkflow('Cet événement est clôturé et ne peut plus être modifié.');
            }

            $verification->update([
                'status' => $status,
                'evidence' => $evidence ?? $verification->evidence,
                'evidence_url' => $evidenceUrl ?? $verification->evidence_url,
                'checked_by' => $user->id,
                'checked_at' => now(),
            ]);

            return $verification->refresh();
        });
    }

    public function validerEvenement(EditorialEvent $event, User $user): EditorialEvent
    {
        $this->autoriserResponsable($user);

        return DB::transaction(function () use ($event, $user): EditorialEvent {
            $event = EditorialEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($event->status === StatutEvenementEditorialEnum::Rejete) {
                $this->echecWorkflow('Un événement rejeté ne peut pas être validé.');
            }

            if ($event->status === StatutEvenementEditorialEnum::Verifie) {
                $this->echecWorkflow('Cet événement a déjà été validé.');
            }

            if (! $event->verifications()->exists()) {
                $this->echecWorkflow('Ajoutez au moins une vérification avant de valider cet événement.');
            }

            if ($event->verifications()->where('status', StatutVerificationEditorialEnum::EnAttente->value)->exists()) {
                $this->echecWorkflow('Toutes les vérifications doivent être examinées avant la validation éditoriale.');
            }

            if ($event->verifications()->where('status', '!=', StatutVerificationEditorialEnum::Confirmee->value)->exists()) {
                $this->echecWorkflow('Toutes les vérifications doivent être confirmées pour déclarer cet événement vérifié.');
            }

            $event->update([
                'status' => StatutEvenementEditorialEnum::Verifie,
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            return $event->refresh();
        });
    }

    public function rejeterEvenement(EditorialEvent $event, string $reason, User $user): EditorialEvent
    {
        $this->autoriserResponsable($user);
        $raison = trim($reason);
        if ($raison === '' || mb_strlen($raison) > 2000) {
            $this->echecWorkflow('Le rejet exige un motif de 1 à 2 000 caractères.');
        }

        return DB::transaction(function () use ($event, $user, $raison): EditorialEvent {
            $event = EditorialEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status !== StatutEvenementEditorialEnum::EnVerification) {
                $this->echecWorkflow('Seul un événement en vérification peut être rejeté.');
            }

            if ($event->sujets()->whereHas('sequences')->exists()) {
                $this->echecWorkflow('Retirez d’abord les sujets de leurs conducteurs avant de rejeter l’événement.');
            }

            $event->update([
                'status' => StatutEvenementEditorialEnum::Rejete,
                'rejection_reason' => $raison,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
            ]);

            $event->sujets()
                ->where('status', '!=', StatutSujetEditorialEnum::Rejete->value)
                ->update([
                    'status' => StatutSujetEditorialEnum::Rejete->value,
                    'rejection_reason' => $raison,
                    'rejected_by' => $user->id,
                    'rejected_at' => now(),
                    'updated_at' => now(),
                ]);

            return $event->refresh();
        });
    }

    /**
     * @param  array{title: string, summary?: ?string, angle?: ?string}  $attributes
     */
    public function creerSujetEditorial(EditorialEvent $event, array $attributes, User $user): EditorialStory
    {
        $this->autoriserRoles($user, self::CONTRIBUTEUR_ROLES);

        if ($event->status === StatutEvenementEditorialEnum::Rejete) {
            $this->echecWorkflow('Aucun sujet ne peut être créé à partir d’un événement rejeté.');
        }

        if (trim($attributes['title']) === '') {
            $this->echecWorkflow('Le titre du sujet éditorial est obligatoire.');
        }

        return $event->sujets()->create([
            'title' => trim($attributes['title']),
            'summary' => $attributes['summary'] ?? null,
            'angle' => $attributes['angle'] ?? null,
            'status' => StatutSujetEditorialEnum::Brouillon,
            'created_by' => $user->id,
        ]);
    }

    public function assignerSujet(EditorialStory $story, Journaliste $journaliste, User $user): EditorialStory
    {
        $this->autoriserResponsable($user);

        if ($story->evenement->status === StatutEvenementEditorialEnum::Rejete) {
            $this->echecWorkflow('Un sujet lié à un événement rejeté ne peut pas être affecté.');
        }

        if (! in_array($story->status, [StatutSujetEditorialEnum::Brouillon, StatutSujetEditorialEnum::Affecte], true)) {
            $this->echecWorkflow('Seul un sujet en brouillon ou déjà affecté peut être réaffecté.');
        }

        if (! $journaliste->is_active) {
            $this->echecWorkflow('Un journaliste inactif ne peut pas recevoir une affectation.');
        }

        $story->update([
            'assigned_journaliste_id' => $journaliste->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'status' => StatutSujetEditorialEnum::Affecte,
        ]);

        return $story->refresh();
    }

    public function demarrerProduction(EditorialStory $story, User $user): EditorialStory
    {
        $this->autoriserResponsableOuJournalisteAssigne($story, $user);

        if ($story->evenement->status === StatutEvenementEditorialEnum::Rejete) {
            $this->echecWorkflow('La production est bloquée, car l’événement éditorial a été rejeté.');
        }

        if ($story->status !== StatutSujetEditorialEnum::Affecte) {
            $this->echecWorkflow('Le sujet doit être affecté avant le démarrage de la production.');
        }

        $story->update([
            'status' => StatutSujetEditorialEnum::EnProduction,
            'production_started_at' => now(),
        ]);

        return $story->refresh();
    }

    public function soumettrePourValidation(EditorialStory $story, User $user): EditorialStory
    {
        $this->autoriserResponsableOuJournalisteAssigne($story, $user);

        if ($story->evenement->status === StatutEvenementEditorialEnum::Rejete) {
            $this->echecWorkflow('La validation est bloquée, car l’événement éditorial a été rejeté.');
        }

        if (! in_array($story->status, [StatutSujetEditorialEnum::Affecte, StatutSujetEditorialEnum::EnProduction], true)) {
            $this->echecWorkflow('Seul un sujet affecté ou en production peut être soumis à validation.');
        }

        $story->update([
            'status' => StatutSujetEditorialEnum::AValider,
            'submitted_for_validation_by' => $user->id,
            'submitted_for_validation_at' => now(),
        ]);

        return $story->refresh();
    }

    public function validerSujetEditorial(
        EditorialStory $story,
        User $user,
        ?string $verificationExceptionReason = null
    ): EditorialStory {
        $this->autoriserResponsable($user);

        if ($story->status !== StatutSujetEditorialEnum::AValider) {
            $this->echecWorkflow('Le sujet doit être soumis à validation avant approbation.');
        }

        $event = $story->evenement;
        if ($event->status === StatutEvenementEditorialEnum::Rejete) {
            $this->echecWorkflow('Un sujet lié à un événement rejeté ne peut pas être validé.');
        }

        $raison = trim((string) $verificationExceptionReason);
        if (mb_strlen($raison) > 1000) {
            $this->echecWorkflow('La justification de la dérogation ne peut pas dépasser 1 000 caractères.');
        }

        $eventIsVerified = $event->status === StatutEvenementEditorialEnum::Verifie;
        if (! $eventIsVerified && $raison === '') {
            $this->echecWorkflow('La vérification humaine de l’événement est obligatoire avant validation du sujet.');
        }

        $story->update([
            'status' => StatutSujetEditorialEnum::Valide,
            'validated_by' => $user->id,
            'validated_at' => now(),
            'verification_exception_reason' => $eventIsVerified ? null : $raison,
            'verification_exception_by' => $eventIsVerified ? null : $user->id,
            'verification_exception_at' => $eventIsVerified ? null : now(),
        ]);

        return $story->refresh();
    }

    public function rejeterSujetEditorial(EditorialStory $story, string $reason, User $user): EditorialStory
    {
        $this->autoriserResponsable($user);

        if ($story->status !== StatutSujetEditorialEnum::AValider) {
            $this->echecWorkflow('Seul un sujet soumis à validation peut être rejeté.');
        }

        $raison = trim($reason);
        if ($raison === '' || mb_strlen($raison) > 2000) {
            $this->echecWorkflow('Le rejet exige un motif de 1 à 2 000 caractères.');
        }

        $story->update([
            'status' => StatutSujetEditorialEnum::Rejete,
            'rejection_reason' => $raison,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
        ]);

        return $story->refresh();
    }

    public function placerAuConducteur(
        EditorialStory $story,
        Conducteur $conducteur,
        User $user,
        int $durationSeconds = 120
    ): Sequence {
        $this->autoriserResponsable($user);

        if ($story->status !== StatutSujetEditorialEnum::Valide) {
            $this->echecWorkflow('Seul un sujet éditorial validé peut être placé au conducteur.');
        }

        $event = $story->evenement;
        $hasUrgentException = trim((string) $story->verification_exception_reason) !== ''
            && $story->verification_exception_by !== null;
        if ($event->status === StatutEvenementEditorialEnum::Rejete) {
            $this->echecWorkflow('Un sujet rattaché à un événement rejeté ne peut pas être placé au conducteur.');
        }

        if ($event->status !== StatutEvenementEditorialEnum::Verifie && ! $hasUrgentException) {
            $this->echecWorkflow('La vérification humaine est obligatoire avant l’ajout au conducteur.');
        }

        if ((string) $conducteur->type !== 'redaction') {
            $this->echecWorkflow('Le sujet éditorial ne peut être ajouté qu’à un conducteur de rédaction.');
        }

        if ($conducteur->date_locked_at !== null) {
            $this->echecWorkflow('Ce conducteur est verrouillé et ne peut plus être modifié.');
        }

        if ($durationSeconds < 1 || $durationSeconds > 36000) {
            $this->echecWorkflow('La durée doit être comprise entre 1 et 36 000 secondes.');
        }

        return DB::transaction(function () use ($story, $conducteur, $durationSeconds): Sequence {
            $event = EditorialEvent::query()->lockForUpdate()->findOrFail($story->editorial_event_id);
            $story = EditorialStory::query()->lockForUpdate()->findOrFail($story->id);
            $conducteur = Conducteur::query()->lockForUpdate()->findOrFail($conducteur->id);

            if ($story->status !== StatutSujetEditorialEnum::Valide) {
                $this->echecWorkflow('Seul un sujet éditorial validé peut être placé au conducteur.');
            }

            $hasUrgentException = trim((string) $story->verification_exception_reason) !== ''
                && $story->verification_exception_by !== null;
            if ($event->status === StatutEvenementEditorialEnum::Rejete) {
                $this->echecWorkflow('Un sujet rattaché à un événement rejeté ne peut pas être placé au conducteur.');
            }
            if ($event->status !== StatutEvenementEditorialEnum::Verifie && ! $hasUrgentException) {
                $this->echecWorkflow('La vérification humaine est obligatoire avant l’ajout au conducteur.');
            }

            if ((string) $conducteur->type !== 'redaction' || $conducteur->date_locked_at !== null) {
                $this->echecWorkflow('Le conducteur est verrouillé ou n’est pas un conducteur de rédaction.');
            }

            $existingSequence = Sequence::query()
                ->where('editorial_story_id', $story->id)
                ->where('conducteur_id', $conducteur->id)
                ->first();

            if ($existingSequence) {
                return $existingSequence;
            }

            $position = (int) Sequence::query()
                ->where('conducteur_id', $conducteur->id)
                ->max('position') + 1;

            return Sequence::create([
                'editorial_story_id' => $story->id,
                'conducteur_id' => $conducteur->id,
                'emission_id' => $conducteur->emission_id,
                'journaliste_responsable_id' => $story->assigned_journaliste_id,
                'title' => $story->title,
                'summary' => $story->summary,
                'angle_editorial' => $story->angle,
                'type' => TypeSequenceEnum::Sujet->value,
                'position' => $position,
                'duration_seconds' => $durationSeconds,
                'statut_terrain' => StatutEtapeWorkflowEnum::En_cours,
            ]);
        });
    }

    private function autoriserResponsable(User $user): void
    {
        $this->autoriserRoles($user, self::RESPONSABLE_ROLES);
    }

    private function autoriserResponsableOuJournalisteAssigne(EditorialStory $story, User $user): void
    {
        if ($user->hasAnyRole(self::RESPONSABLE_ROLES)) {
            return;
        }

        $journalisteAssigne = $story->journalisteAssigne;
        $journalisteLie = $user->journalisteLie();
        if (! $user->hasRole('journaliste')
            || ! $journalisteAssigne
            || ! $journalisteLie
            || (int) $journalisteAssigne->id !== (int) $journalisteLie->id) {
            throw new AuthorizationException('Seul le journaliste assigné ou un responsable éditorial peut effectuer cette action.');
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function autoriserRoles(User $user, array $roles): void
    {
        if (! $user->hasAnyRole($roles)) {
            throw new AuthorizationException('Vous ne disposez pas des droits nécessaires pour cette action.');
        }
    }

    private function echecWorkflow(string $message): never
    {
        throw ValidationException::withMessages(['workflow' => $message]);
    }
}
