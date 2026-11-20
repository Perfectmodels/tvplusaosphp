<?php

namespace App\Models;

use App\Enums\PoleRubriqueEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VeilleSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'title',
        'slug',
        'summary',
        'content',
        'url',
        'image_url',
        'category',
        'tags',
        'priority',
        'status',
        'published_at',
        'scraped_at',
        'assigned_journaliste_id',
        'promoted_to_conducteur',
        'ai_angles',
        'watch_analysis',
        'watch_analyzed_at',
        'watch_attempted_at',
        'watch_error',
        'watch_model',
    ];

    protected $casts = [
        'tags' => 'array',
        'priority' => 'integer',
        'published_at' => 'datetime',
        'scraped_at' => 'datetime',
        'promoted_to_conducteur' => 'boolean',
        'ai_angles' => 'array',
        'watch_analysis' => 'array',
        'watch_analyzed_at' => 'datetime',
        'watch_attempted_at' => 'datetime',
        'category' => PoleRubriqueEnum::class,
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceVeille::class, 'source_id');
    }

    public function scopeGabon(Builder $query): void
    {
        $query->where(function ($query) {
            $query->whereHas('source', fn ($source) => $source->whereIn('country', ['GA', 'Gabon', 'gabon'])->orWhere('config->gabon_watch', true))
                ->orWhereRaw('LOWER(COALESCE(title,\'\')) LIKE ?', ['%gabon%'])
                ->orWhereRaw('LOWER(COALESCE(title,\'\')) LIKE ?', ['%libreville%'])
                ->orWhereRaw('LOWER(COALESCE(title,\'\')) LIKE ?', ['%port-gentil%'])
                ->orWhereRaw('LOWER(COALESCE(title,\'\')) LIKE ?', ['%franceville%'])
                ->orWhereJsonContains('tags', 'gabon');
        });
    }

    public function isGabonRelevant(): bool
    {
        $source = $this->relationLoaded('source') ? $this->source : $this->source()->first();
        $country = mb_strtolower((string) ($source?->country ?? ''));
        if (in_array($country, ['ga', 'gabon'], true) || (bool) data_get($source?->config, 'gabon_watch')) {
            return true;
        }
        $tags = array_map(fn ($t) => mb_strtolower((string) $t), $this->tags ?? []);
        if (in_array('gabon', $tags, true)) {
            return true;
        }
        $haystack = mb_strtolower(($this->title ?? '').' '.($this->summary ?? ''));
        foreach (['gabon', 'libreville', 'port-gentil', 'franceville', 'oyem', 'mouila', 'lambaréné', 'tchibanga'] as $kw) {
            if (str_contains($haystack, $kw)) {
                return true;
            }
        }
        return false;
    }

    public function veilleKind(): string
    {
        $source = $this->relationLoaded('source') ? $this->source : $this->source()->first();
        $tags = array_map(fn ($t) => mb_strtolower((string) $t), $this->tags ?? []);
        $sourceType = mb_strtolower((string) ($source?->type ?? ''));
        $platform = mb_strtolower((string) data_get($source?->config, 'platform', ''));

        $socialTags = ['social', 'reseau', 'réseau', 'reseaux', 'réseaux', 'x', 'twitter', 'facebook', 'telegram', 'whatsapp', 'tiktok', 'youtube', 'instagram'];
        $alertTags = ['alerte', 'alert', 'breaking', 'urgent'];

        if (count(array_intersect($tags, $socialTags)) > 0 || in_array($platform, $socialTags, true) || in_array($sourceType, ['social', 'reseaux_sociaux', 'reseau_social'], true)) {
            return 'social';
        }
        if (count(array_intersect($tags, $alertTags)) > 0 || in_array($sourceType, ['alert', 'alerte', 'alertes'], true) || (bool) data_get($source?->config, 'is_alert')) {
            return 'alerte';
        }

        $content = trim(strip_tags((string) ($this->content ?? '')));
        $summary = trim(strip_tags((string) ($this->summary ?? '')));
        $len = mb_strlen($content !== '' ? $content : $summary);
        if ($len > 0 && $len <= 420) {
            return 'alerte';
        }

        return 'article';
    }

    public function veilleStream(): string
    {
        if (! $this->isGabonRelevant()) {
            return 'a_verifier';
        }
        $kind = $this->veilleKind();
        if (in_array($kind, ['social', 'alerte'], true)) {
            return 'alertes';
        }
        return 'articles';
    }

    public function journaliste(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class, 'assigned_journaliste_id');
    }

    public function remixes(): HasMany
    {
        return $this->hasMany(VeilleRemix::class, 'subject_id');
    }

    public function evenementsEditoriaux(): BelongsToMany
    {
        return $this->belongsToMany(EditorialEvent::class, 'editorial_event_veille_subject')
            ->withPivot('linked_by')
            ->withTimestamps();
    }
}
