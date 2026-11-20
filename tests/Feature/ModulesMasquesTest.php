<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * MODULES MASQUÉS POUR PLUS TARD (vues vides — 14/10/2026) :
 * Conférences · Ressources · Planning · Archives · Réglages.
 *
 * · Les routes sont commentées dans routes/module_autres.php → accès direct = 404.
 * · Les entrées de menu ont été retirées de _partials/sidebar.blade.php.
 * · Le lien « Voir tout » du dashboard vers Planning a été retiré.
 */
class ModulesMasquesTest extends TestCase
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
    private function urlsMasquees(): array
    {
        return ['/conferences', '/planning', '/ressources', '/archives', '/reglages'];
    }

    /** @return array<int, string> Libellés retirés du menu latéral. */
    private function libellesMasques(): array
    {
        return ['Conférences', 'Ressources', 'Planning', 'Archives', 'Réglages'];
    }

    // =========================================================
    //  ACCÈS DIRECT
    // =========================================================

    public function test_les_urls_des_modules_masques_retournent_404(): void
    {
        foreach ($this->urlsMasquees() as $url) {
            $this->actingAs($this->user())->get($url)->assertNotFound();
        }
    }

    // =========================================================
    //  MENU LATÉRAL
    // =========================================================

    public function test_la_sidebar_n_affiche_plus_les_modules_masques(): void
    {
        $html = $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        foreach ($this->libellesMasques() as $libelle) {
            // Le libellé ne doit plus apparaître comme intitulé d'entrée de menu.
            $this->assertDoesNotMatchRegularExpression(
                '/>\s*' . preg_quote($libelle, '/') . '\s*<\/span>/',
                $html,
                "Le menu latéral affiche encore « {$libelle} »."
            );
        }
    }

    public function test_la_sidebar_ne_pointe_plus_vers_les_modules_masques(): void
    {
        $html = $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        foreach ($this->urlsMasquees() as $url) {
            $this->assertStringNotContainsString(
                $url,
                $html,
                "Un lien pointe encore vers {$url}."
            );
        }
    }
}
