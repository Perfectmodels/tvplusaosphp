<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SourceVeille;
use App\Services\ScraperService;
use Illuminate\Console\Command;

/**
 * Résout l'URL du flux d'actualité de chaque source de veille.
 *
 *   php artisan veille:resolve-feeds            # affiche les flux trouvés
 *   php artisan veille:resolve-feeds --persist  # les écrit dans source_veille.rss_feed
 */
class ResolveVeilleFeeds extends Command
{
    protected $signature = 'veille:resolve-feeds
                            {--persist : Écrit l\'URL trouvée dans source_veille.rss_feed}
                            {--force : Re-résout même si rss_feed est déjà renseigné}';

    protected $description = 'Résout l\'URL du flux d\'actualité (RSS/Atom/News sitemap) de chaque source de veille';

    public function handle(ScraperService $scraper): int
    {
        $sources = SourceVeille::query()
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('rss_feed')->orWhere('rss_feed', ''))
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('Aucune source à résoudre (utilisez --force pour tout re-scaner).');

            return self::SUCCESS;
        }

        $rows = [];
        $found = 0;

        foreach ($sources as $source) {
            $feed = null;

            try {
                $feed = $scraper->resolveFeedUrl($source->url);
            } catch (\Throwable $e) {
                $this->error("  ✗ {$source->name} : {$e->getMessage()}");
            }

            if ($feed) {
                $found++;
                if ($this->option('persist')) {
                    $source->update(['rss_feed' => $feed, 'type' => 'rss']);
                }
            }

            $rows[] = [
                $source->id,
                $source->name,
                $source->url,
                $feed ?? '— aucun flux trouvé',
                $feed ? ($this->option('persist') ? 'persisté' : 'trouvé') : 'html',
            ];
        }

        $this->table(['#', 'Source', 'URL', 'Flux détecté', 'Action'], $rows);
        $this->info("{$found}/".count($sources).' flux détectés.');

        return self::SUCCESS;
    }
}
