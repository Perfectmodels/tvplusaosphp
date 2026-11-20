<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pages d'erreur personnalisées (identité « TV+ Afrique ») :
 * 404 « Signal perdu », 403 « Hors cadre », 500 « Coupure technique »,
 * 503 « En maintenance » — resources/views/errors/.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_404_personnalisee_s_affiche_pour_les_visiteurs(): void
    {
        $this->get('/cette-page-nexiste-pas-'.uniqid())
            ->assertNotFound()
            ->assertSee('Signal perdu')
            ->assertSee('404')
            ->assertSee('Aller à la connexion')
            ->assertSee('TV+ AFRIQUE');
    }

    public function test_la_page_404_propose_le_tableau_de_bord_aux_utilisateurs_connectes(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->get('/cette-page-nexiste-pas-'.uniqid())
            ->assertNotFound()
            ->assertSee('Retour au tableau de bord');
    }

    public function test_la_page_403_personnalisee_s_affiche_en_cas_d_acces_refuse(): void
    {
        $user = $this->userWithRole('lecture');

        $this->actingAs($user)
            ->post('/emissions', ['nom' => 'X'])
            ->assertForbidden()
            ->assertSee('Hors cadre')
            ->assertSee('Retour au tableau de bord');
    }

    public function test_la_page_500_s_affiche_dans_le_style_tv_plus(): void
    {
        $html = view('errors.500', ['code' => 500])->render();

        $this->assertStringContainsString('Coupure technique', $html);
        $this->assertStringContainsString('Réessayer', $html);
        $this->assertStringContainsString('tv-erreur-mire', $html);
    }

    public function test_la_page_503_est_autonome_sans_assets_vite(): void
    {
        $html = view('errors.503', ['code' => 503])->render();

        $this->assertStringContainsString('En maintenance', $html);
        $this->assertStringContainsString('Réessayer', $html);
        // La 503 ne doit dépendre d'aucun asset Vite : pendant la maintenance,
        // la middleware bloque aussi les requêtes /build/*.
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringNotContainsString('/build/', $html);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
