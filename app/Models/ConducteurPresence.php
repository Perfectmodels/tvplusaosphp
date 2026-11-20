<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PonctualitePresenceEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Présence d'une personne INTERNE (journaliste) à la réunion de rédaction
 * d'un Conducteur de Rédaction.
 *
 * Une seule ligne par (conducteur, journaliste) — contrainte UNIQUE en base.
 * La ponctualité est pointée manuellement après le début de la conférence.
 */
class ConducteurPresence extends Model
{
    use HasFactory;

    protected $table = 'conducteur_presences';

    protected $fillable = [
        'conducteur_id',
        'journaliste_id',
        'arrivee_at',
        'created_by',
        'ponctualite',
        'ponctualite_marked_at',
        'ponctualite_marked_by',
    ];

    protected $casts = [
        'conducteur_id' => 'integer',
        'journaliste_id' => 'integer',
        'created_by' => 'integer',
        'arrivee_at' => 'datetime',
        'ponctualite' => PonctualitePresenceEnum::class,
        'ponctualite_marked_at' => 'datetime',
        'ponctualite_marked_by' => 'integer',
    ];

    protected $appends = [
        'heure_arrivee_hm',
        'date_arrivee_fr',
        'arrivee_complete_fr',
        'nom_complet',
        'fonction',
        'pole',
    ];

    /** @return array<string, mixed> */
    public function pointagePayload(): array
    {
        return [
            'id' => (int) $this->id,
            'journaliste_id' => (int) $this->journaliste_id,
            'nom_complet' => (string) $this->nom_complet,
            'fonction' => (string) $this->fonction,
            'pole' => (string) $this->pole,
            'arrivee_at' => $this->arrivee_at?->toIso8601String(),
            'heure_arrivee_hm' => (string) $this->heure_arrivee_hm,
            'date_arrivee_fr' => (string) $this->date_arrivee_fr,
            'arrivee_complete_fr' => (string) $this->arrivee_complete_fr,
            'ponctualite' => $this->ponctualite?->value,
            'ponctualite_label' => $this->ponctualite?->label() ?? 'À pointer',
            'ponctualite_marked_at' => $this->ponctualite_marked_at?->toIso8601String(),
            'ponctualite_marked_by' => $this->ponctualite_marked_by,
            'can_delete' => auth()->user()?->can('delete', $this) ?? false,
        ];
    }

    // =====================================================================
    //  RELATIONS
    // =====================================================================

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class);
    }

    public function journaliste(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =====================================================================
    //  ACCESSORS D'AFFICHAGE
    // =====================================================================

    /** Heure d'arrivée formatée. Ex : "14:02" */
    public function getHeureArriveeHmAttribute(): string
    {
        return $this->arrivee_at?->format('H:i') ?? '--:--';
    }

    /** Date d'arrivée formatée. Ex : "28/09/2026" */
    public function getDateArriveeFrAttribute(): string
    {
        return $this->arrivee_at?->format('d/m/Y') ?? '—';
    }

    /** Date + heure complète. Ex : "28/09/2026 à 14:02" */
    public function getArriveeCompleteFrAttribute(): string
    {
        return $this->arrivee_at
            ? $this->arrivee_at->format('d/m/Y').' à '.$this->arrivee_at->format('H:i')
            : '—';
    }

    /** Nom complet de la personne présente. */
    public function getNomCompletAttribute(): string
    {
        try {
            $j = $this->journaliste;
            if (! $j) {
                return 'Personne inconnue';
            }

            $civ = '';
            try {
                $civ = is_object($j->civilite) && method_exists($j->civilite, 'label')
                    ? $j->civilite->label()
                    : (string) ($j->civilite?->value ?? '');
            } catch (\Throwable) {
                $civ = '';
            }

            return trim($civ.' '.$j->prenom.' '.$j->nom) ?: ('Journaliste #'.$j->id);
        } catch (\Throwable) {
            return '—';
        }
    }

    /** Fonction de la personne présente. */
    public function getFonctionAttribute(): string
    {
        try {
            $f = $this->journaliste?->fonction;
            if (! $f) {
                return '—';
            }

            return is_object($f) && method_exists($f, 'label') ? $f->label() : (string) ($f->value ?? $f);
        } catch (\Throwable) {
            return '—';
        }
    }

    /** Pôle / rubrique de la personne présente. */
    public function getPoleAttribute(): string
    {
        try {
            $p = $this->journaliste?->pole;
            if (! $p) {
                return '—';
            }

            return is_object($p) && method_exists($p, 'label') ? $p->label() : (string) ($p->value ?? $p);
        } catch (\Throwable) {
            return '—';
        }
    }
}
