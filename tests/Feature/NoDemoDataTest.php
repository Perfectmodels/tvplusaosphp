<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NoDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_initialisation_normale_ne_cree_aucun_employe_invite_ou_conducteur_fictif(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['users', 'journalistes', 'conducteurs', 'sequences', 'intervenants', 'parcours_intervenants', 'veille_subjects'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('emissions', 8);
    }

    public function test_ancien_suivi_redirige_vers_les_chiffres_reels_en_conservant_les_filtres(): void
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');
        $filters = ['date' => '2026-11-13', 'q' => 'Invité'];

        $this->actingAs($user)->get(route('suivi-intervenants.index', $filters))
            ->assertRedirect(route('parcours-intervenants.index', $filters));
        $this->get(route('parcours-intervenants.index', $filters))->assertOk()
            ->assertViewHas('kpis', fn (object $kpis): bool => (array) $kpis === [
                'total_jour' => 0, 'confirmes' => 0, 'en_chemin' => 0, 'arrives' => 0,
                'maquillage' => 0, 'plateau' => 0, 'retards' => 0,
            ]);
    }
}
