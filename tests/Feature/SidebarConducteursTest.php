<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sidebar — le menu « Conducteurs » n'expose que la Rédaction et le Chef d'Édition.
 * Les rôles d'édition et de présentation partagent le même conducteur d'émission.
 */
class SidebarConducteursTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Role::findOrCreate('admin');
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function menu(): string
    {
        return $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();
    }

    public function test_le_menu_expose_la_redaction_et_le_chef_edition(): void
    {
        $html = $this->menu();

        $this->assertStringContainsString('Conducteurs · Rédaction', $html);
        $this->assertStringContainsString("Conducteurs · Chef d'Édition", $html);
    }

    public function test_le_menu_n_expose_plus_le_conducteur_presentateur(): void
    {
        $html = $this->menu();

        $this->assertStringNotContainsString('Conducteurs · Présentateur', $html);
    }

    public function test_les_pages_presentateur_restent_accessibles(): void
    {
        $u = $this->user();

        $this->actingAs($u)->get('/conducteurs/presentateur')->assertOk();
        $this->actingAs($u)->get('/conducteurs/presentateur/create')->assertOk();
    }

    public function test_les_liens_de_retour_pointent_vers_le_chef_edition(): void
    {
        $html = $this->actingAs($this->user())
            ->get('/conducteurs/presentateur/create')
            ->assertOk()
            ->getContent();

        // Le bouton « Retour » ne doit plus viser la liste présentateur (retirée du menu)
        $this->assertStringContainsString(route('conducteurs.emission.index'), $html);
    }

    public function test_les_cartes_emission_ouvrent_le_conducteur_partage(): void
    {
        // Un conducteur d'émission est nécessaire pour qu'une carte s'affiche
        $em = Emission::create([
            'nom' => 'JT Test Menu', 'title' => 'JT Test Menu',
            'slug' => 'jt-test-menu-'.uniqid(), 'is_active' => true,
        ]);
        $conducteur = Conducteur::create([
            'emission_id' => $em->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);

        $html = $this->actingAs($this->user())
            ->get('/conducteurs/emission')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ouvrir le conducteur', $html);
        $this->assertStringContainsString(route('conducteurs.emission.show', $conducteur), $html);
        $this->assertStringNotContainsString('Vue Présentateur', $html);
    }
}
