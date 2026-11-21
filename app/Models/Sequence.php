<?php

namespace App\Models;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\StatutPresentationSequenceEnum;
use App\Enums\TypeSequenceEnum;
use App\Services\ConducteurCalculatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'conducteur_id',
        'editorial_story_id',
        'emission_id',
        'journaliste_id',
        'journaliste_responsable_id',
        'title',
        'slug',
        'type',
        'position',
        'duration_seconds',
        'actual_duration_sec',
        'start_time',
        'end_time',
        'heure_debut_calculee',
        'heure_fin_calculee',
        'nom_prenom_text',
        'fonction_text',
        'summary',
        'script_text',
        'notes',
        'note_regie',
        'angle_editorial',
        'status',
        'statut_terrain',
        'statut_montage',
        'statut_habillage',
        'statut_livraison',
        'heure_livraison_prevue',
        'statut_diffusion',
        'alerte_info',
        'notes_presentateur',
        'notes_perso',
        'statut_presentation',
        'presentation_started_at',
        'presentation_ended_at',
        'livre_at',
        'masque',
        'priority',
        'video_url',
        'live_url',
        'user_last_modif_id',
        'metadata',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration_seconds' => 'integer',
        'actual_duration_sec' => 'integer',
        'priority' => 'integer',
        'alerte_info' => 'boolean',
        'masque' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'heure_livraison_prevue' => 'datetime',
        'livre_at' => 'datetime',
        'metadata' => 'array',
        'notes_presentateur' => 'string',
        'notes_perso' => 'string',
        'statut_presentation' => StatutPresentationSequenceEnum::class,
        'presentation_started_at' => 'datetime',
        'presentation_ended_at' => 'datetime',
        'type' => TypeSequenceEnum::class,
        'statut_terrain' => StatutEtapeWorkflowEnum::class,
        'statut_montage' => StatutEtapeWorkflowEnum::class,
        'statut_habillage' => StatutEtapeWorkflowEnum::class,
        'statut_livraison' => StatutEtapeWorkflowEnum::class,
        'statut_diffusion' => StatutEtapeWorkflowEnum::class,
    ];

    protected $appends = [
        'code_conducteur_affichage',
        'code_ligne',
        'duree_antenne_sec',
        'duree_hm',
        'debut_hm',
        'fin_hm',
        'type_badge_html',
        'intervenant_nom_affichage',
        'intervenant_fonction_affichage',
        'notes_preview',
        'statut_presentation_html',
        'row_presentation_class',
    ];

    public const WORKFLOW_ORDER = [
        'statut_terrain',
        'statut_montage',
        'statut_habillage',
        'statut_livraison',
        'statut_diffusion',
    ];

    public function sharedDossier(): HasOne
    {
        return $this->hasOne(WorkspaceDossier::class, 'sequence_id');
    }

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class);
    }

    public function sujetEditorial(): BelongsTo
    {
        return $this->belongsTo(EditorialStory::class, 'editorial_story_id');
    }

    public function emission(): BelongsTo
    {
        return $this->belongsTo(Emission::class);
    }

    public function journaliste(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class);
    }

    public function journalisteResponsable(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class, 'journaliste_responsable_id');
    }

    public function dernierModificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_last_modif_id');
    }

    public function intervenants(): BelongsToMany
    {
        return $this->belongsToMany(Intervenant::class, 'sequence_intervenants')
            ->using(SequenceIntervenantPivot::class)
            ->withTimestamps()
            // Sans withPivot('position'), la colonne n'est pas sélectionnée et
            // $intervenant->pivot->position lit null (→ 0) au lieu de l'ordre.
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /** 1:N — Parcours linéaires des intervenants associés à cette séquence (un par intervenant) */
    public function parcoursIntervenants(): HasMany
    {
        return $this->hasMany(ParcoursIntervenant::class, 'sequence_id')
            ->with(['intervenant', 'conducteur.emission', 'pivotSequenceIntervenant']);
    }

    public function intervenantPrincipal(): ?Intervenant
    {
        return $this->intervenants->first();
    }

    public function scopeDiffusionPrete(Builder $query): Builder
    {
        return $query
            ->where('statut_terrain', StatutEtapeWorkflowEnum::Termine->value)
            ->where('statut_montage', StatutEtapeWorkflowEnum::Termine->value)
            ->where('statut_habillage', StatutEtapeWorkflowEnum::Termine->value)
            ->where('statut_livraison', StatutEtapeWorkflowEnum::Termine->value);
    }

    public function getDureeHmAttribute(): string
    {
        return ConducteurCalculatorService::formatDuree((int) ($this->actual_duration_sec ?? $this->duration_seconds ?? 0));
    }

    public function getDebutHmAttribute(): string
    {
        return ConducteurCalculatorService::formatHeure($this->heure_debut_calculee);
    }

    public function getFinHmAttribute(): string
    {
        return ConducteurCalculatorService::formatHeure($this->heure_fin_calculee);
    }

    public function getTypeBadgeHtmlAttribute(): string
    {
        $type = $this->type;
        if (! $type instanceof TypeSequenceEnum) {
            try {
                $type = TypeSequenceEnum::from((string) $this->type);
            } catch (\Throwable) {
                return sprintf('<span class="badge badge-blue">%s</span>', e(strtoupper((string) $this->type)));
            }
        }

        return $type->badge();
    }

    public function getIntervenantNomAffichageAttribute(): string
    {
        // Tous les invités du sujet, dans l'ordre (le 1ᵉʳ = principal)
        $noms = $this->intervenants
            ->map(fn (Intervenant $int) => trim(sprintf('%s %s', $int->prenom ?? '', $int->nom ?? '')))
            ->filter()
            ->values();

        if ($noms->isNotEmpty()) {
            return $noms->implode(' · ');
        }

        return (string) ($this->nom_prenom_text ?? '');
    }

    public function getIntervenantFonctionAffichageAttribute(): string
    {
        if ($this->fonction_text) {
            return (string) $this->fonction_text;
        }
        $int = $this->intervenantPrincipal();
        if ($int && $int->fonction) {
            return (string) $int->fonction;
        }

        return '';
    }

    public function workflowBtnHtml(string $colonne, ?bool $cliquable = null): string
    {
        $val = $this->{$colonne};
        if (! $val instanceof StatutEtapeWorkflowEnum) {
            try {
                $val = StatutEtapeWorkflowEnum::from((string) $val);
            } catch (\Throwable) {
                $val = StatutEtapeWorkflowEnum::En_attente;
            }
        }

        return $val->badgeButton($cliquable ?? $this->isEtapeCliquable($colonne));
    }

    public function isEtapeCliquable(string $colonne): bool
    {
        $idx = array_search($colonne, self::WORKFLOW_ORDER, true);
        if ($idx === false) {
            return false;
        }

        $actuel = $this->{$colonne};
        if ($actuel instanceof StatutEtapeWorkflowEnum) {
            if ($actuel === StatutEtapeWorkflowEnum::Termine) {
                return false;
            }
            if ($actuel === StatutEtapeWorkflowEnum::En_cours) {
                return true;
            }
        }

        if ($idx === 0) {
            return true;
        }

        $colPrecedente = self::WORKFLOW_ORDER[$idx - 1];
        $valPrec = $this->{$colPrecedente};

        return $valPrec instanceof StatutEtapeWorkflowEnum
            && $valPrec === StatutEtapeWorkflowEnum::Termine;
    }

    public function appliquerWorkflowEtapeSuivante(string $colonne): bool
    {
        $idx = array_search($colonne, self::WORKFLOW_ORDER, true);
        if ($idx === false) {
            return false;
        }
        if (! $this->isEtapeCliquable($colonne)) {
            return false;
        }

        $this->{$colonne} = StatutEtapeWorkflowEnum::Termine;

        if ($idx + 1 < count(self::WORKFLOW_ORDER)) {
            $colSuivante = self::WORKFLOW_ORDER[$idx + 1];
            $valSuiv = $this->{$colSuivante};
            $estEnAttente = ! $valSuiv instanceof StatutEtapeWorkflowEnum
                || $valSuiv === StatutEtapeWorkflowEnum::En_attente;
            if ($estEnAttente) {
                $this->{$colSuivante} = StatutEtapeWorkflowEnum::En_cours;
            }
        }

        if (auth()->check()) {
            $this->user_last_modif_id = auth()->id();
        }

        return $this->save();
    }

    protected static function booted(): void
    {
        static::creating(function (self $seq) {
            if (empty($seq->position) || $seq->position <= 0) {
                $max = (int) self::where('conducteur_id', $seq->conducteur_id)->max('position');
                $seq->position = $max + 1;
            }
            $defaults = [
                'statut_terrain' => StatutEtapeWorkflowEnum::En_cours,
                'statut_montage' => StatutEtapeWorkflowEnum::En_attente,
                'statut_habillage' => StatutEtapeWorkflowEnum::En_attente,
                'statut_livraison' => StatutEtapeWorkflowEnum::En_attente,
                'statut_diffusion' => StatutEtapeWorkflowEnum::En_attente,
            ];
            foreach ($defaults as $k => $v) {
                if (empty($seq->{$k}) || $seq->{$k} === null) {
                    $seq->{$k} = $v;
                }
            }
            if (! $seq->type) {
                $seq->type = TypeSequenceEnum::Sujet;
            }
            if ($seq->intervenantPrincipalId()) {
                $seq->completerTextesIntervenant($seq->intervenantPrincipalId());
            }
        });

        static::saving(function (self $seq) {
            if (auth()->check()) {
                $seq->user_last_modif_id = $seq->user_last_modif_id ?? auth()->id();
            }
        });

        static::saved(function (self $seq) {
            // Les notes et les états de production ne changent ni le
            // minutage ni les positions : éviter un recalcul à chaque frappe.
            if (! $seq->wasRecentlyCreated && ! $seq->wasChanged([
                'position', 'duration_seconds', 'masque', 'conducteur_id',
            ])) {
                return;
            }
            if ($seq->conducteur_id) {
                try {
                    $conducteur = $seq->conducteur()->first();
                    if ($conducteur) {
                        ConducteurCalculatorService::recalculerHeures($conducteur);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Recalcul horaires séquence impossible', [
                        'sequence_id' => $seq->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        static::deleted(function (self $seq) {
            try {
                $cond = $seq->conducteur()->first();
                if ($cond) {
                    $suivantes = self::where('conducteur_id', $cond->id)
                        ->where('position', '>', $seq->position)
                        ->orderBy('position')
                        ->get();
                    $newPos = $seq->position;
                    foreach ($suivantes as $s) {
                        $s->timestamps = false;
                        $s->updateQuietly(['position' => $newPos]);
                        $newPos++;
                    }
                    ConducteurCalculatorService::recalculerHeures($cond);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Compactage positions conducteur impossible', [
                    'sequence_id' => $seq->id, 'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function intervenantPrincipalId(): ?int
    {
        try {
            $int = $this->intervenants()->first();
        } catch (\Throwable) {
            return null;
        }

        return $int?->id;
    }

    private function completerTextesIntervenant(int $intervenantId): void
    {
        try {
            $int = Intervenant::find($intervenantId);
            if (! $int) {
                return;
            }
            if (empty($this->nom_prenom_text)) {
                $prenom = $int->prenom ? mb_substr($int->prenom, 0, 1).'. ' : '';
                $this->nom_prenom_text = trim($prenom.($int->nom ?? ''));
            }
            if (empty($this->fonction_text) && ! empty($int->fonction)) {
                $this->fonction_text = $int->fonction;
            }
        } catch (\Throwable) {
        }
    }

    public function getNotesPreviewAttribute(): string
    {
        if (empty($this->notes_presentateur)) {
            return '';
        }
        $txt = trim(strip_tags($this->notes_presentateur));
        if (mb_strlen($txt) > 140) {
            return mb_substr($txt, 0, 140).'…';
        }

        return $txt;
    }

    public function getStatutPresentationHtmlAttribute(): string
    {
        try {
            $s = $this->statut_presentation;
            if (! $s instanceof StatutPresentationSequenceEnum) {
                $s = StatutPresentationSequenceEnum::tryFrom((string) $s) ?? StatutPresentationSequenceEnum::Avenir;
            }

            return $s->badge();
        } catch (\Throwable) {
            return StatutPresentationSequenceEnum::Avenir->badge();
        }
    }

    public function getRowPresentationClassAttribute(): string
    {
        try {
            $s = $this->statut_presentation;
            if (! $s instanceof StatutPresentationSequenceEnum) {
                $s = StatutPresentationSequenceEnum::tryFrom((string) $s) ?? StatutPresentationSequenceEnum::Avenir;
            }

            return $s->rowClass();
        } catch (\Throwable) {
            return '';
        }
    }

    // =====================================================================
    //  IDENTIFIANTS (code composé conducteur-ligne)
    // =====================================================================

    /**
     * Code du conducteur parent. Ex: "CE-2026-0001"
     */
    public function getCodeConducteurAffichageAttribute(): string
    {
        try {
            return (string) ($this->conducteur?->code_conducteur_affichage ?? ('#'.$this->conducteur_id));
        } catch (\Throwable) {
            return '#'.(int) $this->conducteur_id;
        }
    }

    /**
     * Code complet de la ligne = {CODE CONDUCTEUR}-{POSITION:02d}.
     * Ex: "CE-2026-0001-03" = 3ᵉ ligne du conducteur CE-2026-0001.
     * Calculé (pas stocké) : reste cohérent après un drag & drop.
     */
    public function getCodeLigneAttribute(): string
    {
        return sprintf('%s-%02d', $this->code_conducteur_affichage, max(0, (int) $this->position));
    }

    // =====================================================================
    //  COMPTE À REBOURS ANTENNE (chrono ÉMISSION)
    // =====================================================================

    /**
     * Durée retenue pour le décompte de l'émission.
     * Une séquence sans durée renseignée (pub, générique…) compte pour 60 s
     * afin de ne pas être sautée instantanément.
     */
    public function getDureeAntenneSecAttribute(): int
    {
        $duree = (int) ($this->duration_seconds ?? 0);

        return $duree > 0 ? $duree : 60;
    }
}
