<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Enums\StatutLogistiquePivotEnum;
use App\Models\Conducteur;
use App\Models\ParcoursIntervenant;
use App\Models\SequenceIntervenantPivot;
use App\Models\User;

/**
 * Règles métier du parcours intervenant, partagées entre :
 *  - le bouton « Accepté » de l'interface (utilisateur connecté) ;
 *  - la confirmation publique depuis le QR / lien WhatsApp (sans compte).
 *
 * Aucune duplication : les deux chemins exécutent exactement le même code.
 */
class ParcoursIntervenantService
{
    /**
     * Place l'intervenant dans le conducteur au moment de son acceptation.
     * Retourne true si un placement a effectivement eu lieu.
     */
    public function placerDansConducteur(ParcoursIntervenant $parcours, ?User $by = null): bool
    {
        $place = false;
        $corrigerAssociation = false;

        // 1. Une invitation est liée uniquement au conducteur de son émission
        // et de sa DATE de passage. Ne jamais choisir une ancienne édition.
        if ($parcours->emission_id) {
            $date = $parcours->heure_passage_prevue?->toDateString();
            if ($date !== null) {
                $conducteurActuel = $parcours->conducteur_id
                    ? Conducteur::query()->find($parcours->conducteur_id)
                    : null;
                if ($conducteurActuel && (
                    (int) $conducteurActuel->emission_id !== (int) $parcours->emission_id
                    || $conducteurActuel->date_edition?->toDateString() !== $date
                    || (string) $conducteurActuel->type !== 'emission'
                )) {
                    // Corriger une association ancienne invalide, sans choisir de repli.
                    $parcours->conducteur_id = null;
                    $parcours->sequence_id = null;
                    $parcours->sequence_intervenant_id = null;
                    $corrigerAssociation = true;
                }

                if (! $parcours->conducteur_id) {
                    $conducteur = Conducteur::query()
                        ->where('emission_id', $parcours->emission_id)
                        ->where('type', 'emission')
                        ->whereDate('date_edition', $date)
                        ->orderByDesc('id')
                        ->first();
                    if ($conducteur) {
                        $parcours->conducteur_id = $conducteur->id;
                        $place = true;
                    }
                }
            }
        }

        // 2. L'affectation à une séquence est une étape distincte. Ne créer
        // le pivot que si la séquence appartient bien au conducteur prévu.
        if ($parcours->sequence_id && ! $parcours->sequence_intervenant_id
            && (! $parcours->emission_id || $parcours->conducteur_id)
            && (! $parcours->conducteur_id
                || \App\Models\Sequence::query()
                    ->whereKey($parcours->sequence_id)
                    ->where('conducteur_id', $parcours->conducteur_id)
                    ->exists())) {
            $pivot = SequenceIntervenantPivot::firstOrCreate(
                [
                    'sequence_id' => $parcours->sequence_id,
                    'intervenant_id' => $parcours->intervenant_id,
                ],
                [
                    'statut_logistique' => StatutLogistiquePivotEnum::Programme->value,
                    'heure_passage_prevue' => $parcours->heure_passage_prevue,
                    'scanned_by_user_id' => $by?->id,
                ]
            );

            $parcours->sequence_intervenant_id = $pivot->id;
            $place = true;
        }

        if ($place || $corrigerAssociation) {
            $parcours->save();
            $parcours->unsetRelation('pivotSequenceIntervenant');
        }

        return $place;
    }

    /**
     * Confirme l'invitation : placement dans le conducteur puis étape « Confirmé ».
     *
     * @return array{resultat: array, place_dans_conducteur: bool}
     */
    public function confirmer(
        ParcoursIntervenant $parcours,
        ?User $by = null,
        string $medium = 'bouton_web',
        ?string $notes = null,
    ): array {
        $place = false;
        if (! $parcours->sequence_intervenant_id) {
            $place = $this->placerDansConducteur($parcours, $by);
        }

        $resultat = $parcours->avancerEtape(
            EtapeParcoursIntervenantEnum::PresenceConfirmee,
            $by,
            $medium,
            $notes,
        );

        $this->synchroniserPivot($parcours, EtapeParcoursIntervenantEnum::PresenceConfirmee, $by);

        return [
            'resultat' => $resultat,
            'place_dans_conducteur' => $place,
        ];
    }

    /**
     * Miroir de l'étape validée vers le pivot logistique
     * (SequenceIntervenantPivot), pour rester cohérent avec le scan QR.
     */
    public function synchroniserPivot(
        ParcoursIntervenant $parcours,
        EtapeParcoursIntervenantEnum $etape,
        ?User $by = null,
    ): void {
        try {
            $pivot = $parcours->pivotSequenceIntervenant;
            if (! $pivot) {
                return;
            }

            $map = [
                'arrive_accueil' => 'heure_arrive',
                'en_maquillage' => 'heure_maq_debut',
                'sur_plateau' => 'heure_plateau',
            ];

            $col = $map[$etape->value] ?? null;
            if ($col && ! $pivot->{$col}) {
                $pivot->timestamps = false;
                $pivot->updateQuietly([
                    $col => now(),
                    'statut_logistique' => match ($etape->value) {
                        'arrive_accueil' => StatutLogistiquePivotEnum::Arrive->value,
                        'en_maquillage' => StatutLogistiquePivotEnum::Maquillage->value,
                        'sur_plateau' => StatutLogistiquePivotEnum::SurPlateau->value,
                        default => $pivot->statut_logistique,
                    },
                    'scanned_by_user_id' => $by?->id,
                ]);
            }
        } catch (\Throwable) {
            // Le miroir pivot est best-effort : il ne doit jamais bloquer la validation.
        }
    }

    /**
     * Marque l'invitation comme envoyée (QR/WhatsApp) et ouvre la fenêtre
     * de confirmation de 7 jours. Un renvoi relance la fenêtre.
     */
    public function marquerQrEnvoye(
        ParcoursIntervenant $parcours,
        string $medium = 'whatsapp',
        int $joursValidite = 7,
    ): void {
        $now = now();

        if (! $parcours->invitation_envoyee_at) {
            $parcours->invitation_envoyee_at = $now;
        }

        $parcours->qr_envoye_at = $now;
        $parcours->qr_medium = $medium;
        $parcours->last_trigger_medium = $medium;
        $parcours->confirmation_expires_at = $now->copy()->addDays($joursValidite);
        $parcours->save();
    }
}
