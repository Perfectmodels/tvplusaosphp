<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardConferencePointageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00', 'Africa/Libreville'));
    }

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function conference(array $attributes = []): Conducteur
    {
        return Conducteur::create(array_merge([
            'nom' => 'Conférence du matin', 'type' => 'redaction', 'date_edition' => '2026-11-13',
            'conference_started_at' => '2026-11-13 09:00:00',
        ], $attributes));
    }

    private function presence(Conducteur $conference, ?string $status): ConducteurPresence
    {
        $journaliste = Journaliste::create(['nom' => 'Journaliste '.(Journaliste::count() + 1), 'prenom' => 'Test', 'is_active' => true]);

        return ConducteurPresence::create([
            'conducteur_id' => $conference->id, 'journaliste_id' => $journaliste->id,
            'arrivee_at' => '2026-11-13 09:15:00', 'ponctualite' => $status,
        ]);
    }

    public function test_le_dashboard_affiche_les_resultats_manuels_les_personnes_et_le_lien_du_pointage(): void
    {
        $conference = $this->conference();
        $this->presence($conference, 'a_l_heure');
        $this->presence($conference, 'en_retard');
        $this->presence($conference, null);

        $this->actingAs($this->user())->get(route('dashboard'))->assertOk()
            ->assertViewHas('pointageConference', fn ($data) => $data['totaux'] === ['conferences' => 1, 'presences' => 3, 'a_l_heure' => 1, 'en_retard' => 1, 'a_pointer' => 1])
            ->assertSee('Pointage · Conférences de rédaction')->assertSee('Conférence du matin')
            ->assertSee('Test Journaliste 1')->assertSee('Test Journaliste 2')->assertSee('À pointer')
            ->assertSee(route('conducteurs.redaction.show', $conference).'#presences');
    }

    public function test_un_seul_clic_sur_le_resume_revele_tous_les_noms_et_statuts_dans_un_panneau_replie_par_defaut(): void
    {
        $this->presence($this->conference(), 'a_l_heure');
        $this->presence($this->conference(['nom' => 'Conférence de l’après-midi']), 'en_retard');
        $html = $this->actingAs($this->user())->getJson(route('dashboard.pointage'))->assertOk()->json('html');

        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);

        $this->assertSame(1, $xpath->query('//section/details')->length);
        $this->assertSame(0, $xpath->query('//details[@open]')->length);
        $this->assertSame(0, $xpath->query('//details//details')->length);
        $summary = $xpath->query('//details/summary')->item(0);
        $this->assertStringContainsString('Voir personne', $summary->textContent);
        $this->assertStringNotContainsString('Réduire', $summary->textContent);
        $this->assertSame('1', $xpath->query('.//*[@data-pointage-total="a_l_heure"]', $summary)->item(0)->textContent);
        $this->assertSame('1', $xpath->query('.//*[@data-pointage-total="en_retard"]', $summary)->item(0)->textContent);

        $people = $xpath->query('//details//*[@data-pointage-list]//li');
        $this->assertSame(2, $people->length);
        $this->assertStringContainsString('À l’heure', $xpath->query('//li[.//span[contains(text(), "Test Journaliste 1")]]')->item(0)->textContent);
        $this->assertStringContainsString('En retard', $xpath->query('//li[.//span[contains(text(), "Test Journaliste 2")]]')->item(0)->textContent);
    }

    public function test_le_bilan_exclut_les_conferences_anciennes_et_les_conducteurs_d_emission(): void
    {
        $this->presence($this->conference(), 'a_l_heure');
        $this->presence($this->conference(['nom' => 'Ancienne conférence à exclure', 'date_edition' => '2026-11-12', 'conference_started_at' => '2026-11-12 09:00:00']), 'en_retard');
        $this->presence($this->conference(['nom' => 'Émission à exclure', 'type' => 'emission']), 'en_retard');

        $this->actingAs($this->user())->getJson(route('dashboard.pointage'))->assertOk()
            ->assertJsonPath('pointage.totaux.conferences', 1)->assertJsonPath('pointage.totaux.en_retard', 0)
            ->assertDontSee('Ancienne conférence à exclure')->assertDontSee('Émission à exclure');
    }

    public function test_une_conference_commencee_aujourdhui_apparait_meme_si_son_edition_est_demain(): void
    {
        $conference = $this->conference(['date_edition' => '2026-11-14']);
        $this->presence($conference, 'en_retard');

        $this->actingAs($this->user())->getJson(route('dashboard.pointage'))->assertOk()
            ->assertJsonPath('pointage.totaux.en_retard', 1)->assertJsonPath('pointage.conferences.0.edition', '14/11/2026');
    }

    public function test_les_statuts_non_pointes_ne_deviennent_pas_automatiquement_des_retards(): void
    {
        $conference = $this->conference(['conference_started_at' => null]);
        $this->presence($conference, null);

        $this->actingAs($this->user())->getJson(route('dashboard.pointage'))->assertOk()
            ->assertJsonPath('pointage.totaux.a_l_heure', 0)->assertJsonPath('pointage.totaux.en_retard', 0)
            ->assertJsonPath('pointage.totaux.a_pointer', 1)->assertJsonPath('pointage.conferences.0.debut', null);
    }

    public function test_l_actualisation_du_dashboard_reflete_une_correction_du_pointage(): void
    {
        $conference = $this->conference();
        $presence = $this->presence($conference, null);
        $this->actingAs($this->user());
        $this->getJson(route('dashboard.pointage'))->assertOk()->assertJsonPath('pointage.totaux.a_pointer', 1);

        $this->patchJson(route('conducteurs.redaction.presence.ponctualite', [$conference, $presence]), ['ponctualite' => 'en_retard'])->assertOk();
        $this->getJson(route('dashboard.pointage'))->assertOk()
            ->assertJsonPath('pointage.totaux.en_retard', 1)->assertJsonPath('pointage.totaux.a_pointer', 0)
            ->assertJsonPath('pointage.conferences.0.personnes.0.ponctualite_label', 'En retard');

        $this->patchJson(route('conducteurs.redaction.presence.ponctualite', [$conference, $presence]), ['ponctualite' => 'a_l_heure'])->assertOk();
        $this->getJson(route('dashboard.pointage'))->assertOk()
            ->assertJsonPath('pointage.totaux.en_retard', 0)->assertJsonPath('pointage.totaux.a_l_heure', 1);
    }

    public static function dashboardRoles(): array
    {
        return [
            'admin' => ['admin'], 'edition' => ['chef_edition'], 'redaction' => ['chef_redaction'],
            'direction' => ['directeur_general'], 'journaliste' => ['journaliste'], 'lecture' => ['lecture'],
        ];
    }

    #[DataProvider('dashboardRoles')]
    public function test_le_pointage_est_visible_dans_les_differents_tableaux_de_bord_autorises(string $role): void
    {
        $this->presence($this->conference(), 'a_l_heure');

        $this->actingAs($this->user($role))->get(route('dashboard'))->assertOk()
            ->assertSee('id="dashboard-conference-pointage"', false);
        $this->getJson(route('dashboard.pointage'))->assertOk()->assertJsonPath('pointage.totaux.a_l_heure', 1);
    }

    public function test_un_dashboard_sans_conference_affiche_un_etat_vide_et_des_compteurs_nuls(): void
    {
        $this->actingAs($this->user())->get(route('dashboard'))->assertOk()
            ->assertSee('Aucune conférence de rédaction pour aujourd’hui.')
            ->assertViewHas('pointageConference', fn ($data) => $data['totaux']['presences'] === 0 && $data['conferences'] === []);
    }

    public function test_le_flux_de_pointage_necessite_une_authentification_et_une_fonction_autorisee(): void
    {
        $this->getJson(route('dashboard.pointage'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('dashboard.pointage'))->assertForbidden();
    }

    public function test_les_noms_dans_le_fragment_de_pointage_sont_echappes(): void
    {
        $this->presence($this->conference(['nom' => '<script>pointageDangereux()</script>']), 'a_l_heure');
        $this->actingAs($this->user());

        $data = $this->getJson(route('dashboard.pointage'))->assertOk()->json();

        $this->assertStringNotContainsString('<script>pointageDangereux()</script>', $data['html']);
        $this->assertStringContainsString('&lt;script&gt;pointageDangereux()', $data['html']);
    }
}
