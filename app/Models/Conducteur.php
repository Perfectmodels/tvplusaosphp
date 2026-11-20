<?php

namespace App\Models;

use App\Enums\StatutConducteurEnum;
use App\Services\ConducteurCalculatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Conducteur extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'emission_id',
        'nom',
        'date_edition',
        'conference_started_at',
        'conference_started_by',
        'owner_id',
        'presenter_on_air_id',
        'presentateur_id',
        'active_sequence_id',
        'version',
        'type',
        'code_conducteur',
        'status',
        'opening_text',
        'closing_text',
        'total_duration',
        'target_duration_sec',
        'antenne_started_at',
        'antenne_offset_sec',
        'antenne_ended_at',
        'active_version',
        'validated_by',
        'validated_at',
        'published_at',
        'date_locked_at',
        'metadata',
    ];

    protected $casts = [
        'total_duration' => 'integer',
        'target_duration_sec' => 'integer',
        'antenne_started_at' => 'datetime',
        'antenne_offset_sec' => 'integer',
        'antenne_ended_at' => 'datetime',
        'active_version' => 'boolean',
        'validated_at' => 'datetime',
        'published_at' => 'datetime',
        'date_locked_at' => 'datetime',
        'date_edition' => 'date',
        'conference_started_at' => 'datetime',
        'conference_started_by' => 'integer',
        'presentateur_id' => 'integer',
        'active_sequence_id' => 'integer',
        'metadata' => 'array',
        'type' => 'string',
        'status' => StatutConducteurEnum::class,
    ];

    protected $appends = [
        'target_duration_minutes',
        'total_duration_hm',
        'progression_percent',
        'statut_badge_html',
    ];

    public function emission(): BelongsTo
    {
        return $this->belongsTo(Emission::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class, 'parent_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Conducteur::class, 'parent_id');
    }

    public function conducteurEmissionMiroir(): HasOne
    {
        return $this->hasOne(Conducteur::class, 'parent_id')->where('type', 'emission')->latestOfMany();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function livePresenter(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class, 'presenter_on_air_id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class)->where('masque', false)->orderBy('position');
    }

    public function sequencesAvecMasques(): HasMany
    {
        return $this->hasMany(Sequence::class)->orderBy('position');
    }

    public function presentateur(): BelongsTo
    {
        return $this->belongsTo(Intervenant::class, 'presentateur_id');
    }

    public function sequenceActive(): BelongsTo
    {
        return $this->belongsTo(Sequence::class, 'active_sequence_id');
    }

    /**
     * Présences à la réunion de rédaction (personnes internes uniquement).
     * Triées par heure d'arrivée croissante.
     */
    public function presences(): HasMany
    {
        return $this->hasMany(ConducteurPresence::class, 'conducteur_id')
            ->orderBy('arrivee_at')
            ->orderBy('id');
    }

    public function emissionInteractionMessages(): HasMany
    {
        return $this->hasMany(EmissionInteractionMessage::class);
    }

    public function emissionChatMessages(): HasMany
    {
        return $this->hasMany(EmissionChatMessage::class);
    }

    public function getTargetDurationMinutesAttribute(): int
    {
        return (int) round(($this->target_duration_sec ?? 3600) / 60);
    }

    public function getTotalDurationHmAttribute(): string
    {
        return ConducteurCalculatorService::formatDuree((int) $this->total_duration);
    }

    // =====================================================================
    //  CHRONO ANTENNE — UN SEUL COMPTE À REBOURS POUR TOUTE L'ÉMISSION
    // =====================================================================

    /**
     * Le chrono de l'émission tourne-t-il en ce moment ?
     * (démarré et pas encore terminé — une pause le suspend sans le terminer)
     */
    public function getAntenneEnMarcheAttribute(): bool
    {
        return $this->antenne_started_at !== null && $this->antenne_ended_at === null;
    }

    /** Le chrono de l'émission a-t-il déjà été démarré (même s'il est en pause) ? */
    public function getAntenneDemarreeAttribute(): bool
    {
        return $this->antenne_started_at !== null
            || (int) ($this->antenne_offset_sec ?? 0) > 0
            || $this->antenne_ended_at !== null;
    }

    /** L'émission est-elle terminée ? */
    public function getAntenneTermineeAttribute(): bool
    {
        return $this->antenne_ended_at !== null;
    }

    /**
     * Secondes écoulées depuis le départ de l'émission.
     * Les pauses ne sont PAS comptées : on ajoute le cumul `antenne_offset_sec`.
     */
    public function getAntenneEcouleSecAttribute(): int
    {
        $offset = max(0, (int) ($this->antenne_offset_sec ?? 0));

        if ($this->antenne_started_at === null) {
            return $offset;
        }

        return $offset + max(0, (int) $this->antenne_started_at->diffInSeconds(now()));
    }

    /**
     * Durée totale de l'émission = somme des durées antenne de ses séquences.
     * Une séquence sans durée compte pour 60 s (voir Sequence::duree_antenne_sec).
     */
    public function getAntenneTotalSecAttribute(): int
    {
        return (int) $this->sequences->sum(fn (Sequence $s) => $s->duree_antenne_sec);
    }

    public function getProgressionPercentAttribute(): int
    {
        $sequences = $this->sequences;
        if ($sequences->count() === 0) {
            return 0;
        }
        $termine = $sequences->filter(
            fn ($s) => $s->statut_diffusion && $s->statut_diffusion->value === 'Terminé'
        )->count();

        return (int) round(($termine / $sequences->count()) * 100);
    }

    public function getStatutBadgeHtmlAttribute(): string
    {
        if (! $this->status instanceof StatutConducteurEnum) {
            try {
                $s = StatutConducteurEnum::from((string) $this->status);
            } catch (\Throwable) {
                $s = StatutConducteurEnum::Brouillon;
            }
        } else {
            $s = $this->status;
        }

        return $s->badge();
    }

    public function scopeRedaction($query)
    {
        return $query->where('type', 'redaction');
    }

    public function scopeTypeChefEdition($query)
    {
        return $query->where('type', 'chef_edition');
    }

    public function scopeTypePresentateur($query)
    {
        return $query->whereIn('type', ['presentateur', 'presentation']);
    }

    /**
     * Scope des conducteurs d'ÉMISSION (type='emission').
     * ⚠️ Nom délibérément distinct de la relation emission() : un scopeEmission()
     * serait écrasé par la relation non-statique emission() et casserait
     * Conducteur::emission() (Error: non-static method).
     */
    public function scopeTypeEmission($query)
    {
        return $query->where('type', 'emission');
    }

    public function scopeByEmissionAndDate($query, int $emissionId, string $date)
    {
        return $query->where('emission_id', $emissionId)
            ->where('date_edition', $date)
            ->typeChefEdition();
    }

    public function getTypeLabelAttribute(): string
    {
        return match ((string) $this->type) {
            'chef_edition' => 'Chef Édition',
            'redaction' => 'Rédaction',
            'presentateur' => 'Présentateur',
            'presentation' => 'Présentateur',
            'emission' => 'Émission',
            default => 'Inconnu',
        };
    }

    public function getTypeBadgeHtmlAttribute(): string
    {
        $t = (string) $this->type;
        $map = [
            'chef_edition' => ['bg-primary/12 text-primary border-primary/15', 'fa-pen-to-square', 'Chef Édition'],
            'redaction' => ['bg-tertiary-fixed/12 text-tertiary border-tertiary/15', 'fa-newspaper', 'Rédaction'],
            'presentateur' => ['bg-secondary-fixed/12 text-secondary border-secondary/15', 'fa-microphone-lines', 'Présentateur'],
            'presentation' => ['bg-secondary-fixed/12 text-secondary border-secondary/15', 'fa-microphone-lines', 'Présentateur'],
            'emission' => ['bg-amber-100 text-amber-800 border-amber-200', 'fa-tv', 'Émission'],
        ];
        $cfg = $map[$t] ?? ['bg-on-surface/5 text-on-surface-variant border-outline/10', 'fa-question', 'Inconnu'];
        [$cls, $icon, $lbl] = $cfg;

        return sprintf(
            '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border text-[11px] font-semibold tracking-wide %s">'
                .'<i class="fa-solid %s text-[10px]"></i>'
                .'<span>%s</span>'
                .'</span>',
            $cls, $icon, $lbl
        );
    }

    protected static function booted(): void
    {
        // --- Génération automatique du code identifiant (CR-AAAA-NNNN / CE-AAAA-NNNN) ---
        static::creating(function (self $conducteur) {
            if (empty($conducteur->code_conducteur)) {
                $conducteur->code_conducteur = self::genererCodeConducteur((string) $conducteur->type);
            }

            if ((string) $conducteur->type === 'emission' && empty($conducteur->public_interaction_token)) {
                do {
                    $token = Str::random(48);
                } while (self::query()->where('public_interaction_token', $token)->exists());

                $conducteur->public_interaction_token = $token;
            }
        });

        static::saved(function (self $conducteur) {
            try {
                ConducteurCalculatorService::recalculerHeures($conducteur);
            } catch (\Throwable) {
            }
        });
    }

    // =====================================================================
    //  IDENTIFIANT MÉTIER
    // =====================================================================

    /**
     * Préfixe du code selon le type de conducteur.
     * CR = Conducteur de Rédaction · CE = Conducteur d'Émission (et dérivés legacy)
     */
    public static function prefixePourType(?string $type): string
    {
        return ((string) $type === 'redaction') ? 'CR' : 'CE';
    }

    /**
     * Génère un code unique de la forme {PRÉFIXE}-{AAAA}-{NNNN}.
     * Ex: CR-2026-0001 · CE-2026-0007
     */
    public static function genererCodeConducteur(?string $type): string
    {
        $prefixe = self::prefixePourType($type);
        $annee = date('Y');
        $racine = "{$prefixe}-{$annee}-";

        $max = 0;
        foreach (self::query()->where('code_conducteur', 'LIKE', "{$racine}%")->pluck('code_conducteur') as $code) {
            $num = (int) preg_replace('/\D+/', '', (string) Str::afterLast((string) $code, '-'));
            if ($num > $max) {
                $max = $num;
            }
        }

        return sprintf('%s%04d', $racine, $max + 1);
    }

    /** Ex: "CR-2026-0001" */
    public function getCodeConducteurAffichageAttribute(): string
    {
        return (string) ($this->code_conducteur ?? ('#'.$this->id));
    }

    /** Ex: "CR-2026-0001 · JT 12h45" */
    public function getCodeAvecNomAttribute(): string
    {
        $nom = trim((string) ($this->nom ?? ''));
        if ($nom === '') {
            $nom = optional($this->emission)->nom ?? ('Conducteur #'.$this->id);
        }

        return $this->code_conducteur_affichage.' · '.$nom;
    }
}
