<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Enums\StatutLogistiquePivotEnum;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * SPEC §7.2 — table pivot CENTRALE de synchronisation Conducteur ↔ Suivi Intervenants
 * Colonnes horodatages: heure_arrive, heure_maq_debut, heure_maq_fin, heure_plateau, heure_diffusion, heure_termine_passage
 */
class SequenceIntervenantPivot extends Pivot
{
    protected $table = 'sequence_intervenants';

    public $incrementing = true; // on a un id PK auto-incrémenté (migration 000019 L12)

    protected $fillable = [
        'sequence_id',
        'intervenant_id',
        'position',
        'time_slot',
        'status',
        'notes',
        // Originales SPEC sprint 0
        'heure_arrive',
        'heure_maq_debut',
        'heure_maq_fin',
        'heure_plateau',
        'heure_diffusion',
        'scanned_by_user_id',
        // Nouvelles colonnes SPEC §7.2
        'role_dans_sequence',
        'ordre_passage',
        'duree_allouee_sec',
        'heure_passage_prevue',
        'heure_termine_passage',
        'statut_logistique',
        'no_show',
        'observations_logistique',
    ];

    protected $casts = [
        'position' => 'integer',
        'ordre_passage' => 'integer',
        'duree_allouee_sec' => 'integer',
        'no_show' => 'boolean',
        'heure_arrive' => 'datetime',
        'heure_maq_debut' => 'datetime',
        'heure_maq_fin' => 'datetime',
        'heure_plateau' => 'datetime',
        'heure_diffusion' => 'datetime',
        'heure_passage_prevue' => 'datetime',
        'heure_termine_passage' => 'datetime',
        'statut_logistique' => StatutLogistiquePivotEnum::class,
    ];

    protected static function booted(): void
    {
        static::created(function (self $pivot) {
            try {
                $dejaLie = ParcoursIntervenant::query()
                    ->where('sequence_intervenant_id', $pivot->id)
                    ->exists();

                if ($dejaLie) {
                    return;
                }

                $parCouple = ParcoursIntervenant::query()
                    ->where('intervenant_id', $pivot->intervenant_id)
                    ->where('sequence_id', $pivot->sequence_id)
                    ->get();

                $aRelier = $parCouple->first(
                    static fn (ParcoursIntervenant $p) => $p->sequence_intervenant_id === null
                );

                if ($aRelier !== null) {
                    // Ré-ajout après retrait : l'ancienne ligne déliée du même
                    // couple est re-reliée au nouveau pivot (aucun fantôme, aucun doublon).
                    $aRelier->sequence_intervenant_id = $pivot->id;
                    if ($aRelier->conducteur_id === null) {
                        $aRelier->conducteur_id = $pivot->sequence?->conducteur_id;
                    }
                    $aRelier->save();

                    return;
                }

                if ($parCouple->isEmpty()) {
                    ParcoursIntervenant::query()->create([
                        'sequence_intervenant_id' => $pivot->id,
                        'intervenant_id' => $pivot->intervenant_id,
                        'sequence_id' => $pivot->sequence_id,
                        'last_trigger_medium' => 'bouton_web',
                        'invitation_envoyee_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning('Pivot created → parcours creation failed', ['pivot_id' => $pivot->id ?? null, 'err' => $e->getMessage()]);
            }
        });

        // LOT 12 — symétrie du reflet conducteur : retirer un invité d'un sujet
        // (ou supprimer le sujet) défait aussi sa ligne de parcours.
        // Recherche par couple ET par clé étrangère : la FK `nullOnDelete` peut
        // avoir déjà vidé `sequence_intervenant_id` avant l'événement.
        static::deleted(function (self $pivot) {
            try {
                $parcoursLies = ParcoursIntervenant::query()
                    ->where('sequence_intervenant_id', $pivot->id)
                    ->orWhere(static function ($q) use ($pivot) {
                        $q->where('intervenant_id', $pivot->intervenant_id)
                            ->where('sequence_id', $pivot->sequence_id);
                    })
                    ->get();

                foreach ($parcoursLies as $parcours) {
                    $estManuel = $parcours->emission_id !== null;
                    $convocationEnvoyee = $parcours->qr_envoye_at !== null
                        || $parcours->confirme_at !== null;

                    if ($estManuel || $convocationEnvoyee) {
                        // Décision humaine (« Programmer un intervenant ») ou
                        // convocation déjà envoyée : délier ET conserver la ligne.
                        $parcours->sequence_intervenant_id = null;
                        $parcours->conducteur_id = null;
                        if ($parcours->heure_passage_prevue === null && $pivot->heure_passage_prevue !== null) {
                            $parcours->heure_passage_prevue = $pivot->heure_passage_prevue;
                        }
                        $parcours->save();
                    } else {
                        // Reflet de la liste d'invités du sujet : le retirer du
                        // sujet supprime aussi sa ligne de parcours.
                        $parcours->delete();
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Pivot deleted → parcours cleanup failed', ['pivot_id' => $pivot->id ?? null, 'err' => $e->getMessage()]);
            }
        });
    }

    public function sequence()
    {
        return $this->belongsTo(Sequence::class);
    }

    public function intervenant()
    {
        return $this->belongsTo(Intervenant::class);
    }

    public function scannerBy()
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    /** 1:1 — Un pivot = UN SEUL parcours linéaire 9 étapes */
    public function parcours(): HasOne
    {
        return $this->hasOne(ParcoursIntervenant::class, 'sequence_intervenant_id', 'id');
    }

    /** Helper pour les anciens scans — remplit à la demande les dates pivot dans parcours */
    public function retroActiverParcoursDepuisPivot(?User $by = null): ParcoursIntervenant
    {
        $parcours = $this->parcours;
        if (! $parcours) {
            $parcours = ParcoursIntervenant::query()->create([
                'sequence_intervenant_id' => $this->id,
                'intervenant_id' => $this->intervenant_id,
                'sequence_id' => $this->sequence_id,
                'last_trigger_medium' => 'retro_pivot',
            ]);
        }
        $map = [
            [EtapeParcoursIntervenantEnum::ArriveAccueil, $this->heure_arrive],
            [EtapeParcoursIntervenantEnum::Maquillage, $this->heure_maq_debut],
            [EtapeParcoursIntervenantEnum::PretPlateau, $this->heure_maq_fin],
            [EtapeParcoursIntervenantEnum::SurPlateau, $this->heure_plateau],
            [EtapeParcoursIntervenantEnum::Diffuse, $this->heure_diffusion ?? $this->heure_termine_passage],
        ];
        foreach ($map as [$etape, $date]) {
            if ($date instanceof Carbon || is_string($date)) {
                try {
                    $d = is_string($date) ? Carbon::parse($date) : $date;
                    $parcours->avancerEtape($etape, $by, 'retro_pivot', null, $d);
                } catch (\Throwable) {
                }
            }
        }

        return $parcours;
    }
}
