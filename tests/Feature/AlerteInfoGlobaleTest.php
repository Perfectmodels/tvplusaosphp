<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AlerteInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlerteInfoGlobaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_peut_diffuser_une_alerte_globale(): void
    {
        $this->actingAs($this->acteur('admin'))
            ->post(route('alertes-info.store'), [
                'titre' => 'Coupure de diffusion',
                'message' => 'Le signal antenne est interrompu.',
                'niveau' => 'urgence',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('alerte_infos', [
            'titre' => 'Coupure de diffusion',
            'niveau' => 'urgence',
            'actif' => true,
        ]);
    }

    public function test_un_journaliste_ne_peut_pas_diffuser_dalerte(): void
    {
        $this->actingAs($this->acteur('journaliste'))
            ->post(route('alertes-info.store'), [
                'titre' => 'Alerte',
                'message' => 'Message',
                'niveau' => 'info',
            ])
            ->assertForbidden();
    }

    public function test_lalerte_saffiche_sur_les_pages_internes(): void
    {
        $alerte = AlerteInfo::create([
            'titre' => 'Alerte visible',
            'message' => 'Message visible partout',
            'niveau' => 'avertissement',
        ]);

        $html = $this->actingAs($this->acteur('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Alerte visible', $html);
        $this->assertStringContainsString('Message visible partout', $html);
        $this->assertStringContainsString('tv-alerte-clignotante', $html);
        $this->assertStringContainsString('tv-notif-stack', $html);
        $this->assertStringContainsString('tv-notif-alerte', $html);
    }

    public function test_clore_lalerte_la_retire_des_pages(): void
    {
        $alerte = AlerteInfo::create([
            'titre' => 'Alerte à clore',
            'message' => 'À retirer',
            'niveau' => 'info',
        ]);

        $this->actingAs($this->acteur('admin'))
            ->delete(route('alertes-info.destroy', $alerte))
            ->assertRedirect();

        $this->assertFalse((bool) $alerte->fresh()->actif);

        $html = $this->actingAs($this->acteur('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Alerte à clore', $html);
    }

    public function test_une_alerte_expiree_nest_pas_affichee(): void
    {
        AlerteInfo::create([
            'titre' => 'Alerte expirée',
            'message' => 'Ancienne alerte',
            'niveau' => 'info',
            'expire_at' => now()->subHour(),
        ]);

        $html = $this->actingAs($this->acteur('admin'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Alerte expirée', $html);
    }

    private function acteur(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }
}
