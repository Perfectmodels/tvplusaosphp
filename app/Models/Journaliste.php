<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CiviliteCast;
use App\Enums\FonctionJournalisteEnum;
use App\Enums\PoleRubriqueEnum;
use App\Enums\StatutJournalisteEnum;
use App\Services\MatriculeEmployeService;
use App\Services\MediaValidation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Journaliste / Rédacteur / JRI interne TV+AOS (SPEC §2)
 * Peut être PROMU en "Présentateur" via flag is_presentateur
 * Photo via Spatie MediaLibrary collection: `portrait`
 */
class Journaliste extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'matricule',
        'civilite',
        'user_id',
        'nom',
        'prenom',
        'first_name',
        'last_name',
        'date_naissance',
        'lieu_naissance',
        'fonction',
        'pole',
        'statut',
        'date_embauche',
        'specialites',
        'email',
        'telephone',
        'phone',
        'adresse',
        'notes_admin',
        'is_active',
        'is_presentateur',
        'date_promotion_presentateur',
        'photo',
        'specialty',
        'grade',
        'biography',
    ];

    protected $casts = [
        'civilite' => CiviliteCast::class,
        'fonction' => FonctionJournalisteEnum::class,
        'pole' => PoleRubriqueEnum::class,
        'statut' => StatutJournalisteEnum::class,
        'specialites' => 'array',
        'date_naissance' => 'date',
        'lieu_naissance' => 'string',
        'date_embauche' => 'date',
        'date_promotion_presentateur' => 'datetime',
        'is_active' => 'boolean',
        'is_presentateur' => 'boolean',
    ];

    /**
     * @return array<string,mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_presentateur' => false,
    ];

    // =====================================================================
    // BOOT — Sync legacy fields (first_name, last_name, phone, email)
    // =====================================================================

    protected static function booted(): void
    {
        static::creating(function (self $j) {
            if (trim((string) $j->matricule) === '') {
                $j->matricule = self::genererMatricule();
            }
            MatriculeEmployeService::retenir($j->matricule);
        });

        static::saving(function (self $j) {
            if (empty($j->first_name) && ! empty($j->prenom)) {
                $j->first_name = $j->prenom;
            }
            if (empty($j->last_name) && ! empty($j->nom)) {
                $j->last_name = $j->nom;
            }
            if (empty($j->phone) && ! empty($j->telephone)) {
                $j->phone = $j->telephone;
            }
        });
    }

    // =====================================================================
    // IDENTIFIANT MÉTIER — matricule auto J-AAAA-NNNN
    // =====================================================================

    /** Réserve un numéro employé annuel, sans réutiliser les numéros supprimés. */
    public static function genererMatricule(?string $annee = null): string
    {
        return MatriculeEmployeService::generer($annee);
    }

    // =====================================================================
    // ACCESSORS
    // =====================================================================

    protected function nomComplet(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(($this->civilite ? ($this->civilite->value.' ') : '').($this->prenom ?? '').' '.($this->nom ?? '')),
        );
    }

    protected function initiales(): Attribute
    {
        return Attribute::make(
            get: fn (): string => strtoupper(
                mb_substr((string) ($this->prenom ?? ''), 0, 1)
                .mb_substr((string) ($this->nom ?? ''), 0, 1)
            )
        );
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                try {
                    if ($this->hasMedia('portrait')) {
                        return $this->getFirstMediaUrl('portrait', 'thumb');
                    }
                } catch (\Throwable) {
                    // Media table missing or Spatie MediaLibrary not installed
                }
                if (! empty($this->photo)) {
                    return (string) $this->photo;
                }
                $n = urlencode($this->initiales ?: 'JV');

                return "https://ui-avatars.com/api/?name={$n}&background=001a41&color=ffffff&size=256";
            },
        );
    }

    // =====================================================================
    // LOCAL SCOPES (SPEC §2.3 barre filtres)
    // =====================================================================

    public function scopeActifs($query)
    {
        return $query->where('statut', StatutJournalisteEnum::Actif->value);
    }

    public function scopePresentateurs($query)
    {
        return $query->where('is_presentateur', true)
            ->where('statut', '!=', StatutJournalisteEnum::Inactif->value);
    }

    public function scopeRecherche($query, string $q)
    {
        $s = '%'.mb_strtolower(trim($q)).'%';

        return $query->where(static function ($qq) use ($s) {
            $qq->whereRaw('LOWER(nom) LIKE ?', [$s])
                ->orWhereRaw('LOWER(prenom) LIKE ?', [$s])
                ->orWhereRaw('LOWER(matricule) LIKE ?', [$s])
                ->orWhereRaw('LOWER(COALESCE(email,\'\')) LIKE ?', [$s])
                ->orWhereRaw('LOWER(COALESCE(telephone,\'\')) LIKE ?', [$s]);
        });
    }

    // =====================================================================
    // RELATIONS
    // =====================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(VeilleSubject::class, 'assigned_journaliste_id');
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class, 'journaliste_id');
    }

    /**
     * Sujets (séquences) dont ce journaliste est le responsable.
     *
     * C'est la colonne réellement écrite par l'application
     * (`journaliste_responsable_id`). La relation `sequences()` ci-dessus
     * pointe sur `journaliste_id`, colonne historique jamais remplie :
     * elle est conservée pour compatibilité mais ne doit pas servir
     * aux compteurs affichés.
     */
    public function sujetsResponsables(): HasMany
    {
        return $this->hasMany(Sequence::class, 'journaliste_responsable_id');
    }

    // =====================================================================
    // ACTIONS METIER
    // =====================================================================

    public function promouvoirPresentateur(bool $promouvoir = true): self
    {
        $this->forceFill([
            'is_presentateur' => $promouvoir,
            'date_promotion_presentateur' => $promouvoir ? now() : null,
        ])->save();

        return $this;
    }

    // =====================================================================
    // MEDIA LIBRARY
    // =====================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('portrait')
            ->singleFile()
            // 🔒 Type + taille + SVG refusé (vecteur XSS).
            ->acceptsFile([MediaValidation::class, 'imagePortrait'])
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 256, 256)->nonQueued()->performOnCollections('portrait');
                $this->addMediaConversion('xs')
                    ->fit(Fit::Crop, 64, 64)->nonQueued()->performOnCollections('portrait');
            });
    }
}
