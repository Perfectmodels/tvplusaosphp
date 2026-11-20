<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FonctionJournalisteEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurRedactionCiviliteTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_chef_edition_affiche_les_civilite_sans_erreur(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));

        $emission = Emission::create([
            'nom' => 'Débat test',
            'type' => TypeEmissionEnum::Debat->value,
        ]);
        $conducteur = Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);

        Intervenant::create([
            'civilite' => 'Mme',
            'prenom' => 'Marie',
            'nom' => 'Dupont',
            'fonction' => 'Experte',
            'is_active' => true,
        ]);
        Journaliste::create([
            'civilite' => 'Mr',
            'prenom' => 'Jean',
            'nom' => 'Martin',
            'fonction' => FonctionJournalisteEnum::Journaliste->value,
        ]);

        $this->actingAs($admin)
            ->get(route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id]))
            ->assertOk()
            ->assertSee('Mme Marie Dupont')
            ->assertSee('Mr Jean Martin')
            ->assertSee('Journaliste');
    }
}
