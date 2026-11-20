<?php

namespace Database\Seeders;

use App\Models\SourceVeille;
use Illuminate\Database\Seeder;

class SourceVeilleSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'name'      => 'BBC World News',
                'type'      => 'rss',
                'url'       => 'https://feeds.bbci.co.uk/news/world/rss.xml',
                'frequency' => '15min',
                'status'    => 'active',
                'language'  => 'en',
                'country'   => 'UK',
                'category'  => 'international',
            ],
            [
                'name'      => 'The Verge Tech',
                'type'      => 'web',
                'url'       => 'https://www.theverge.com/tech',
                'frequency' => '1h',
                'status'    => 'active',
                'language'  => 'en',
                'country'   => 'US',
                'category'  => 'tech',
            ],
            [
                'name'      => 'Le Monde - Pixels',
                'type'      => 'rss',
                'url'       => 'https://www.lemonde.fr/pixels/rss_full.xml',
                'frequency' => '5min',
                'status'    => 'error',
                'language'  => 'fr',
                'country'   => 'FR',
                'category'  => 'tech',
            ],
        ];

        foreach ($sources as $def) {
            SourceVeille::firstOrCreate(
                ['name' => $def['name'], 'url' => $def['url']],
                $def
            );
        }
    }
}
