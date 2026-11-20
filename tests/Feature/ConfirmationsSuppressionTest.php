<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Toute action de SUPPRESSION doit demander confirmation via SweetAlert.
 *
 * Régression : certains formulaires utilisaient encore le `confirm()` natif
 * du navigateur, d'autres aucune confirmation.
 */
class ConfirmationsSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        foreach (['admin', 'journaliste'] as $role) {
            Role::findOrCreate($role);
        }

        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_le_layout_expose_la_confirmation_sweetalert(): void
    {
        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('TvAlert', $html);
        $this->assertStringContainsString('confirmDelete', $html);
        $this->assertStringContainsString('sweetalert2', $html);
    }

    public function test_la_suppression_d_un_journaliste_est_confirmee(): void
    {
        Journaliste::create(['nom' => 'Ndiaye', 'prenom' => 'Awa', 'is_active' => true]);

        $html = $this->actingAs($this->user())->get('/journalistes')->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm-delete=', $html);
        $this->assertStringNotContainsString('onsubmit="return confirm(', $html);
    }

    public function test_la_suppression_d_une_emission_est_confirmee(): void
    {
        $html = $this->actingAs($this->user())->get('/emissions')->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm-delete=', $html);
        $this->assertStringNotContainsString('onsubmit="return confirm(', $html);
    }

    public function test_aucune_page_du_module_ne_utilise_plus_le_confirm_natif(): void
    {
        $pages = ['/journalistes', '/emissions', '/veille/sujets', '/veille/sources'];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->user())->get($page);

            if ($response->status() !== 200) {
                continue;
            }

            $this->assertStringNotContainsString(
                'onsubmit="return confirm(',
                $response->getContent(),
                "La page {$page} utilise encore le confirm() natif."
            );
        }
    }
}
