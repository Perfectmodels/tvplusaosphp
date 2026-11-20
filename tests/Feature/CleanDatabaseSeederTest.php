<?php

namespace Tests\Feature;

use App\Models\Emission;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_seeding_par_defaut_ne_cree_aucune_activite_ou_personne_fictive(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['users', 'journalistes', 'intervenants', 'conducteurs', 'sequences', 'parcours_intervenants', 'source_veille', 'veille_subjects', 'editorial_events', 'editorial_stories', 'editorial_verifications', 'activity_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('emissions', 8);
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseHas('roles', ['name' => 'chef_edition']);
        $this->assertTrue(Emission::query()->get()->every(fn (Emission $emission): bool => $emission->heure_debut === null));
    }

    public function test_le_seeding_par_defaut_peut_etre_relance_sans_ajouter_de_donnees(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('emissions', 8);
        $this->assertDatabaseCount('roles', 17);
        $this->assertDatabaseCount('users', 0);
    }
}
