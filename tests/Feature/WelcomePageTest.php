<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_l_ouverture_est_une_introduction_sonore_sans_donnees_simulees(): void
    {
        $this->get('/')->assertOk()->assertSee('L’introduction sonore démarre.')->assertSee('TV+A OS')
            ->assertSee('data-destination="'.route('login').'"', false)
            ->assertDontSee('Continuer sans son')->assertDontSee('Awa Diallo')->assertDontSee('Boubacar S.')
            ->assertDontSee('Invités jour')->assertDontSee('Traçabilité')->assertDontSee('On air')
            ->assertDontSee('1 – 4 sur 24 intervenants')->assertDontSee('tvplusos.app')
            ->assertDontSee('Laravel '.Application::VERSION)->assertDontSee('PHP '.PHP_VERSION);
    }

    public function test_un_compte_connecte_ne_revele_pas_de_donnees_privees_sur_l_introduction(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertOk()->assertSee('data-destination="'.route('login').'"', false)->assertDontSee($user->email);
    }
}
