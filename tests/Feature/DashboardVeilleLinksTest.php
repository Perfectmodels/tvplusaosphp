<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Dashboard — les cartes « Veille Info & Alertes » doivent afficher
 * de VRAIS sujets de veille et pointer vers leur fiche.
 */
class DashboardVeilleLinksTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Role::findOrCreate('chef_edition');
        $u = User::factory()->create();
        $u->assignRole('chef_edition');

        return $u;
    }

    private function sujet(string $titre, ?SourceVeille $source = null): VeilleSubject
    {
        return VeilleSubject::create([
            'title' => $titre,
            'slug' => Str::slug($titre).'-'.uniqid(),
            'url' => 'https://exemple.test/'.uniqid(),
            'source_id' => $source?->id,
            'status' => 'pending',
            'scraped_at' => now(),
        ]);
    }

    // =========================================================
    //  LIENS VERS LA VEILLE
    // =========================================================

    public function test_voir_toute_la_veille_pointe_vers_la_liste_des_sujets(): void
    {
        $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('veille.sujets.index'), false);
    }

    public function test_chaque_carte_veille_pointe_vers_le_detail_du_sujet(): void
    {
        $s1 = $this->sujet('Premier sujet de veille');
        $s2 = $this->sujet('Deuxième sujet de veille');

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString("/veille/sujets/{$s1->id}", $html);
        $this->assertStringContainsString("/veille/sujets/{$s2->id}", $html);
    }

    public function test_le_libelle_de_la_carte_est_le_nom_de_la_source(): void
    {
        $source = SourceVeille::create([
            'name' => 'BBC World News',
            'type' => 'rss',
            'url' => 'https://bbc.test/rss',
            'is_active' => true,
        ]);
        $this->sujet('Sujet provenant de la BBC', $source);

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('BBC World News', $html);
    }

    public function test_les_titres_des_sujets_sont_affiches(): void
    {
        $this->sujet('Titre de veille tres reconnaissable XYZ');

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Titre de veille tres reconnaissable XYZ', $html);
    }

    // =========================================================
    //  AUCUN LIEN MORT
    // =========================================================

    public function test_le_dashboard_ne_contient_plus_de_lien_vide(): void
    {
        $this->sujet('Sujet quelconque');

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        // Tous les liens <a> doivent avoir une vraie destination
        preg_match_all('/<a[^>]+href="([^"]*)"/', $html, $m);
        $vides = array_values(array_filter($m[1], static fn ($h) => $h === '#' || trim($h) === ''));

        $this->assertSame([], $vides, 'Le dashboard contient encore des liens vides (href="#").');
    }

    public function test_le_dashboard_ne_propose_plus_le_module_planning(): void
    {
        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        // Le module Planning est masqué : plus de lien « Voir tout » vers /planning.
        $this->assertStringNotContainsString('/planning', $html);
    }

    // =========================================================
    //  ÉTAT VIDE
    // =========================================================

    public function test_etat_vide_quand_aucun_sujet_de_veille(): void
    {
        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Aucun sujet de veille', $html);
        // L'état vide propose quand même d'atteindre la veille et les sources
        $this->assertStringContainsString(route('veille.sujets.index'), $html);
        $this->assertStringContainsString(route('veille.sources'), $html);
    }

    public function test_seuls_les_trois_sujets_les_plus_recents_sont_affiches(): void
    {
        foreach (range(1, 5) as $i) {
            $s = $this->sujet("Sujet numero {$i}");
            $s->update(['scraped_at' => now()->subMinutes(10 * (6 - $i))]);
        }

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $nbCartes = preg_match_all('#/veille/sujets/\d+#', $html, $m);
        $this->assertSame(3, $nbCartes, 'Le dashboard doit afficher exactement 3 sujets.');
    }
}
