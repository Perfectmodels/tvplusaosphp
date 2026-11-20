<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurDureeTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_conducteur_peut_avoir_une_duree_cible_superieure_a_une_heure(): void
    {
        $this->actingAs($this->admin())
            ->post(route('conducteurs.emission.store'), [
                'emission_id' => $this->emission()->id,
                'date_edition' => now()->toDateString(),
                'target_duration_sec' => 10800,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('conducteurs', [
            'type' => 'emission',
            'target_duration_sec' => 10800,
        ]);
    }

    public function test_une_duree_cible_au_dela_de_six_heures_est_refusee(): void
    {
        $this->actingAs($this->admin())
            ->from(route('conducteurs.emission.create'))
            ->post(route('conducteurs.emission.store'), [
                'emission_id' => $this->emission()->id,
                'date_edition' => now()->toDateString(),
                'target_duration_sec' => 30000,
            ])
            ->assertSessionHasErrors('target_duration_sec');
    }

    public function test_le_formulaire_propose_des_durees_superieures_a_une_heure(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('conducteurs.emission.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>2 h<', $html);
        $this->assertStringContainsString('>3 h<', $html);
        $this->assertStringContainsString('>6 h<', $html);
    }

    public function test_un_sujet_peut_durer_plus_dune_heure(): void
    {
        $conducteur = Conducteur::create([
            'emission_id' => $this->emission()->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
            'owner_id' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('conducteurs.sequences.store'), [
                'conducteur_id' => $conducteur->id,
                'title' => 'Sujet long',
                'type' => 'SUJET',
                'duration_minutes' => 120,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('sequences', [
            'conducteur_id' => $conducteur->id,
            'duration_seconds' => 7200,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    private function emission(): Emission
    {
        return Emission::create([
            'nom' => 'Émission longue',
            'type' => TypeEmissionEnum::Debat->value,
        ]);
    }
}
