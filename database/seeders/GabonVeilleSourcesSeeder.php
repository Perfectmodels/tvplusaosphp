<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SourceVeille;
use Illuminate\Database\Seeder;

class GabonVeilleSourcesSeeder extends Seeder
{
    private const SOURCES = [
        ['name' => 'Gabon Media Time', 'url' => 'https://gabonmediatime.com/', 'rss_feed' => 'https://gabonmediatime.com/cat/derniers-articles/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabonactu', 'url' => 'https://gabonactu.com/', 'rss_feed' => 'https://gabonactu.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabonreview', 'url' => 'https://www.gabonreview.com/', 'rss_feed' => 'https://www.gabonreview.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Direct Infos Gabon', 'url' => 'https://directinfosgabon.com/', 'rss_feed' => 'https://directinfosgabon.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Infos Gabon', 'url' => 'https://infosgabon.com/', 'rss_feed' => 'https://infosgabon.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabonews', 'url' => 'https://gabonews.com/', 'rss_feed' => 'https://gabonews.com/spip.php?page=backend', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabon Eco', 'url' => 'https://gaboneco.com/', 'rss_feed' => 'https://gaboneco.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'AGP Gabon', 'url' => 'https://agpgabon.ga/', 'rss_feed' => 'https://agpgabon.ga/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabon All Sport', 'url' => 'https://gabonallsport.com/', 'rss_feed' => 'https://gabonallsport.com/feed/', 'country' => 'GA', 'category' => 'sport'],
        ['name' => 'Focus Groupe Media', 'url' => 'https://www.focusgroupemedia.com/', 'rss_feed' => 'https://www.focusgroupemedia.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabon Minutes', 'url' => 'https://gabonminutes.com/', 'rss_feed' => 'https://gabonminutes.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabon Mail Infos', 'url' => 'https://gabonmailinfos.com/', 'rss_feed' => 'https://gabonmailinfos.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabon Infos', 'url' => 'https://gaboninfos.com/', 'rss_feed' => 'https://gaboninfos.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'Gabon Info', 'url' => 'https://gabon-info.com/', 'rss_feed' => 'https://gabon-info.com/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'L’Union', 'url' => 'https://www.union.sonapresse.com/', 'rss_feed' => 'https://www.union.sonapresse.com/rss.xml', 'country' => 'GA', 'category' => null],
        ['name' => 'Kongossa News', 'url' => 'https://kongossanews.info/', 'rss_feed' => 'https://kongossanews.info/feed/', 'country' => 'GA', 'category' => null],
        ['name' => 'RFI Gabon', 'url' => 'https://www.rfi.fr/fr/tag/gabon/', 'rss_feed' => 'https://www.rfi.fr/fr/tag/gabon/rss', 'country' => 'FR', 'category' => null],
        ['name' => 'France 24 Gabon', 'url' => 'https://www.france24.com/fr/tag/gabon/', 'rss_feed' => 'https://www.france24.com/fr/tag/gabon/rss', 'country' => 'FR', 'category' => null],
        ['name' => 'Le Monde Afrique', 'url' => 'https://www.lemonde.fr/afrique/', 'rss_feed' => 'https://www.lemonde.fr/afrique/rss_full.xml', 'country' => 'FR', 'category' => null],
    ];

    public function run(): void
    {
        $created = 0;
        foreach (self::SOURCES as $source) {
            $existing = SourceVeille::query()->where('rss_feed', $source['rss_feed'])
                ->orWhere('url', $source['url'])->orWhere('name', $source['name'])
                ->orWhereRaw("REPLACE(LOWER(name), ' ', '') = ?", [mb_strtolower(str_replace(' ', '', $source['name']))])
                ->first();
            $gabonWatch = $source['country'] === 'GA' || in_array($source['name'], ['RFI Gabon', 'France 24 Gabon'], true);
            if ($existing) {
                if (($existing->config['catalog'] ?? null) === 'gabon-2026-11' && ! array_key_exists('gabon_watch', $existing->config ?? [])) {
                    $existing->update(['config' => [...($existing->config ?? []), 'gabon_watch' => $gabonWatch]]);
                }

                continue;
            }
            SourceVeille::create([
                ...$source, 'type' => 'rss', 'language' => 'fr', 'priority' => 5,
                'frequency' => '15min', 'is_active' => true,
                'config' => ['catalog' => 'gabon-2026-11', 'feed_verified_at' => '2026-11-14', 'gabon_watch' => $gabonWatch],
            ]);
            $created++;
        }
        $this->command?->info($created.' sources vérifiées ajoutées, réglages existants conservés.');
    }
}
