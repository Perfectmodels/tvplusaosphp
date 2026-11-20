<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Intervenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suppression d'un intervenant.
 *
 * Régression : la route et le contrôleur `destroy` existaient, mais aucune vue
 * n'affichait le bouton de suppression.
 */
class IntervenantSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        foreach (['admin', 'journaliste', 'Super Admin', 'chef_edition'] as $r) {
            Role::findOrCreate($r);
        }

        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function intervenant(string $nom = 'Mballa'): Intervenant
    {
        return Intervenant::create(['nom' => $nom, 'prenom' => 'Jean']);
    }

    public function test_la_liste_affiche_le_bouton_de_suppression(): void
    {
        $intervenant = $this->intervenant();

        $html = $this->actingAs($this->user())
            ->get('/intervenants')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm-delete=', $html);
        $this->assertStringContainsString('intervenants/'.$intervenant->id, $html);
    }

    public function test_la_fiche_affiche_le_bouton_de_suppression(): void
    {
        $intervenant = $this->intervenant();

        $html = $this->actingAs($this->user())
            ->get("/intervenants/{$intervenant->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm-delete=', $html);
    }

    public function test_la_suppression_effective_fonctionne(): void
    {
        $intervenant = $this->intervenant();

        $this->actingAs($this->user())
            ->delete("/intervenants/{$intervenant->id}")
            ->assertRedirect('/intervenants');

        $this->assertDatabaseMissing('intervenants', ['id' => $intervenant->id]);
    }

    public function test_un_journaliste_ne_peut_pas_supprimer(): void
    {
        $intervenant = $this->intervenant();

        $this->actingAs($this->user('journaliste'))
            ->delete("/intervenants/{$intervenant->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('intervenants', ['id' => $intervenant->id]);
    }
}
