<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Emission;
use Database\Seeders\EmissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmissionCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_catalogue_tvplus_cree_les_huit_emissions_officielles_actives(): void
    {
        $this->seed(EmissionCatalogSeeder::class);

        $emissions = Emission::query()->orderBy('id')->get();

        $this->assertSame([
            'Info du Week',
            'Mid’Info de la semaine',
            'Le direct de l’infos',
            'Cent vingt minutes d’infos',
            'Restez avec l’actu du soir',
            'On vient chez vous',
            'À cœur ouvert du samedi',
            'Le débat d’Afrique',
        ], $emissions->pluck('nom')->all());
        $this->assertTrue($emissions->every(fn (Emission $emission): bool => $emission->is_active));
    }

    public function test_le_catalogue_peut_etre_seme_plusieurs_fois_sans_doublonner(): void
    {
        $this->seed(EmissionCatalogSeeder::class);
        $this->seed(EmissionCatalogSeeder::class);

        $this->assertSame(8, Emission::query()->count());
    }
}
