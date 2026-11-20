<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ScraperService;
use Illuminate\Console\Command;

/**
 * Diagnostic : montre exactement ce que le scraper sait extraire d'une URL.
 *
 *   php artisan veille:probe https://www.agenceecofin.com
 *   php artisan veille:probe https://exemple.test/feed.xml --type=rss
 */
class ProbeVeilleUrl extends Command
{
    protected $signature = 'veille:probe
                            {url : URL de la page ou du flux à analyser}
                            {--type=auto : auto|rss|html|article}
                            {--full : Affiche aussi les résumés}';

    protected $description = 'Montre ce que le scraper extrait d\'une URL (flux détecté + articles)';

    public function handle(ScraperService $scraper): int
    {
        $url = (string) $this->argument('url');

        $this->line("URL        : {$url}");

        $html = $scraper->fetchPage($url);
        if ($html !== null) {
            $declared = $scraper->findFeedLinksInHtml($html, $url);
            if (count($declared) > 0) {
                $this->line('Flux déclarés dans la page :');
                foreach (array_slice($declared, 0, 8) as $link) {
                    $this->line("   - {$link}");
                }
            }
        }

        try {
            $feed = $scraper->resolveFeedUrl($url);
        } catch (\Throwable $e) {
            $this->error('Erreur de résolution : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Flux résolu: '.($feed ?? '(aucun — on passera par le HTML)'));

        $type = (string) $this->option('type');
        $target = $feed !== null && $type === 'auto' ? $feed : $url;
        $useType = $feed !== null && $type === 'auto' ? 'rss' : $type;

        $this->line("Cible      : {$target} (type {$useType})");
        $this->newLine();

        $items = $scraper->scrapeUrl($target, $useType);

        $this->info(count($items).' élément(s) extrait(s).');
        $this->newLine();

        foreach (array_slice($items, 0, 15) as $index => $item) {
            $this->line(sprintf('%2d. %s', $index + 1, (string) ($item['title'] ?? '')));
            $this->line('    '.(string) ($item['url'] ?? ''));
            if ($this->option('full') && ! empty($item['summary'])) {
                $this->line('    '.mb_substr((string) $item['summary'], 0, 140).'…');
            }
        }

        return self::SUCCESS;
    }
}
