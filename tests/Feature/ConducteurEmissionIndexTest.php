<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutConducteurEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurEmissionIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_est_presentee_sous_forme_de_tableau_sans_selection_groupee(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));

        $emission = Emission::create([
            'nom' => 'Émission test',
            'type' => TypeEmissionEnum::Debat->value,
        ]);
        Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
            'owner_id' => $admin->id,
            'status' => StatutConducteurEnum::Brouillon,
            'target_duration_sec' => 7200,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('conducteurs.emission.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Ouvrir', $html);
        $this->assertStringContainsString('id="edition-filter-toggle"', $html);
        $this->assertStringContainsString('id="edition-filter-panel"', $html);
        $this->assertStringNotContainsString('Tout sélectionner sur cette page', $html);
        $this->assertStringNotContainsString('bulk-conducteurs-emission', $html);
        $this->assertStringContainsString('btn-delete-conducteur', $html);
    }
}
