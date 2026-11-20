<?php

namespace Tests\Feature;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Models\Conducteur;
use App\Models\DigitalPublication;
use App\Models\EditorialEvent;
use App\Models\EditorialStory;
use App\Models\Emission;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string ...$roles): User
    {
        $user = User::factory()->create(['role' => $roles[0]]);
        $user->assignRole($roles);

        return $user;
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], ['journaliste', 'chef_redaction', 'chef_edition', 'realisateur', 'community_manager', 'presentateur', 'correspondant', 'monteur', 'documentaliste', 'responsable_administratif', 'directeur_general', 'admin', 'superadmin', 'habilleur', 'chef_plateau', 'receptionniste', 'lecture']);
    }

    #[DataProvider('roles')]
    public function test_chaque_role_dispose_d_un_espace_sans_donnees_simulees(string $role): void
    {
        $this->actingAs($this->actor($role))->get(route('dashboard'))->assertOk()
            ->assertSee('Mes priorités')->assertSee('Activité récente')
            ->assertSee('Aucune urgence, échéance proche ou validation attendue')
            ->assertDontSee('Awa Diallo');
    }

    public function test_les_sujets_et_les_indicateurs_du_journaliste_sont_personnels_et_non_limites_aux_huit_lignes(): void
    {
        $user = $this->actor('journaliste');
        $j = Journaliste::create(['nom' => 'Personnel', 'prenom' => 'Test', 'user_id' => $user->id]);
        $user->update(['journaliste_id' => $j->id]);
        $emission = Emission::create(['nom' => 'Journal vérifié', 'title' => 'Journal vérifié', 'slug' => 'journal-verifie']);
        $c = Conducteur::create(['nom' => 'Édition', 'type' => 'redaction', 'emission_id' => $emission->id, 'date_edition' => today()]);
        for ($i = 0; $i < 13; $i++) {
            Sequence::create(['conducteur_id' => $c->id, 'title' => 'Sujet personnel '.$i, 'position' => $i + 1, 'journaliste_responsable_id' => $j->id]);
        }
        $other = Journaliste::create(['nom' => 'Autre', 'prenom' => 'Test']);
        Sequence::create(['conducteur_id' => $c->id, 'title' => 'Sujet confidentiel autre équipe', 'position' => 14, 'journaliste_responsable_id' => $other->id]);
        $event = EditorialEvent::factory()->create();
        EditorialStory::factory()->create(['editorial_event_id' => $event->id, 'assigned_journaliste_id' => $j->id, 'status' => 'rejected']);
        EditorialStory::factory()->create(['editorial_event_id' => $event->id, 'title' => 'Correction confidentielle', 'assigned_journaliste_id' => $other->id, 'status' => 'rejected']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Sujet confidentiel autre équipe')
            ->assertDontSee('Correction confidentielle')
            ->assertViewHas('kpis', fn ($items) => $items[0]['value'] === 13 && $items[3]['value'] === 1)
            ->assertViewHas('sections', fn ($sections) => count($sections[0]['items']) === 8);
    }

    public function test_le_realisateur_voit_les_durees_medias_et_etapes_reelles_des_sequences(): void
    {
        $user = $this->actor('realisateur');
        $emission = Emission::create(['nom' => 'Journal du soir', 'title' => 'Journal du soir', 'slug' => 'journal-du-soir']);
        $conducteur = Conducteur::create(['nom' => 'Édition du soir', 'type' => 'emission', 'emission_id' => $emission->id, 'date_edition' => today()]);
        Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Reportage sur le terrain',
            'position' => 2,
            'duration_seconds' => 187,
            'video_url' => 'https://example.test/reportage.mp4',
            'statut_terrain' => StatutEtapeWorkflowEnum::Termine,
            'statut_montage' => StatutEtapeWorkflowEnum::En_cours,
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('sections', fn ($sections) => $sections[1]['title'] === 'Séquences, durées et médias'
                && str_contains($sections[1]['items'][0]['detail'], 'Durée prévue 3:07')
                && str_contains($sections[1]['items'][0]['detail'], '1 lien(s) média')
                && $sections[1]['items'][0]['status'] === 'Montage · En cours');
    }

    public function test_le_redacteur_en_chef_a_des_files_d_arbitrage_et_de_validation_distinctes(): void
    {
        $user = $this->actor('chef_redaction');
        $event = EditorialEvent::factory()->create(['title' => 'Arbitrage du matin']);
        EditorialStory::factory()->create([
            'editorial_event_id' => $event->id,
            'title' => 'Sujet à valider',
            'status' => 'awaiting_validation',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Sujets à arbitrer')
            ->assertSee('Arbitrage du matin')
            ->assertSee('Sujet à valider')
            ->assertViewHas('sections', fn ($sections) => array_column($sections, 'title') === [
                'Sujets à arbitrer',
                'Sujets à valider',
                'Conducteurs à valider',
                'Contenus numériques à valider',
            ]);
    }

    public function test_les_filtres_de_date_recherche_et_emission_s_appliquent_aux_donnees(): void
    {
        $user = $this->actor('chef_redaction');
        $e = Emission::create(['nom' => 'Émission filtrée', 'title' => 'Émission filtrée', 'slug' => 'filtre']);
        Conducteur::create(['nom' => 'À contrôler', 'type' => 'redaction', 'emission_id' => $e->id, 'date_edition' => today()->subDay()]);
        Conducteur::create(['nom' => 'Autre titre', 'type' => 'redaction', 'emission_id' => $e->id, 'date_edition' => today()]);
        $this->actingAs($user)->get(route('dashboard', ['from' => today()->subDay()->toDateString(), 'to' => today()->subDay()->toDateString(), 'emission_id' => $e->id, 'q' => 'contrôler']))->assertOk()->assertSee('À contrôler')->assertDontSee('Autre titre');
    }

    public function test_un_role_revoque_en_session_ou_dans_le_champ_legacy_ne_redevient_pas_accessible(): void
    {
        $user = $this->actor('journaliste', 'chef_redaction');
        $this->actingAs($user)->get(route('dashboard', ['role' => 'chef_redaction']))->assertOk();
        $user->syncRoles(['journaliste']);
        $user->update(['role' => 'chef_redaction']);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('activeDashboardRole', 'journaliste');
        $this->get(route('dashboard', ['role' => 'chef_redaction']))->assertForbidden();
    }

    public function test_le_presentateur_ne_voit_que_ses_conducteurs_valides(): void
    {
        $user = $this->actor('presentateur');
        $j = Journaliste::create(['nom' => 'Présentateur', 'prenom' => 'Test', 'user_id' => $user->id]);
        $e = Emission::create(['nom' => 'Mon antenne', 'title' => 'Mon antenne', 'slug' => 'mon-antenne', 'presentateur_id' => $j->id]);
        foreach (['Validé' => 'Conducteur prêt', 'Brouillon' => 'Conducteur non validé'] as $status => $title) {
            Conducteur::create(['nom' => $title, 'type' => 'emission', 'emission_id' => $e->id, 'date_edition' => today(), 'status' => $status]);
        }
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Conducteur prêt')->assertDontSee('Conducteur non validé')->assertViewHas('kpis', fn ($items) => $items[0]['value'] === 1);
    }

    public function test_le_community_manager_ne_lit_pas_les_publications_d_un_autre_utilisateur(): void
    {
        $user = $this->actor('community_manager');
        $other = $this->actor('community_manager');
        DigitalPublication::create(['title' => 'Contenu réservé à une autre personne', 'content' => 'Texte', 'platform' => 'site', 'status' => 'draft', 'created_by' => $other->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Contenu réservé à une autre personne')->assertViewHas('kpis', fn ($items) => $items[0]['value'] === 0);
    }

    public function test_le_filtre_emission_du_community_manager_est_limite_a_ses_publications(): void
    {
        $user = $this->actor('community_manager');
        $other = $this->actor('community_manager');
        $ownEmission = Emission::create(['nom' => 'Mon émission', 'title' => 'Mon émission', 'slug' => 'mon-emission']);
        $otherEmission = Emission::create(['nom' => 'Émission d’un autre compte', 'title' => 'Émission d’un autre compte', 'slug' => 'emission-autre-compte']);
        DigitalPublication::create(['title' => 'Mon contenu', 'content' => 'Texte', 'platform' => 'site', 'status' => 'draft', 'created_by' => $user->id, 'emission_id' => $ownEmission->id]);
        DigitalPublication::create(['title' => 'Son contenu', 'content' => 'Texte', 'platform' => 'site', 'status' => 'draft', 'created_by' => $other->id, 'emission_id' => $otherEmission->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('filterEmissions', fn ($emissions) => $emissions->modelKeys() === [$ownEmission->id]);
    }

    public function test_le_resume_community_manager_est_calcule_par_plateforme_et_reste_personnel(): void
    {
        $user = $this->actor('community_manager');
        $other = $this->actor('community_manager');
        DigitalPublication::create(['title' => 'Planifiée en retard', 'content' => 'Texte', 'platform' => 'facebook', 'status' => 'scheduled', 'scheduled_at' => now()->subDay(), 'created_by' => $user->id]);
        DigitalPublication::create(['title' => 'Déjà publiée', 'content' => 'Texte', 'platform' => 'facebook', 'status' => 'published', 'published_at' => now(), 'created_by' => $user->id]);
        DigitalPublication::create(['title' => 'Contenu d’un autre compte', 'content' => 'Texte', 'platform' => 'instagram', 'status' => 'scheduled', 'scheduled_at' => now()->subDay(), 'created_by' => $other->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('sections', fn ($sections) => $sections[0]['title'] === 'Publications par plateforme'
                && count($sections[0]['items']) === 1
                && $sections[0]['items'][0]['title'] === 'Facebook'
                && $sections[0]['items'][0]['detail'] === '2 contenu(s) · 1 planifié(s) · 1 en retard'
                && $sections[0]['items'][0]['status'] === 'Retard');
    }

    public function test_un_compte_non_rattache_n_herite_pas_des_affectations_non_attribuees(): void
    {
        EditorialStory::factory()->create(['title' => 'Sujet non attribué', 'assigned_journaliste_id' => null]);
        $this->actingAs($this->actor('journaliste'))->get(route('dashboard'))->assertOk()->assertDontSee('Sujet non attribué')->assertSee('Votre compte n’est pas lié');
    }

    public function test_un_utilisateur_ne_peut_pas_s_attribuer_un_role_en_modifiant_son_compte(): void
    {
        $user = $this->actor('journaliste');
        $this->actingAs($user)->patchJson(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'role' => 'admin', 'roles' => ['admin']])->assertUnprocessable()->assertJsonValidationErrors(['role', 'roles']);
        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    public function test_l_administrateur_peut_attribuer_plusieurs_fonctions_sans_creer_un_autre_compte(): void
    {
        $admin = $this->actor('admin');
        $user = $this->actor('journaliste');
        $this->actingAs($admin)->patch(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'role' => 'journaliste', 'roles' => ['journaliste', 'community_manager']])->assertRedirect(route('users.index'));
        $this->assertTrue($user->fresh()->hasAllRoles(['journaliste', 'community_manager']));
        $this->actingAs($user->fresh())->get(route('dashboard', ['role' => 'community_manager']))->assertOk()->assertSee('Community Manager');
    }

    public function test_un_administrateur_ne_peut_pas_attribuer_le_role_superadministrateur(): void
    {
        $admin = $this->actor('admin');
        $user = $this->actor('journaliste');
        $this->actingAs($admin)->patchJson(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'role' => 'superadmin'])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_la_vue_de_reference_conserve_les_indicateurs_avant_les_trois_colonnes_et_les_filtres_replies(): void
    {
        $response = $this->actingAs($this->actor('community_manager'))->get(route('dashboard'))->assertOk();
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " ed-grid ")]/div[contains(concat(" ", normalize-space(@class), " "), " ed-column ")]')->length);
        $this->assertSame(0, $xpath->query('//details[@id="workspace-filters"][@open]')->length);
        $this->assertLessThan(strpos($response->getContent(), 'id="workspace-details"'), strpos($response->getContent(), 'aria-label="Indicateurs du rôle"'));
        $this->assertSame(0, $xpath->query('//*[@id="app-sidebar"]//a[contains(@href, "/conducteurs/")]')->length);
        $this->assertSame(1, $xpath->query('//button[@id="workspace-filters-toggle"][@aria-controls="workspace-filters"]')->length);
    }

    public function test_une_publication_planifiee_en_retard_reste_prioritaire_meme_creee_avant_la_periode(): void
    {
        $user = $this->actor('community_manager');
        $publication = DigitalPublication::create(['title' => 'Retard ancien à traiter', 'content' => 'Contenu', 'platform' => 'site', 'created_by' => $user->id, 'status' => 'scheduled', 'scheduled_at' => now()->subDays(2), 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        $publication->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->saveQuietly();
        $this->assertTrue($publication->fresh()->updated_at->isBefore(today()));
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewHas('priorities', fn ($items) => collect($items)->contains('title', 'Retard ancien à traiter'));
    }

    public function test_un_role_absent_des_affectations_ne_suffit_pas_dans_le_champ_legacy(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
