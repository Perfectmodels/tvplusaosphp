<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SharedNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    /** @return list<string> */
    private function navigationLinks(string $html): array
    {
        $this->assertSame(1, preg_match('/<nav\b[^>]*\bid="app-sidebar".*?<\/nav>/s', $html, $sidebar));
        preg_match_all('/href="([^"]+)"/', $sidebar[0], $matches);

        return array_map(static fn (string $href): string => html_entity_decode($href), $matches[1]);
    }

    public function test_le_menu_original_reste_identique_du_dashboard_aux_fonctionnalites(): void
    {
        $this->actingAs($this->user('admin'));
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $navigation = $this->navigationLinks($dashboard->getContent());

        foreach (['Système d\'exploitation TV Afrique', 'Conducteurs · Rédaction', "Conducteurs · Chef d'Édition", 'Veille & Sources', 'Journalistes', 'Intervenants', 'Administrer', 'Gestion utilisateurs', 'Journal d’activité'] as $label) {
            $dashboard->assertSee($label, false);
        }

        foreach (['intervenants.index', 'journalistes.index', 'emissions.index', 'conducteurs.redaction.index', 'conducteurs.emission.index', 'veille.sujets.index', 'veille.sources', 'users.index', 'profile.edit'] as $route) {
            $response = $this->get(route($route))->assertOk();

            $this->assertSame($navigation, $this->navigationLinks($response->getContent()), 'Le menu change sur '.$route);
        }
    }

    public function test_la_navigation_respecte_les_droits_sur_les_pages_metier_et_le_dashboard(): void
    {
        $this->actingAs($this->user('chef_edition'));

        foreach (['dashboard', 'emissions.index'] as $route) {
            $response = $this->get(route($route))->assertOk();
            $navigation = $this->navigationLinks($response->getContent());

            $this->assertNotContains(route('users.index'), $navigation);
            $this->assertNotContains(route('admin.activity-logs'), $navigation);
        }
    }

    public function test_la_page_intervenants_peut_etre_affichee_plusieurs_fois(): void
    {
        $this->actingAs($this->user('admin'));

        $this->get(route('intervenants.index'))->assertOk()->assertSee('Disponibles');
        $this->get(route('intervenants.index', ['filtre' => 'nouveaux']))->assertOk()->assertSee('Nouveaux');
    }
}
