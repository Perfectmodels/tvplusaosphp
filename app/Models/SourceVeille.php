<?php

namespace App\Models;

use App\Enums\PoleRubriqueEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SourceVeille extends Model
{
    use HasFactory;

    protected $table = 'source_veille';

    protected $fillable = [
        'name',
        'type',
        'url',
        'frequency',
        'rss_feed',
        'country',
        'language',
        'category',
        'priority',
        'scraper_class',
        'last_scraped_at',
        'last_succeeded_at',
        'collection_requested_at',
        'last_error',
        'consecutive_failures',
        'last_items_count',
        'last_new_items_count',
        'is_active',
        'status',
        'config',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'last_succeeded_at' => 'datetime',
        'collection_requested_at' => 'datetime',
        'consecutive_failures' => 'integer',
        'last_items_count' => 'integer',
        'last_new_items_count' => 'integer',
        'config' => 'array',
        'status' => 'string',
        'frequency' => 'string',
        'category' => PoleRubriqueEnum::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $source) {
            if ($source->isDirty('is_active')) {
                // Le booléen pilote le statut d'activation.
                $source->status = $source->getAttribute('is_active') ? 'active' : 'disabled';
            } elseif ($source->isDirty('status')
                && in_array($source->getAttribute('status'), ['active', 'disabled'], true)) {
                // ⚠️ Un statut « error » (échec ponctuel de scraping) ne doit PAS
                // désactiver la source, sinon une simple coupure réseau la
                // retirerait définitivement de la veille automatique.
                $source->is_active = $source->getAttribute('status') === 'active';
            }
        });
    }

    // =====================================================================
    //  FRÉQUENCES DE SCRAPING
    // =====================================================================

    /** Fréquences acceptées → durée en minutes. */
    public const FREQUENCES = [
        '5min' => 5,
        '15min' => 15,
        '30min' => 30,
        '1h' => 60,
        '2daily' => 720,
        'daily' => 1440,
    ];

    /** Durée minimale entre deux passages, en minutes. */
    public function frequenceMinutes(): int
    {
        return self::FREQUENCES[$this->frequency ?? '15min'] ?? 15;
    }

    /** Date du prochain passage autorisé (null si la source n'a jamais tourné). */
    public function prochainPassageAt(): ?Carbon
    {
        if ($this->collection_requested_at) {
            return $this->collection_requested_at;
        }
        if (! $this->last_scraped_at) {
            return null;
        }

        $minutes = $this->status === 'error'
            ? min($this->frequenceMinutes(), min(60, 5 * (2 ** min(4, max(0, ($this->consecutive_failures ?? 1) - 1)))))
            : $this->frequenceMinutes();

        return $this->last_scraped_at->copy()->addMinutes($minutes);
    }

    /** La source est-elle due pour un nouveau passage ? */
    public function estDue(): bool
    {
        $prochain = $this->prochainPassageAt();

        return $this->is_active && ($prochain === null || $prochain->lessThanOrEqualTo(now()));
    }

    /** Nombre de minutes de retard sur la planification (0 si à jour). */
    public function retardMinutes(): int
    {
        $prochain = $this->prochainPassageAt();

        if ($prochain === null) {
            return 0;
        }

        return $prochain->isPast() ? (int) $prochain->diffInMinutes(now()) : 0;
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(VeilleSubject::class, 'source_id');
    }

    public function remixes(): HasMany
    {
        return $this->hasMany(VeilleRemix::class, 'source_id');
    }
}
