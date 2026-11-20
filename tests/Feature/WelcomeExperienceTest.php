<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_introduction_ne_propose_ni_contournement_ni_commande_de_coupure_du_son(): void
    {
        $html = $this->get('/')->assertOk()->assertSee('data-intro-active', false)->assertSee('data-destination="'.route('login').'"', false)->getContent();
        $this->assertStringNotContainsString('Continuer sans son', $html);
        $this->assertStringNotContainsString('data-intro-skip', $html);
        $this->assertStringNotContainsString('tvos-intro-mute', $html);
        $this->assertStringNotContainsString('Revoir l’accueil immersif', $html);
        $this->assertStringContainsString('id="tvos-intro-status"', $html);
        $this->assertStringNotContainsString('Commencer l’introduction', $html);
    }

    public function test_la_voix_demarre_automatiquement_et_reserve_le_bouton_au_blocage_du_navigateur(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<audio\b[^>]*id="tvos-intro-audio"[^>]*>(.*?)<\/audio>/s', $html, $match);
        $this->assertNotEmpty($match);
        $this->assertStringContainsString('preload="auto"', $match[0]);
        $this->assertStringContainsString('/media/audio-oeil.mp3', $match[0]);
        $this->assertStringContainsString('autoplay', $match[0]);
        $this->assertStringNotContainsString('controls', $match[0]);
        preg_match('/<button\b[^>]*id="tvos-intro-enter"[^>]*>/', $html, $button);
        $this->assertNotEmpty($button);
        $this->assertStringContainsString('hidden', $button[0]);
        $this->assertStringNotContainsString('data:audio', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertLessThan(40000, strlen($html));
        $this->assertFileExists(public_path('media/audio-oeil.mp3'));
    }

    public function test_l_oeil_n_embarque_plus_les_scripts_globaux_du_document_original(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, ' data-regard>'));
        $this->assertStringNotContainsString('id="eye"', $html);
        $this->assertStringNotContainsString('id="fx"', $html);
        $this->assertStringNotContainsString('document.getElementById(\'eye\')', $html);
        $this->assertStringNotContainsString('$attributes', $html);
        $this->assertStringContainsString('welcome-eye-', $html);
        $this->assertStringContainsString('welcome-intro-', $html);
    }

    public function test_le_tableau_de_bord_partage_le_meme_oeil_sans_introduction_sonore(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('tvos-regard--compact', $html);
        $this->assertStringContainsString('welcome-eye-', $html);
        $this->assertStringNotContainsString('id="tvos-welcome-intro"', $html);
        $this->assertStringNotContainsString('images/oeil.png', $html);
    }

    public function test_la_connexion_propose_un_vrai_formulaire_et_un_guide_sans_rejouer_la_voix(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('login').'"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('Première connexion ?', $html);
        $this->assertSame(5, substr_count($html, ' role="tab"'));
        $this->assertSame(5, substr_count($html, ' role="tabpanel"'));
        foreach (['Rédaction', 'Production', 'Antenne', 'Équipe', 'Digital'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringNotContainsString('id="tvos-welcome-intro"', $html);
        $this->assertStringNotContainsString('audio-oeil.mp3', $html);
    }
}
