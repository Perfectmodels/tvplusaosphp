<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurEditionPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00', 'Africa/Libreville'));
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    private function conducteur(string $date, string $name, string $type = 'redaction'): Conducteur
    {
        return Conducteur::create([
            'nom' => $name,
            'date_edition' => $date,
            'type' => $type,
            'emission_id' => $type === 'emission' ? Emission::create([
                'nom' => 'Programme '.$name,
                'title' => 'Programme '.$name,
                'slug' => Str::uuid()->toString(),
                'is_active' => true,
            ])->id : null,
        ]);
    }

    public static function indexRoutes(): array
    {
        return [
            'redaction' => ['conducteurs.redaction.index', 'redaction'],
            'emission' => ['conducteurs.emission.index', 'emission'],
            'presentateur' => ['conducteurs.presentateur.index', 'emission'],
        ];
    }

    #[DataProvider('indexRoutes')]
    public function test_chaque_page_affiche_une_seule_date_d_edition(string $route, string $type): void
    {
        $today = $this->conducteur('2026-11-13', 'Édition du jour', $type);
        $yesterday = $this->conducteur('2026-11-12', 'Édition précédente', $type);
        $this->actingAs($this->user());

        $this->get(route($route))->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$today->id])
            ->assertViewHas('editions', fn ($pages) => $pages->total() === 2 && $pages->perPage() === 1)
            ->assertSee('Page 1 sur 2 dates d’édition');

        $this->get(route($route, ['page' => 2]))->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$yesterday->id])
            ->assertViewHas('dateEdition', fn ($date) => $date->toDateString() === '2026-11-12');
    }

    #[DataProvider('indexRoutes')]
    public function test_une_creation_ancienne_ou_future_ne_remplace_pas_l_edition_du_jour(string $route, string $type): void
    {
        $today = $this->conducteur('2026-11-13', 'Rendez-vous du jour', $type);
        $this->conducteur('2026-11-12', 'Création tardive pour hier', $type);
        $this->conducteur('2026-11-15', 'Préparation future', $type);

        $this->actingAs($this->user())->get(route($route))->assertOk()
            ->assertViewHas('dateEdition', fn ($date) => $date->isToday())
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$today->id])
            ->assertViewHas('editions', fn ($pages) => $pages->currentPage() === 2);
    }

    public function test_tous_les_conducteurs_d_une_date_restent_ensemble_dans_un_ordre_stable(): void
    {
        $ids = [];
        foreach (range(1, 26) as $index) {
            $ids[] = $this->conducteur('2026-11-13', 'Conducteur du jour '.$index)->id;
        }
        $this->conducteur('2026-11-12', 'Hier');

        $this->actingAs($this->user())->get(route('conducteurs.redaction.index'))->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === $ids)
            ->assertViewHas('editions', fn ($pages) => $pages->total() === 2);
    }

    public function test_le_choix_d_une_date_retrouve_ses_conducteurs(): void
    {
        $this->conducteur('2026-11-13', 'Aujourd’hui');
        $old = $this->conducteur('2026-11-10', 'Édition à retrouver');

        $this->actingAs($this->user())->get(route('conducteurs.redaction.index', ['date' => '2026-11-10']))
            ->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$old->id])
            ->assertViewHas('editions', fn ($pages) => ! str_contains($pages->url(1), 'date='));
    }

    public function test_une_date_sans_conducteur_ne_montre_pas_une_autre_journee(): void
    {
        $this->conducteur('2026-11-12', 'Conducteur existant');

        $this->actingAs($this->user())->get(route('conducteurs.redaction.index', ['date' => '2026-11-13']))
            ->assertOk()->assertViewHas('conducteurs', fn ($items) => $items->isEmpty())
            ->assertViewHas('dateEdition', fn ($date) => $date->toDateString() === '2026-11-13');
    }

    public function test_la_derniere_edition_passee_est_preferee_a_une_creation_future(): void
    {
        $yesterday = $this->conducteur('2026-11-12', 'Dernière édition passée');
        $this->conducteur('2026-11-20', 'Création future');

        $this->actingAs($this->user())->get(route('conducteurs.redaction.index'))->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$yesterday->id]);
    }

    public function test_les_conducteurs_sans_date_restent_accessibles_sur_une_page_a_classer(): void
    {
        $this->conducteur('2026-11-13', 'Édition datée');
        $undated = $this->conducteur('2026-11-12', 'Ancien conducteur sans date');
        Conducteur::whereKey($undated->id)->update(['date_edition' => null]);

        $this->actingAs($this->user())->get(route('conducteurs.redaction.index', ['page' => 2]))
            ->assertOk()->assertSee('Sans date d’édition — À classer')->assertSee('Date à renseigner')
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$undated->id]);
    }

    public function test_une_date_vide_et_une_page_hors_limite_ne_melangent_pas_les_editions(): void
    {
        $this->conducteur('2026-11-13', 'Édition existante');
        $this->actingAs($this->user());

        $this->get(route('conducteurs.redaction.index', ['date' => '2020-01-01']))->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->isEmpty())
            ->assertViewHas('editions', fn ($pages) => $pages->currentPage() === 1);

        $this->getJson(route('conducteurs.redaction.index', ['date' => 'date-invalide']))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_l_entete_conserve_le_menu_et_propose_des_actions_utilisables(): void
    {
        $this->actingAs($this->user())->get(route('dashboard'))->assertOk()
            ->assertSee('id="app-sidebar"', false)
            ->assertSee(route('dashboard').'#workspace-priorities', false)
            ->assertSee(route('dashboard', ['role' => 'admin']), false)
            ->assertSee('aria-controls="workspace-filters"', false)
            ->assertSee('aria-label="Rechercher dans mon espace"', false);
    }

    public function test_la_redaction_ne_propose_que_des_actions_sur_les_conducteurs_de_redaction(): void
    {
        $conducteur = $this->conducteur('2026-11-13', 'Réunion de rédaction');
        $response = $this->actingAs($this->user())->get(route('conducteurs.redaction.index'))->assertOk();
        preg_match('/<main\b[^>]*>(.*?)<\/main>/s', $response->getContent(), $matches);
        $content = $matches[1];

        $response->assertSee('Conducteurs · Rédaction')->assertDontSee('Suivi de Production Éditoriale');
        $this->assertStringContainsString(route('conducteurs.redaction.show', $conducteur), $content);
        $this->assertStringContainsString('Ouvrir le conducteur', $content);
        $this->assertStringContainsString(route('conducteurs.redaction.create', ['date' => '2026-11-13']), $content);
        $this->assertStringNotContainsString(route('conducteurs.chef_edition'), $content);
        $this->assertStringNotContainsString(route('conducteurs.emission.index'), $content);
        $this->assertStringNotContainsString('Ouvrir JT', $content);
        $this->assertStringNotContainsString('Timeline', $content);

        $this->get(route('conducteurs.redaction.show', $conducteur))->assertOk();
    }

    public function test_la_redaction_vide_propose_la_creation_pour_la_date_consultee(): void
    {
        $this->actingAs($this->user())->get(route('conducteurs.redaction.index', ['date' => '2026-11-12']))
            ->assertSee('Aucun conducteur de rédaction pour cette date.')
            ->assertSee(route('conducteurs.redaction.create', ['date' => '2026-11-12']), false)
            ->assertDontSee('Aller en Chef Édition')->assertDontSee('Vue Chef Édition');
    }

    public function test_le_formulaire_de_creation_reprend_la_date_consultee_et_refuse_une_date_invalide(): void
    {
        $this->actingAs($this->user())->get(route('conducteurs.redaction.create', ['date' => '2026-11-12']))
            ->assertOk()->assertViewHas('initialDateEdition', '2026-11-12')
            ->assertSee('value="2026-11-12"', false);

        $this->getJson(route('conducteurs.redaction.create', ['date' => 'invalide']))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_le_formulaire_presentateur_reprend_la_date_consultee_et_la_valide(): void
    {
        $this->actingAs($this->user())->get(route('conducteurs.presentateur.create', ['date' => '2026-11-12']))
            ->assertOk()->assertViewHas('initialDateEdition', '2026-11-12')
            ->assertSee('value="2026-11-12"', false)
            ->assertSee(route('conducteurs.presentateur.index', ['date' => '2026-11-12']), false);

        $this->getJson(route('conducteurs.presentateur.create', ['date' => 'invalide']))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    #[DataProvider('indexRoutes')]
    public function test_les_boutons_de_date_restent_dans_le_type_de_conducteur_consulte(string $route, string $type): void
    {
        $response = $this->actingAs($this->user())->get(route($route, ['date' => '2026-11-12']))
            ->assertOk()
            ->assertSee('id="edition-filter-form" action="'.route($route).'"', false)
            ->assertSee(route($route, ['date' => '2026-11-13']), false)
            ->assertSee('id="edition-filter-date" type="date" name="date" value="2026-11-12"', false)
            ->assertDontSee('id="edition-filter-type"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'id="edition-filter-form"'));
    }

    public function test_un_lecteur_ne_voit_pas_de_bouton_de_creation_dans_la_redaction(): void
    {
        $reader = User::factory()->create();
        $reader->assignRole(Role::findOrCreate('lecture', 'web'));

        $this->actingAs($reader)->get(route('conducteurs.redaction.index'))
            ->assertOk()->assertDontSee('Nouveau conducteur de rédaction');
    }

    public function test_l_emission_ne_propose_que_la_creation_et_l_ouverture_du_bon_conducteur(): void
    {
        $conducteur = $this->conducteur('2026-11-13', 'Émission du jour', 'emission');
        $response = $this->actingAs($this->user())->get(route('conducteurs.emission.index'))->assertOk();
        preg_match('/<main\b[^>]*>(.*?)<\/main>/s', $response->getContent(), $matches);
        $content = $matches[1];

        $response->assertSee('Conducteurs · Émission');
        $this->assertStringContainsString(route('conducteurs.emission.show', $conducteur), $content);
        $this->assertStringContainsString('Ouvrir le conducteur', $content);
        $this->assertStringContainsString(route('conducteurs.emission.create', ['date' => '2026-11-13']), $content);
        $this->assertStringNotContainsString(route('conducteurs.redaction.index'), $content);
        $this->assertStringNotContainsString(route('conducteurs.presentateur.index'), $content);
        $this->assertStringNotContainsString(route('conducteurs.chef_edition'), $content);
        $this->assertStringNotContainsString('Vue Présentateur', $content);

        $this->get(route('conducteurs.emission.show', $conducteur))->assertOk();
    }

    public function test_l_emission_vide_propose_la_creation_pour_la_date_consultee(): void
    {
        $this->actingAs($this->user())->get(route('conducteurs.emission.index', ['date' => '2026-11-12']))
            ->assertOk()->assertSee("Aucun conducteur d'émission pour cette date.", false)
            ->assertSee(route('conducteurs.emission.create', ['date' => '2026-11-12']), false)
            ->assertDontSee('Vue Rédaction')->assertDontSee('Aller en Rédaction');
    }

    public function test_le_formulaire_d_emission_conserve_la_date_choisie_et_la_valide(): void
    {
        $this->actingAs($this->user())->get(route('conducteurs.emission.create', ['date' => '2026-11-12']))
            ->assertOk()->assertViewHas('initialDateEdition', '2026-11-12')
            ->assertSee('value="2026-11-12"', false)
            ->assertSee(route('conducteurs.emission.index', ['date' => '2026-11-12']), false);

        $this->getJson(route('conducteurs.emission.create', ['date' => 'invalide']))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_un_lecteur_peut_consulter_les_emissions_sans_bouton_de_creation(): void
    {
        $reader = User::factory()->create();
        $reader->assignRole(Role::findOrCreate('lecture', 'web'));

        $this->actingAs($reader)->get(route('conducteurs.emission.index'))->assertOk()
            ->assertDontSee("Nouveau conducteur d'émission", false);
        $this->get(route('conducteurs.emission.create'))->assertForbidden();
    }
}
