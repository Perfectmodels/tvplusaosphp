<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Emission;
use Illuminate\Database\Seeder;

class EmissionCatalogSeeder extends Seeder
{
    /** Types provisoires déduits des intitulés ; les horaires et descriptions restent à confirmer par TV+ Afrique. */
    public const EMISSIONS = [
        ['nom' => 'Info du Week', 'slug' => 'info-du-week', 'type' => TypeEmissionEnum::Mag],
        ['nom' => 'Mid’Info de la semaine', 'slug' => 'mid-info-de-la-semaine', 'type' => TypeEmissionEnum::JT],
        ['nom' => 'Le direct de l’infos', 'slug' => 'le-direct-de-l-infos', 'type' => TypeEmissionEnum::JT],
        ['nom' => 'Cent vingt minutes d’infos', 'slug' => 'cent-vingt-minutes-d-infos', 'type' => TypeEmissionEnum::JT],
        ['nom' => 'Restez avec l’actu du soir', 'slug' => 'restez-avec-l-actu-du-soir', 'type' => TypeEmissionEnum::JT],
        ['nom' => 'On vient chez vous', 'slug' => 'on-vient-chez-vous', 'type' => TypeEmissionEnum::Mag],
        ['nom' => 'À cœur ouvert du samedi', 'slug' => 'a-coeur-ouvert-du-samedi', 'type' => TypeEmissionEnum::Talk],
        ['nom' => 'Le débat d’Afrique', 'slug' => 'le-debat-d-afrique', 'type' => TypeEmissionEnum::Debat],
    ];

    public function run(): void
    {
        foreach (self::EMISSIONS as $definition) {
            Emission::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'nom' => $definition['nom'],
                    'title' => $definition['nom'],
                    'type' => $definition['type'],
                    'statut' => StatutEmissionEnum::Planifiee,
                    'status' => 'scheduled',
                    'is_active' => true,
                    'is_brouillon' => false,
                ]
            );
        }
    }
}
