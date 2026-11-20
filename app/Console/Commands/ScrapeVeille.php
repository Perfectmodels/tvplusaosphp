<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ScrapeVeilleSourcesJob;
use App\Models\SourceVeille;
use App\Models\VeilleSubject;
use Illuminate\Console\Command;

/**
 * Déclenche manuellement le scraping des sources de veille.
 *
 *   php artisan veille:scrape              # toutes les sources
 *   php artisan veille:scrape --source=9   # une seule source
 *   php artisan veille:scrape --now        # ignore la fréquence minimale
 */
class ScrapeVeille extends Command
{
    protected $signature = 'veille:scrape
                            {--source= : Ne scrape que cette source}
                            {--now : Ignore la fréquence minimale entre deux passages}';

    protected $description = 'Scrape les sources de veille et importe les sujets';

    public function handle(): int
    {
        $sourceId = $this->option('source') ? (int) $this->option('source') : null;

        if ($sourceId !== null && ! SourceVeille::whereKey($sourceId)->exists()) {
            $this->error("Source #{$sourceId} introuvable.");

            return self::FAILURE;
        }

        $before = VeilleSubject::count();

        $this->info('Scraping en cours…');
        $started = now();
        ScrapeVeilleSourcesJob::dispatchSync($sourceId, (bool) $this->option('now'));

        $after = VeilleSubject::count();

        $this->newLine();
        $this->info("Sujets en base : {$before} → {$after} (+".($after - $before).')');

        $rows = SourceVeille::query()
            ->when($sourceId, fn ($query) => $query->whereKey($sourceId))
            ->withCount('subjects')
            ->orderBy('id')
            ->get()
            ->map(fn (SourceVeille $s) => [
                $s->id,
                $s->name,
                $s->status ?? '—',
                $s->subjects_count,
                $s->rss_feed ?? '(html)',
                $s->last_scraped_at?->format('d/m/Y H:i') ?? 'jamais',
            ]);

        $this->table(['#', 'Source', 'Statut', 'Sujets', 'Flux', 'Dernier passage'], $rows->all());

        $failed = SourceVeille::query()->where('is_active', true)->where('status', 'error')
            ->when($sourceId, fn ($query) => $query->whereKey($sourceId))
            ->where('last_scraped_at', '>=', $started)->exists();

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
