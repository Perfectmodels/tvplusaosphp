<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmissionCatalogOrganisationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function emission(string $name, array $attributes = []): Emission
    {
        return Emission::create(array_merge([
            'nom' => $name, 'type' => TypeEmissionEnum::JT->value,
            'statut' => StatutEmissionEnum::Planifiee->value,
            'heure_debut' => null, 'heure_fin' => null,
            'duree_minutes' => 0, 'duration_minutes' => 0,
        ], $attributes));
    }

    public function test_le_catalogue_regroupe_les_formats_et_classe_les_horaires_avant_les_programmes_a_planifier(): void
    {
        $this->emission('Un magazine', ['type' => TypeEmissionEnum::Mag->value]);
        $this->emission('Un débat', ['type' => TypeEmissionEnum::Debat->value]);
        $this->emission('JT sans horaire');
        $this->emission('JT du soir', ['heure_debut' => '20:00']);
        $this->emission('JT du matin', ['heure_debut' => '09:00']);

        $this->actingAs($this->user())->get(route('emissions.index'))->assertOk()
            ->assertViewHas('emissions', fn ($items) => $items->pluck('nom')->all() === ['JT du matin', 'JT du soir', 'JT sans horaire', 'Un magazine', 'Un débat'])
            ->assertViewHas('emissionGroups', fn ($groups) => $groups->keys()->all() === [TypeEmissionEnum::JT->value, TypeEmissionEnum::Mag->value, TypeEmissionEnum::Debat->value])
            ->assertSee('Journaux télévisés')->assertSee('Magazines')->assertSee('Débats');
    }

    public function test_un_horaire_inconnu_reste_a_renseigner_sans_afficher_une_duree_fictive(): void
    {
        $this->emission('Programme à compléter');

        $this->actingAs($this->user())->get(route('emissions.index'))->assertOk()
            ->assertSee('Horaire à renseigner')->assertSee('Jours à renseigner')
            ->assertDontSee('0 min')->assertDontSee('7 prochains jours')
            ->assertDontSee('Filtres avancés')->assertDontSee('Exporter');
    }

    public function test_les_filtres_et_la_pagination_restent_combines(): void
    {
        foreach (range(1, 13) as $number) {
            $this->emission('Journal régional '.$number, ['jours_diffusion' => ['ven']]);
        }
        $this->emission('Autre jour', ['jours_diffusion' => ['lun']]);
        $this->emission('Autre statut', ['statut' => StatutEmissionEnum::Archivee->value, 'jours_diffusion' => ['ven']]);
        $filters = ['q' => 'Journal régional', 'type' => TypeEmissionEnum::JT->value, 'statut' => StatutEmissionEnum::Planifiee->value, 'jour' => 'ven'];

        $this->actingAs($this->user())->get(route('emissions.index', $filters))->assertOk()
            ->assertDontSee('Autre jour')->assertDontSee('Autre statut')
            ->assertViewHas('emissions', function ($items) use ($filters): bool {
                parse_str(parse_url($items->url(2), PHP_URL_QUERY), $query);
                $this->assertSame($filters, array_intersect_key($query, $filters));

                return $items->total() === 13 && $items->perPage() === 12;
            });
    }

    public function test_le_compteur_du_jour_utilise_le_code_du_jour_et_exclut_les_archives_et_les_programmes_inactifs(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00', 'Africa/Libreville'));
        $this->emission('Vendredi actif', ['jours_diffusion' => ['ven']]);
        $this->emission('Lundi', ['jours_diffusion' => ['lun']]);
        $this->emission('Vendredi archivé', ['jours_diffusion' => ['ven'], 'statut' => StatutEmissionEnum::Archivee->value]);
        $this->emission('Vendredi inactif', ['jours_diffusion' => ['ven'], 'is_active' => false]);

        $this->actingAs($this->user())->get(route('emissions.index'))->assertOk()
            ->assertViewHas('jourActuel', 'ven')->assertViewHas('stats', fn ($stats) => $stats['aujourdhui'] === 1);
    }

    public function test_les_liens_du_programme_ouvrent_ses_conducteurs_et_preselectionnent_sa_creation(): void
    {
        $programme = $this->emission('Émission à préparer');
        Conducteur::create(['emission_id' => $programme->id, 'type' => 'emission', 'date_edition' => '2026-11-13']);
        Conducteur::create(['emission_id' => $programme->id, 'type' => 'redaction', 'date_edition' => '2026-11-13']);
        $this->actingAs($this->user())->get(route('emissions.index'))->assertOk()
            ->assertSee(route('conducteurs.emission.index', ['emission_id' => $programme->id]))
            ->assertSee(route('conducteurs.emission.create', ['emission_id' => $programme->id, 'date' => today()->toDateString()]))
            ->assertViewHas('emissions', fn ($items) => $items->first()->conducteurs_count === 1);

        $this->get(route('conducteurs.emission.create', ['emission_id' => $programme->id, 'date' => '2026-11-12']))
            ->assertOk()->assertViewHas('selectedEmissionId', $programme->id)
            ->assertSee(route('conducteurs.emission.index', ['emission_id' => $programme->id, 'date' => '2026-11-12']))
            ->assertSee('value="'.$programme->id.'" selected', false)
            ->assertSee('value="2026-11-12"', false);
    }

    public function test_les_conducteurs_restent_filtres_par_programme_en_changeant_de_date(): void
    {
        $programme = $this->emission('Programme choisi');
        $other = $this->emission('Autre programme');
        $today = Conducteur::create(['nom' => 'Conducteur choisi du jour', 'emission_id' => $programme->id, 'type' => 'emission', 'date_edition' => today()->toDateString()]);
        $yesterday = Conducteur::create(['nom' => 'Conducteur choisi précédent', 'emission_id' => $programme->id, 'type' => 'emission', 'date_edition' => today()->subDay()->toDateString()]);
        Conducteur::create(['nom' => 'Conducteur à exclure', 'emission_id' => $other->id, 'type' => 'emission', 'date_edition' => today()->toDateString()]);
        $this->actingAs($this->user());

        $this->get(route('conducteurs.emission.index', ['emission_id' => $programme->id]))->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$today->id])
            ->assertViewHas('editions', fn ($pages) => str_contains($pages->url(2), 'emission_id='.$programme->id))
            ->assertSee('name="emission_id" value="'.$programme->id.'"', false)
            ->assertDontSee('Conducteur à exclure');
        $this->get(route('conducteurs.emission.index', ['emission_id' => $programme->id, 'page' => 2]))->assertOk()
            ->assertViewHas('conducteurs', fn ($items) => $items->modelKeys() === [$yesterday->id]);
    }

    public function test_une_emission_inactive_ne_peut_pas_etre_preselectionnee_pour_creer_un_conducteur(): void
    {
        $programme = $this->emission('Programme inactif', ['is_active' => false]);

        $this->actingAs($this->user())->getJson(route('conducteurs.emission.create', ['emission_id' => $programme->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('emission_id');
    }

    public function test_un_lecteur_consulte_le_catalogue_sans_actions_de_gestion(): void
    {
        $this->emission('Émission visible');

        $response = $this->actingAs($this->user('lecture'))->get(route('emissions.index'))->assertOk()
            ->assertSee('Émission visible')->assertDontSee('Nouvelle émission')
            ->assertDontSee('Préparer un conducteur');
        preg_match('/<main\b[^>]*>(.*?)<\/main>/s', $response->getContent(), $main);
        $this->assertStringNotContainsString('Supprimer', $main[1]);
    }
}
