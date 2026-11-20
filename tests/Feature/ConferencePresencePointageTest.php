<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PonctualitePresenceEnum;
use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConferencePresencePointageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-13 09:30:00', 'Africa/Libreville'));
    }

    private function user(string $role = 'chef_redaction'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function conducteur(array $attributes = []): Conducteur
    {
        return Conducteur::create(array_merge(['nom' => 'Conférence de rédaction', 'type' => 'redaction', 'date_edition' => '2026-11-13'], $attributes));
    }

    private function presence(Conducteur $conducteur, string $time = '09:10'): ConducteurPresence
    {
        $journaliste = Journaliste::create(['nom' => 'Journaliste test '.(Journaliste::count() + 1), 'prenom' => 'Anne', 'is_active' => true]);

        return ConducteurPresence::create(['conducteur_id' => $conducteur->id, 'journaliste_id' => $journaliste->id, 'arrivee_at' => '2026-11-13 '.$time]);
    }

    public function test_le_debut_effectif_est_enregistre_avec_son_auteur_et_le_bon_fuseau_horaire(): void
    {
        $chef = $this->user();
        $conducteur = $this->conducteur();

        $this->actingAs($chef)->patchJson(route('conducteurs.redaction.conference.update', $conducteur), ['conference_started_at' => '2026-11-13T08:00:00Z'])
            ->assertOk()->assertJsonPath('conference_started_at', '2026-11-13T09:00:00+01:00');

        $this->assertDatabaseHas('conducteurs', ['id' => $conducteur->id, 'conference_started_at' => '2026-11-13 09:00:00', 'conference_started_by' => $chef->id]);
    }

    public function test_enregistrer_le_debut_ne_classe_pas_automatiquement_les_presents(): void
    {
        $conducteur = $this->conducteur();
        $early = $this->presence($conducteur, '08:55');
        $late = $this->presence($conducteur, '09:15');

        $this->actingAs($this->user())->patchJson(route('conducteurs.redaction.conference.update', $conducteur), ['conference_started_at' => '2026-11-13T09:00'])->assertOk();

        $this->assertNull($early->fresh()->ponctualite);
        $this->assertNull($late->fresh()->ponctualite);
        $this->getJson(route('conducteurs.redaction.presence.index', $conducteur))->assertOk()
            ->assertJsonPath('presences.0.ponctualite_label', 'À pointer')->assertJsonPath('presences.1.ponctualite_label', 'À pointer');
    }

    public function test_le_responsable_pointe_manuellement_et_peut_corriger_son_choix(): void
    {
        $chef = $this->user();
        $conducteur = $this->conducteur(['conference_started_at' => '2026-11-13 09:00:00']);
        $presence = $this->presence($conducteur);
        $url = route('conducteurs.redaction.presence.ponctualite', [$conducteur, $presence]);

        $this->actingAs($chef)->patchJson($url, ['ponctualite' => 'en_retard'])->assertOk()
            ->assertJsonPath('presence.ponctualite_label', 'En retard');
        $this->assertSame(PonctualitePresenceEnum::EnRetard, $presence->fresh()->ponctualite);
        $this->assertSame($chef->id, $presence->fresh()->ponctualite_marked_by);
        $this->assertNotNull($presence->fresh()->ponctualite_marked_at);

        $this->patchJson($url, ['ponctualite' => 'a_l_heure'])->assertOk()->assertJsonPath('presence.ponctualite_label', 'À l’heure');
        $this->assertSame(PonctualitePresenceEnum::ALHeure, $presence->fresh()->ponctualite);
    }

    public function test_le_pointage_est_refuse_tant_que_la_conference_n_a_pas_debute(): void
    {
        $conducteur = $this->conducteur();
        $presence = $this->presence($conducteur);

        $this->actingAs($this->user())->patchJson(route('conducteurs.redaction.presence.ponctualite', [$conducteur, $presence]), ['ponctualite' => 'en_retard'])
            ->assertUnprocessable()->assertJsonValidationErrors('ponctualite')
            ->assertJsonPath('errors.ponctualite.0', 'Enregistrez le début effectif de la conférence avant de pointer la ponctualité.');
        $this->assertNull($presence->fresh()->ponctualite);
    }

    public function test_le_debut_effectif_dans_le_futur_est_refuse(): void
    {
        $conducteur = $this->conducteur();

        $this->actingAs($this->user())->patchJson(route('conducteurs.redaction.conference.update', $conducteur), ['conference_started_at' => '2026-11-13T10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('conference_started_at');
        $this->assertNull($conducteur->fresh()->conference_started_at);
    }

    public function test_un_statut_inconnu_ou_absent_n_est_pas_enregistre(): void
    {
        $conducteur = $this->conducteur(['conference_started_at' => '2026-11-13 09:00:00']);
        $presence = $this->presence($conducteur);
        $this->actingAs($this->user());
        $url = route('conducteurs.redaction.presence.ponctualite', [$conducteur, $presence]);

        $this->patchJson($url, ['ponctualite' => 'retard_automatique'])->assertUnprocessable()->assertJsonValidationErrors('ponctualite');
        $this->patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('ponctualite');
        $this->assertNull($presence->fresh()->ponctualite);
    }

    public function test_une_correction_du_debut_ou_de_l_arrivee_preserve_le_choix_manuel(): void
    {
        $conducteur = $this->conducteur(['conference_started_at' => '2026-11-13 09:00:00']);
        $presence = $this->presence($conducteur);
        $this->actingAs($this->user())->patchJson(route('conducteurs.redaction.presence.ponctualite', [$conducteur, $presence]), ['ponctualite' => 'en_retard'])->assertOk();

        $this->patchJson(route('conducteurs.redaction.conference.update', $conducteur), ['conference_started_at' => '2026-11-13T09:20'])->assertOk();
        $this->patchJson(route('conducteurs.redaction.presence.update', [$conducteur, $presence]), ['arrivee_at' => '2026-11-13T08:50'])->assertOk();

        $this->assertSame(PonctualitePresenceEnum::EnRetard, $presence->fresh()->ponctualite);
    }

    public function test_la_revision_de_la_liste_preserve_le_pointage_deja_effectue(): void
    {
        $conducteur = $this->conducteur(['conference_started_at' => '2026-11-13 09:00:00']);
        $presence = $this->presence($conducteur);
        $presence->update(['ponctualite' => PonctualitePresenceEnum::ALHeure]);

        $this->actingAs($this->user())->putJson(route('conducteurs.redaction.presence.reconcile', $conducteur), ['presences' => [['journaliste_id' => $presence->journaliste_id, 'arrivee_at' => '2026-11-13T09:15']]])
            ->assertOk()->assertJsonPath('presences.0.ponctualite', 'a_l_heure');
        $this->assertSame(PonctualitePresenceEnum::ALHeure, $presence->fresh()->ponctualite);
    }

    public function test_effacer_un_pointage_retablit_le_statut_a_pointer(): void
    {
        $conducteur = $this->conducteur(['conference_started_at' => '2026-11-13 09:00:00']);
        $presence = $this->presence($conducteur);
        $presence->update(['ponctualite' => 'en_retard', 'ponctualite_marked_at' => now(), 'ponctualite_marked_by' => $this->user()->id]);

        $this->actingAs($this->user())->patchJson(route('conducteurs.redaction.presence.ponctualite', [$conducteur, $presence]), ['ponctualite' => null])
            ->assertOk()->assertJsonPath('presence.ponctualite_label', 'À pointer');
        $this->assertNull($presence->fresh()->ponctualite);
        $this->assertNull($presence->fresh()->ponctualite_marked_at);
    }

    public function test_un_lecteur_consulte_les_pointages_sans_pouvoir_les_modifier(): void
    {
        $conducteur = $this->conducteur(['conference_started_at' => '2026-11-13 09:00:00']);
        $presence = $this->presence($conducteur);
        $this->actingAs($this->user('lecture'));

        $this->getJson(route('conducteurs.redaction.presence.index', $conducteur))->assertOk();
        $this->patchJson(route('conducteurs.redaction.conference.update', $conducteur), ['conference_started_at' => '2026-11-13T09:00'])->assertForbidden();
        $this->patchJson(route('conducteurs.redaction.presence.ponctualite', [$conducteur, $presence]), ['ponctualite' => 'en_retard'])->assertForbidden();
        $this->assertNull($presence->fresh()->ponctualite);
    }

    public function test_une_presence_d_un_autre_conducteur_ne_peut_pas_etre_pointee(): void
    {
        $conducteur = $this->conducteur(['conference_started_at' => '2026-11-13 09:00:00']);
        $other = $this->conducteur();
        $presence = $this->presence($other);

        $this->actingAs($this->user())->patchJson(route('conducteurs.redaction.presence.ponctualite', [$conducteur, $presence]), ['ponctualite' => 'en_retard'])->assertNotFound();
        $this->assertNull($presence->fresh()->ponctualite);
    }

    public function test_les_pointages_sont_reserves_a_la_redaction_et_aux_utilisateurs_connectes(): void
    {
        $conducteur = $this->conducteur(['type' => 'emission']);
        $this->getJson(route('conducteurs.redaction.presence.index', $conducteur))->assertUnauthorized();
        $this->actingAs($this->user())->getJson(route('conducteurs.redaction.presence.index', $conducteur))->assertNotFound();
    }

    public function test_la_fiche_affiche_le_debut_et_les_controles_de_pointage_manuel(): void
    {
        $conducteur = $this->conducteur();
        $this->presence($conducteur);

        $this->actingAs($this->user())->get(route('conducteurs.redaction.show', $conducteur))->assertOk()
            ->assertSee('Début effectif de la conférence')->assertSee('Démarrer la conférence maintenant')
            ->assertSee('Ponctualité · pointage manuel')->assertSee('En retard')
            ->assertDontSee('ecartAvecPremier', false);
    }
}
