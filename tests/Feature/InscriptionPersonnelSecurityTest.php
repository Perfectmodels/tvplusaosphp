<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InscriptionPersonnel;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InscriptionPersonnelSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string ...$roles): User
    {
        $user = User::factory()->create(['role' => $roles[0]]);
        $user->assignRole($roles);

        return $user;
    }

    public function test_la_recherche_publique_ne_divulgue_pas_les_coordonnees(): void
    {
        Journaliste::create([
            'civilite' => 'Mr',
            'prenom' => 'Jean',
            'nom' => 'Dupont',
            'email' => 'jean.dupont@example.test',
            'telephone' => '0600000000',
        ]);

        $response = $this->getJson(route('personnel.inscription.search', ['q' => 'Dupont']));

        $response->assertOk()->assertJsonCount(1, 'results');

        $result = $response->json('results.0');
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('telephone', $result);
        $this->assertArrayNotHasKey('fonction', $result);
        $this->assertArrayNotHasKey('has_account', $result);
        $this->assertStringContainsString('Dupont', $result['label']);
    }

    public function test_un_journaliste_ne_peut_pas_consulter_les_inscriptions(): void
    {
        $this->actingAs($this->actor('journaliste'))
            ->get(route('personnel.inscriptions.index'))
            ->assertForbidden();
    }

    public function test_un_journaliste_ne_peut_pas_approuver_une_inscription(): void
    {
        $demande = $this->demandeEnAttente();

        $this->actingAs($this->actor('journaliste'))
            ->post(route('personnel.inscriptions.approve', $demande), ['role' => 'journaliste'])
            ->assertForbidden();
    }

    public function test_un_admin_peut_consulter_les_inscriptions(): void
    {
        $this->actingAs($this->actor('admin'))
            ->get(route('personnel.inscriptions.index'))
            ->assertOk();
    }

    public function test_un_admin_ne_peut_pas_attribuer_le_role_superadmin(): void
    {
        $demande = $this->demandeEnAttente();

        $this->actingAs($this->actor('admin'))
            ->from(route('personnel.inscriptions.index'))
            ->post(route('personnel.inscriptions.approve', $demande), ['role' => 'superadmin'])
            ->assertRedirect(route('personnel.inscriptions.index'))
            ->assertSessionHasErrors('role');

        $this->assertSame('pending', $demande->fresh()->statut);
    }

    private function demandeEnAttente(): InscriptionPersonnel
    {
        return InscriptionPersonnel::create([
            'civilite' => 'Mr',
            'prenom' => 'Jean',
            'nom' => 'Dupont',
            'email' => 'candidat-'.fake()->unique()->safeEmail(),
            'statut' => InscriptionPersonnel::STATUT_PENDING,
        ]);
    }
}
