<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TypeEmissionEnum;
use App\Enums\TypeIntervenantEnum;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RÉGRESSION Lot 4 — Les FormRequest::authorize() utilisaient des permissions
 * Spatie ('create emission', 'admin full', …) qui n'existent jamais dans la base
 * (le seeder ne crée que des rôles) → 403 « This action is unauthorized »
 * systématique, même pour admin / chef_edition.
 *
 * Elles sont désormais alignées sur les policies par rôle
 * (EmissionPolicy / JournalistePolicy / IntervenantPolicy).
 */
class FormRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function emissionPayload(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'JT 20h',
            'type' => TypeEmissionEnum::JT->value,
            'heure_debut' => '20:00',
            'heure_fin' => '20:30',
            'duree_minutes' => 30,
        ], $overrides);
    }

    public function test_l_admin_peut_creer_une_emission(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))
            ->post('/emissions', $this->emissionPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('emissions', ['nom' => 'JT 20h']);
    }

    public function test_le_chef_edition_peut_creer_une_emission(): void
    {
        $response = $this->actingAs($this->userWithRole('chef_edition'))
            ->post('/emissions', $this->emissionPayload(['nom' => 'Le Mag']));

        $response->assertRedirect();
        $this->assertDatabaseHas('emissions', ['nom' => 'Le Mag']);
    }

    public function test_un_role_sans_droit_ne_peut_pas_creer_une_emission(): void
    {
        $this->actingAs($this->userWithRole('lecture'))
            ->post('/emissions', $this->emissionPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('emissions', ['nom' => 'JT 20h']);
    }

    public function test_l_admin_peut_modifier_une_emission(): void
    {
        $emission = Emission::create($this->emissionPayload());

        $response = $this->actingAs($this->userWithRole('admin'))
            ->put("/emissions/{$emission->id}", $this->emissionPayload(['nom' => 'JT 20h Special']));

        $response->assertRedirect();
        $this->assertDatabaseHas('emissions', ['id' => $emission->id, 'nom' => 'JT 20h Special']);
    }

    public function test_l_admin_peut_creer_et_modifier_un_journaliste(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->post('/journalistes', [
                'matricule' => 'J-2026-001',
                'nom' => 'Ndiaye',
                'prenom' => 'Awa',
                'email' => 'awa.ndiaye@example.org',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('journalistes', ['matricule' => 'J-2026-001']);

        $journaliste = Journaliste::where('matricule', 'J-2026-001')->firstOrFail();

        $this->actingAs($user)
            ->put("/journalistes/{$journaliste->id}", [
                'matricule' => 'J-2026-001',
                'nom' => 'Ndiaye',
                'prenom' => 'Awa-Claire',
                'email' => 'awa.ndiaye@example.org',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('journalistes', ['id' => $journaliste->id, 'prenom' => 'Awa-Claire']);
    }

    public function test_l_admin_peut_creer_et_modifier_un_intervenant(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->post('/intervenants', [
                'nom' => 'Mabicka',
                'prenom' => 'Sylvie',
                'type' => TypeIntervenantEnum::Invite->value,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('intervenants', ['nom' => 'Mabicka', 'prenom' => 'Sylvie']);

        $intervenant = Intervenant::where('nom', 'Mabicka')->firstOrFail();

        $this->actingAs($user)
            ->put("/intervenants/{$intervenant->id}", [
                'nom' => 'Mabicka',
                'prenom' => 'Sylvie-Marie',
                'type' => TypeIntervenantEnum::Invite->value,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('intervenants', ['id' => $intervenant->id, 'prenom' => 'Sylvie-Marie']);
    }
}
