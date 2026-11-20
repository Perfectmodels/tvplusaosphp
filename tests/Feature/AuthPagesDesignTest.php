<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pages d'authentification — identité « TV+ Afrique »
 * (fond bleu doux + carte en verre + œil en CSS, sans image externe).
 */
class AuthPagesDesignTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Role::findOrCreate('admin');
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    /** @return array<int, string> */
    private function pages(): array
    {
        return ['/login', '/forgot-password'];
    }

    // =========================================================
    //  RENDU
    // =========================================================

    public function test_les_pages_d_authentification_sont_rendues(): void
    {
        foreach ($this->pages() as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_la_page_de_reinitialisation_est_rendue(): void
    {
        $this->get('/reset-password/'.Password::createToken($this->user()))->assertOk();
    }

    // =========================================================
    //  IDENTITÉ VISUELLE
    // =========================================================

    public function test_la_connexion_affiche_la_marque_et_la_baseline(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('tv-wordmark', $html);
        $this->assertStringContainsString('Du sujet à l’antenne,', $html);
        $this->assertStringContainsString('Informer. Coordonner. Inspirer.', $html);
    }

    public function test_la_connexion_reutilise_la_police_inter_du_projet(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('family=Inter', $html);
    }

    // =========================================================
    //  ABSENCES VOLONTAIRES
    // =========================================================

    public function test_la_connexion_n_affiche_plus_le_prenom_brut(): void
    {
        // Régression : l'accolade {Prénom} était rendue littéralement.
        $this->assertStringNotContainsString('{Prénom}', $this->get('/login')->assertOk()->getContent());
    }

    public function test_la_connexion_n_affiche_plus_de_bouton_google(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('Continuer avec Google', $html);
        $this->assertStringNotContainsString('btn-google', $html);
    }

    public function test_la_connexion_n_affiche_plus_de_selecteur_de_langue(): void
    {
        $this->assertStringNotContainsString('expand_more', $this->get('/login')->assertOk()->getContent());
    }

    public function test_les_pages_n_utilisent_plus_d_image_externe(): void
    {
        foreach (array_merge($this->pages(), ['/reset-password/test-token']) as $page) {
            $html = $this->get($page)->getContent();

            $this->assertStringNotContainsString('unsplash', $html);
            $this->assertStringNotContainsString('trae.ai', $html);
        }
    }

    // =========================================================
    //  ACCESSIBILITÉ & FORMULAIRE
    // =========================================================

    public function test_le_formulaire_de_connexion_est_valide(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('login').'"', $html);
        $this->assertStringContainsString('aria-label', $html);
        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_la_connexion_conserve_l_email_apres_une_erreur(): void
    {
        $this->from('/login')
            ->post('/login', [
                'email' => 'journaliste@tvplusafrique.com',
                'password' => 'mauvais',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        // L'e-mail est conservé en « old input » sur la page de connexion suivante.
        $this->assertStringContainsString(
            'journaliste@tvplusafrique.com',
            $this->get('/login')->assertOk()->getContent()
        );
    }
}
