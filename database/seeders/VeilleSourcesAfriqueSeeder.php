<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SourceVeille;
use Illuminate\Database\Seeder;

/**
 * Sources de veille « actualité économique Afrique ».
 *
 * Idempotent : ajoute les sources manquantes sans écraser les réglages modifiés
 * dans l'interface. Ensuite :
 *   php artisan veille:resolve-feeds --persist
 * détecte et persiste le flux RSS/Atom de chacune.
 */
class VeilleSourcesAfriqueSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const SOURCES = [
        [
            'name' => 'RFI Afrique',
            'url' => 'https://www.rfi.fr/fr/afrique/',
            'category' => 'international',
            'priority' => 4,
        ],
        [
            'name' => 'France 24 Afrique',
            'url' => 'https://www.france24.com/fr/afrique/',
            'category' => 'international',
            'priority' => 4,
        ],
        [
            'name' => 'Agence Ecofin',
            'url' => 'https://www.agenceecofin.com',
            'category' => 'economie',
            'priority' => 5,
        ],
        [
            'name' => 'Ecomatin',
            'url' => 'https://ecomatin.net',
            'category' => 'economie',
            'priority' => 5,
        ],
        [
            'name' => 'Africtelegraph',
            'url' => 'https://africtelegraph.com',
            'category' => 'economie',
            'priority' => 5,
        ],
        [
            'name' => 'Sokafinance',
            'url' => 'https://www.sikafinance.com',
            'category' => 'economie',
            'priority' => 5,
        ],
    ];

    public function run(): void
    {
        foreach (self::SOURCES as $source) {
            // Clé sur le nom : l'URL peut avoir été modifiée (volontairement ou
            // non) dans l'interface, on la normalise à chaque passage.
            SourceVeille::firstOrCreate(
                ['name' => $source['name']],
                [
                    'url' => $source['url'],
                    'type' => 'rss',
                    'rss_feed' => null,
                    'language' => 'fr',
                    'category' => $source['category'],
                    'priority' => $source['priority'],
                    'frequency' => '1h',
                    'is_active' => true,
                    'status' => 'active',
                ]
            );
        }

        $this->command?->info(count(self::SOURCES).' sources de veille enregistrées.');
    }
}
