<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_is_reachable(): void
    {
        // La racine rend la page d'accueil publique (200). L'ancien comportement
        // redirigeait vers /dashboard, ce qui n'est plus le cas.
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_la_page_d_accueil_respecte_la_charte_graphique(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Charte M3 de l'app : fond Material 3 + marque TV+A OS.
        $this->assertStringContainsString('bg-background', $html);
        $this->assertStringContainsString('TV+A OS', $html);

        // ⛔ Le style « immersif » hors charte (fond noir #030813, cyan sci-fi)
        //    ne doit plus apparaître sur la page d'accueil.
        $this->assertStringNotContainsString('tvos-landing', $html);
    }

    public function test_login_page_works(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }
}
