<?php

namespace App\Jobs;

use App\Enums\PoleRubriqueEnum;
use App\Models\ActivityLog;
use App\Models\SourceVeille;
use App\Models\VeilleSubject;
use App\Services\ScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScrapeVeilleSourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $sourceId = null,
        public bool $force = false,
    ) {}

    protected function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = Str::slug($base) ?: ('sujet-'.Str::random(6));
        $candidate = $slug;
        $i = 1;
        while (VeilleSubject::where('slug', $candidate)
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }

    public function handle(ScraperService $scraper): void
    {
        $started = microtime(true);
        $sources = SourceVeille::query()->where('is_active', true)
            ->when($this->sourceId, fn ($query) => $query->whereKey($this->sourceId))
            ->orderBy('last_scraped_at')->orderBy('id')->get();
        foreach ($sources as $source) {
            if (microtime(true) - $started >= config('veille.cycle_time_budget', 240)) {
                break;
            }
            $lock = Cache::lock('veille:source:'.$source->id, config('veille.source_time_budget', 60) + 120);
            if (! $lock->get()) {
                continue;
            }
            try {
                $source->refresh();
                if (! $source->is_active || (! $this->force && ! $source->estDue())) {
                    continue;
                }
                $scraper->withinBudget(config('veille.source_time_budget', 60), fn () => $this->collectSource($source, $scraper));
            } catch (\Throwable $exception) {
                Log::channel('tvplus_veille')->error('[Veille] Collecte échouée', ['source_id' => $source->id, 'error' => $exception->getMessage()]);
                $this->recordFailure($source, 'La collecte a échoué. Une nouvelle tentative est programmée automatiquement.');
            } finally {
                $lock->release();
            }
        }
    }

    private function collectSource(SourceVeille $source, ScraperService $scraper): void
    {
        $log = Log::channel('tvplus_veille');
        if (empty($source->rss_feed) && ! in_array($source->type, ['single_url', 'manual'], true)) {
            $resolved = $scraper->resolveFeedUrl($source->url);
            if ($resolved) {
                $source->update(['rss_feed' => $resolved, 'type' => 'rss']);
            }
        }
        $scrapeUrl = $source->rss_feed ?: $source->url;
        $scrapeType = $source->rss_feed ? 'rss' : ($source->type === 'single_url' ? 'article' : $source->type);
        $items = $scraper->scrapeUrl($scrapeUrl, $scrapeType);
        if ($items === [] && $source->rss_feed && $source->rss_feed !== $source->url) {
            $items = $scraper->scrapeUrl($source->url, 'web');
        }
        $imported = 0;
        $created = 0;
        $deepCount = 0;
        foreach ($items as $item) {
            if (empty($item['url']) && empty($item['title'])) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $title = Str::limit($title, 250, '');
            $itemUrl = trim((string) ($item['url'] ?? ''));
            $publishedAt = $item['published_at'] ?? null;
            $existing = null;
            if ($itemUrl !== '') {
                $existing = VeilleSubject::where('url', $itemUrl)->first();
            }
            if (! $existing) {
                $existingQuery = VeilleSubject::query()
                    ->where('source_id', $source->id)
                    ->where('title', $title);
                $hasValidPublishedDate = false;
                if ($publishedAt) {
                    try {
                        $existingQuery->whereDate('published_at', Carbon::parse($publishedAt)->toDateString());
                        $hasValidPublishedDate = true;
                    } catch (\Throwable) {
                    }
                }
                if (! $hasValidPublishedDate) {
                    $existingQuery->whereNull('published_at');
                }
                $existing = $existingQuery->first();
                if (! $existing && $hasValidPublishedDate) {
                    $existing = VeilleSubject::query()
                        ->where('source_id', $source->id)
                        ->where('title', $title)
                        ->whereNull('published_at')
                        ->first();
                }
            }

            $needDeep = (empty($item['summary']) || empty($item['content']) || empty($item['image_url']));
            $existingNeedsFill = $existing && (empty($existing->summary) || empty($existing->content) || empty($existing->image_url));
            if ($deepCount < config('veille.deep_articles_per_source', 3) && $needDeep && ! empty($item['url']) && (! $existing || $existingNeedsFill)) {
                try {
                    // 'article' = la page est traitée comme UN article
                    // (contrairement à 'html' qui cherche une liste de liens).
                    $deepCount++;
                    $deep = $scraper->scrapeUrl($item['url'], 'article');
                    if (is_array($deep) && count($deep) === 1) {
                        $d = $deep[0];
                        if (empty($item['summary']) && ! empty($d['summary'])) {
                            $item['summary'] = $d['summary'];
                        }
                        if (empty($item['content']) && ! empty($d['content'])) {
                            $item['content'] = $d['content'];
                        }
                        if (empty($item['image_url']) && ! empty($d['image_url'])) {
                            $item['image_url'] = $d['image_url'];
                        }
                        if (empty($item['published_at']) && ! empty($d['published_at'])) {
                            $item['published_at'] = $d['published_at'];
                        }
                    }
                } catch (\Exception $e) {
                    $log->warning("[Veille] Deep scrape échoué {$item['url']}: {$e->getMessage()}");
                }
            }

            $scrapedValues = [
                'title' => $title,
                'summary' => $item['summary'] ?? (! empty($item['content']) ? Str::limit(trim(strip_tags($item['content'])), 350) : null),
                'content' => $item['content'] ?? null,
                'image_url' => $item['image_url'] ?? null,
                'category' => PoleRubriqueEnum::tryFromLoose($item['category'] ?? null)?->value
                    ?? $source->category?->value,
                'published_at' => $this->publishedAt($item['published_at'] ?? null),
            ];
            $gabonSource = in_array(mb_strtolower((string) $source->country), ['ga', 'gabon'], true)
                || ($source->config['gabon_watch'] ?? false);

            if ($existing) {
                // Les passages suivants rafraîchissent la collecte sans
                // écraser un titre, un résumé ou une rubrique retouchés
                // par la rédaction. Seuls les champs encore vides sont complétés.
                $updates = [];

                if (blank($existing->source_id)) {
                    $updates['source_id'] = $source->id;
                }
                if (blank($existing->url) && $itemUrl !== '') {
                    $updates['url'] = $itemUrl;
                }
                foreach ($scrapedValues as $field => $value) {
                    if (blank($existing->{$field}) && ! blank($value)) {
                        $updates[$field] = $value;
                    }
                }
                if (blank($existing->slug)) {
                    $updates['slug'] = $this->uniqueSlug($existing->title ?: $title, $existing->id);
                }
                if ($gabonSource && ! in_array('gabon', $existing->tags ?? [], true)) {
                    $updates['tags'] = [...($existing->tags ?? []), 'gabon'];
                }

                if ($updates !== []) {
                    $existing->update($updates);
                }
            } else {
                $created++;
                VeilleSubject::create([
                    'source_id' => $source->id,
                    'status' => 'pending',
                    'title' => $title,
                    'slug' => $this->uniqueSlug($title),
                    'url' => $itemUrl !== '' ? $itemUrl : null,
                    ...$scrapedValues,
                    'priority' => $source->priority ?? 5,
                    'scraped_at' => $item['scraped_at'] ?? now(),
                    'tags' => $gabonSource ? ['gabon'] : null,
                ]);
            }
            $imported++;
        }
        if ($imported === 0) {
            $this->recordFailure($source, 'La source n’a renvoyé aucun article exploitable. Nouvelle tentative automatique.');

            return;
        }
        $updated = SourceVeille::query()->whereKey($source->id)->where('is_active', true)->update([
            'last_scraped_at' => now(), 'last_succeeded_at' => now(),
            'status' => 'active', 'last_error' => null, 'consecutive_failures' => 0,
            'last_items_count' => $imported, 'last_new_items_count' => $created,
            'collection_requested_at' => null,
        ]);
        if ($updated === 0) {
            return;
        }
        $this->journaliserActiviteSource($source, 'scrape.completed', ['items_processed' => $imported, 'items_created' => $created]);
        Log::channel('tvplus_veille')->info('[Veille] Source collectée', ['source_id' => $source->id, 'items' => $imported, 'new' => $created]);
    }

    private function recordFailure(SourceVeille $source, string $message): void
    {
        $updated = SourceVeille::query()->whereKey($source->id)->where('is_active', true)->update([
            'last_scraped_at' => now(), 'status' => 'error', 'last_error' => $message,
            'consecutive_failures' => ($source->consecutive_failures ?? 0) + 1,
            'last_items_count' => 0, 'last_new_items_count' => 0, 'collection_requested_at' => null,
        ]);
        if ($updated === 0) {
            return;
        }
        $this->journaliserActiviteSource($source, 'scrape.failed', ['items_processed' => 0]);
    }

    private function publishedAt(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, int|string>  $context
     */
    private function journaliserActiviteSource(SourceVeille $source, string $event, array $context): void
    {
        try {
            ActivityLog::create([
                'actor_id' => null,
                'actor_name' => 'Automatisation veille',
                'event' => $event,
                'route_name' => 'veille.scheduler',
                'route_uri' => null,
                'http_method' => 'SYSTEM',
                'http_status' => null,
                'subject_type' => $source->getMorphClass(),
                'subject_id' => $source->id,
                'route_parameters' => null,
                'request_fields' => null,
                'context' => array_merge(['source_name' => $source->name], $context),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::channel('tvplus_veille')->warning('[Veille] Impossible d’écrire dans le journal d’activité: '.$exception->getMessage());
        }
    }
}
