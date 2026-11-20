<?php

namespace App\Models;

use App\Enums\RolePresentateurEmissionEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Emission extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'sous_titre',
        'description',
        'type',
        'categorie',
        'cible_audience',
        'langues',
        'heure_debut',
        'heure_fin',
        'duree_minutes',
        'fuseau_horaire',
        'jours_diffusion',
        'date_lancement',
        'date_fin',
        'format_image',
        'qualite',
        'couleur_habillage',
        'police_titres',
        'generique_video_path',
        'jingle_audio_path',
        'statut',
        'theme_color',
        'thumbnail',
        'slug',
        'chef_edition_id',
        'presentateur_id',
        'duration_minutes',
        'scheduled_at',
        'studio',
        'status',
        'season',
        'episode',
        'notes',
        'is_brouillon',
        'is_active',
    ];

    protected $casts = [
        'langues' => 'array',
        'jours_diffusion' => 'array',
        'scheduled_at' => 'datetime',
        'date_lancement' => 'date',
        'date_fin' => 'date',
        'heure_debut' => 'datetime:H:i',
        'heure_fin' => 'datetime:H:i',
        'duration_minutes' => 'integer',
        'duree_minutes' => 'integer',
        'season' => 'integer',
        'episode' => 'integer',
        'is_brouillon' => 'boolean',
        'is_active' => 'boolean',
        'type' => TypeEmissionEnum::class,
        'statut' => StatutEmissionEnum::class,
    ];

    protected $attributes = [
        'is_active' => true,
        'is_brouillon' => true,
    ];

    // =====================================================================
    // BOOT — Sync legacy fields & auto slug
    // =====================================================================

    protected static function booted(): void
    {
        static::saving(function (self $e) {
            if (empty($e->title) || ($e->isDirty('nom') && !empty($e->nom))) {
                $e->title = $e->nom ?? $e->title ?? '';
            }
            if (empty($e->nom) && !empty($e->title)) {
                $e->nom = $e->title;
            }
            if ($e->statut instanceof StatutEmissionEnum) {
                $map = [
                    StatutEmissionEnum::Brouillon->value => 'draft',
                    StatutEmissionEnum::Planifiee->value => 'scheduled',
                    StatutEmissionEnum::En_cours->value => 'recording',
                    StatutEmissionEnum::Diffusee->value => 'broadcast',
                    StatutEmissionEnum::Archivee->value => 'archived',
                    StatutEmissionEnum::Annulee->value => 'cancelled',
                ];
                $e->status = $map[$e->statut->value] ?? $e->status;
            }
            if ($e->isDirty('duree_minutes') && !is_null($e->duree_minutes)) {
                $e->duration_minutes = (int) $e->duree_minutes;
            } elseif (is_null($e->duree_minutes) && !is_null($e->duration_minutes)) {
                $e->duree_minutes = (int) $e->duration_minutes;
            }
            if ($e->isDirty('couleur_habillage') && !empty($e->couleur_habillage)) {
                $e->theme_color = Str::limit($e->couleur_habillage, 7, '');
            } elseif (empty($e->couleur_habillage) && !empty($e->theme_color)) {
                $e->couleur_habillage = $e->theme_color;
            }
            if (empty($e->slug) && !empty($e->nom)) {
                $base = Str::slug($e->nom, '-');
                $i = 1;
                $slug = $base;
                while (self::where('slug', $slug)->where('id', '!=', $e->id ?? 0)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $e->slug = $slug;
            }
        });
    }

    // =====================================================================
    // ACCESSORS
    // =====================================================================

    public function getNomCompletAttribute(): string
    {
        return trim($this->nom . ($this->sous_titre ? ' · ' . $this->sous_titre : ''));
    }

    public function getInitialesAttribute(): string
    {
        $parts = preg_split('/[\s\-]+/', $this->nom ?? 'E', -1, PREG_SPLIT_NO_EMPTY);
        $init = strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2))));
        return $init ?: 'EM';
    }

    public function getPhotoUrlAttribute(): string
    {
        try {
            if (Schema::hasColumn('media') && $this->hasMedia('logo')) {
                return $this->getFirstMediaUrl('logo');
            }
        } catch (\Throwable) {
        }
        $color = $this->couleur_habillage ?: '#001a41';
        $bg = ltrim($color, '#');
        $init = urlencode($this->initiales);
        return "https://ui-avatars.com/api/?name={$init}&background={$bg}&color=fff&bold=true&size=256";
    }

    public function getHoraireLabelAttribute(): string
    {
        if (!$this->heure_debut) return '—';
        $debut = is_string($this->heure_debut) ? substr($this->heure_debut, 0, 5) : $this->heure_debut->format('H:i');
        if (!$this->heure_fin) return $debut;
        $fin = is_string($this->heure_fin) ? substr($this->heure_fin, 0, 5) : $this->heure_fin->format('H:i');
        return "{$debut} → {$fin}";
    }

    public function getJoursCourtsAttribute(): array
    {
        return is_array($this->jours_diffusion) ? array_map('ucfirst', array_slice($this->jours_diffusion, 0, 7)) : [];
    }

    public function getNombreConducteursAttribute(): int
    {
        try {
            return $this->conducteurs()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    // =====================================================================
    // SCOPES
    // =====================================================================

    public function scopeActifs($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeParStatut($query, StatutEmissionEnum $statut)
    {
        return $query->where('statut', $statut->value);
    }

    public function scopeParType($query, TypeEmissionEnum $type)
    {
        return $query->where('type', $type instanceof \BackedEnum ? $type->value : $type);
    }

    public function scopeRecherche($query, string $q)
    {
        $sq = '%' . $q . '%';
        return $query->where(function ($w) use ($sq) {
            $w->where('nom', 'like', $sq)
              ->orWhere('sous_titre', 'like', $sq)
              ->orWhere('description', 'like', $sq)
              ->orWhere('categorie', 'like', $sq);
        });
    }

    // =====================================================================
    // RELATIONS
    // =====================================================================

    public function chefEdition(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chef_edition_id');
    }

    public function presentateurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class, 'presentateur_id');
    }

    public function presentateurs(): BelongsToMany
    {
        return $this->belongsToMany(Journaliste::class, 'emission_journaliste')
            ->withPivot(['role', 'statut_affectation', 'ordre'])
            ->withTimestamps()
            ->orderByPivot('ordre', 'asc')
            ->orderByPivot('id', 'asc');
    }

    public function presentateursPresents(): BelongsToMany
    {
        return $this->presentateurs()->wherePivot('statut_affectation', 'Confirme');
    }

    public function conducteurs(): HasMany
    {
        return $this->hasMany(Conducteur::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class);
    }

    // =====================================================================
    // HELPERS
    // =====================================================================

    public function ajouterPresentateur(Journaliste $j, RolePresentateurEmissionEnum $role = RolePresentateurEmissionEnum::Principal, string $statut = 'En_attente', ?int $ordre = null): void
    {
        $ord = $ordre ?? ($this->presentateurs()->max('ordre') ?? 0) + 1;
        $this->presentateurs()->syncWithoutDetaching([
            $j->id => [
                'role' => $role->value,
                'statut_affectation' => $statut,
                'ordre' => $ord,
            ],
        ]);
        if (is_null($this->presentateur_id) && $role === RolePresentateurEmissionEnum::Principal) {
            $this->presentateur_id = $j->id;
            $this->saveQuietly();
        }
    }

    public function retirerPresentateur(Journaliste $j): void
    {
        $this->presentateurs()->detach($j->id);
        if ((int) $this->presentateur_id === (int) $j->id) {
            $next = $this->presentateurs()
                ->wherePivot('role', RolePresentateurEmissionEnum::Principal->value)
                ->orderByPivot('ordre')
                ->first();
            $this->presentateur_id = $next?->id;
            $this->saveQuietly();
        }
    }
}
