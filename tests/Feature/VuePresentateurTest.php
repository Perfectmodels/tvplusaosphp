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

class VuePresentateurTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_deux_routes_ouvrent_la_meme_vue_unifiee(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);
        $marqueurs = ['Programmation du conducteur', 'Antenne & notes présentateur', 'Ajouter un élément'];

        $htmlPresenter = $this->actingAs($admin)
            ->get(route('conducteurs.presentateur.show', $conducteur))
            ->assertOk()
            ->getContent();

        $htmlChef = $this->actingAs($admin)
            ->get(route('conducteurs.chef_edition', ['conducteurId' => $conducteur->id]))
            ->assertOk()
            ->getContent();

        foreach ($marqueurs as $marqueur) {
            $this->assertStringContainsString($marqueur, $htmlPresenter);
            $this->assertStringContainsString($marqueur, $htmlChef);
        }
    }

    public function test_le_presentateur_voit_la_vue_unifiee_sans_les_actions_admin(): void
    {
        $admin = $this->acteur('admin');
        $presentateur = $this->acteur('presentateur');
        $conducteur = $this->conducteur($admin);

        $html = $this->actingAs($presentateur)
            ->get(route('conducteurs.presentateur.show', $conducteur))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Antenne & notes présentateur', $html);
        $this->assertStringContainsString('Programmation du conducteur', $html);
        $this->assertStringNotContainsString('@click="ouvrirModaleSequence(null)"', $html);
        $this->assertStringNotContainsString('Diffuser une alerte', $html);
    }

    public function test_le_reordonnancement_depuis_la_route_presentateur_est_enregistre(): void
    {
        $admin = $this->acteur('admin');
        $conducteur = $this->conducteur($admin);
        $un = $conducteur->sequences()->create(['title' => 'Un', 'type' => 'SUJET', 'position' => 1]);
        $deux = $conducteur->sequences()->create(['title' => 'Deux', 'type' => 'SUJET', 'position' => 2]);

        $this->actingAs($admin)
            ->patchJson(route('conducteurs.presentateur.sequence.reorder', $conducteur), [
                'ordered_ids' => [$deux->id, $un->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(1, (int) $deux->fresh()->position);
        $this->assertSame(2, (int) $un->fresh()->position);
    }

    private function acteur(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function conducteur(User $owner): Conducteur
    {
        $emission = Emission::create([
            'nom' => 'Émission présentateur',
            'type' => TypeEmissionEnum::Debat->value,
        ]);

        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
            'owner_id' => $owner->id,
            'status' => StatutConducteurEnum::Brouillon,
        ]);
    }
}
