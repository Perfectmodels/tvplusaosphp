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

        // 1. Retrouver le conducteur de l'émission pour la date de passage
        if (! $parcours->conducteur_id && $parcours->emission_id) {
            $date = $parcours->heure_passage_prevue?->toDateString()
                ?? $parcours->invitation_envoyee_at?->toDateString();

            $conducteur = Conducteur::query()
                ->where('emission_id', $parcours->emission_id)
                ->when($date, static fn ($q) => $q->whereDate('date_edition', $date))
                ->orderByDesc('date_edition')
                ->orderByDesc('id')
                ->first();

            // Repli : dernier conducteur connu de cette émission
            if (! $conducteur) {
                $conducteur = Conducteur::query()
                    ->where('emission_id', $parcours->emission_id)
                    ->orderByDesc('date_edition')
                    ->orderByDesc('id')
                    ->first();
            }

            if ($conducteur) {
                $parcours->conducteur_id = $conducteur->id;
                $place = true;
            }
        }

        // 2. Si une séquence est déjà rattachée, créer le pivot (source de vérité conducteur)
        if ($parcours->sequence_id && ! $parcours->sequence_intervenant_id) {
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

        if ($place) {
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
