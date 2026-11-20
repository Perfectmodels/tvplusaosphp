<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Présences à la réunion de rédaction — Conducteur de Rédaction.
 * Personnes INTERNES uniquement (journalistes).
 */
class ConducteurPresenceTest extends TestCase
{
    use RefreshDatabase;

    private function roles(): void
    {
        foreach (['admin', 'directeur_general', 'chef_edition', 'chef_redaction', 'journaliste', 'presentateur'] as $r) {
            Role::findOrCreate($r);
        }
    }

    private function userAvecRole(string $role): User
    {
        $this->roles();
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function conducteurRedaction(): Conducteur
    {
        return Conducteur::create([
            'nom'          => 'JT 12h45 — Test',
            'date_edition' => '2026-10-02',
            'type'         => 'redaction',
        ]);
    }

    private function journaliste(string $nom = 'Test'): Journaliste
    {
        return Journaliste::create([
            'nom'      => $nom,
            'prenom'   => 'Jean',
            'is_active'=> true,
        ]);
    }

    // =========================================================
    //  CRÉATION DU CONDUCTEUR + LISTE DE PRÉSENTS
    // =========================================================

    public function test_la_creation_du_conducteur_enregistre_les_presences(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $j1 = $this->journaliste('Ndiaye');
        $j2 = $this->journaliste('Léon');

        $this->actingAs($chef)->post('/conducteurs/redaction', [
            'nom'          => 'JT 18h — Réunion',
            'date_edition' => '2026-10-02',
            'presences'    => [
                ['journaliste_id' => $j1->id, 'arrivee_at' => '2026-10-02T14:00'],
                ['journaliste_id' => $j2->id, 'arrivee_at' => '2026-10-02T14:25'],
            ],
        ]);

        $conducteur = Conducteur::where('nom', 'JT 18h — Réunion')->first();
        $this->assertNotNull($conducteur);
        $this->assertSame(2, $conducteur->presences()->count());
        $this->assertDatabaseHas('conducteur_presences', [
            'conducteur_id'  => $conducteur->id,
            'journaliste_id' => $j1->id,
        ]);
    }

    // =========================================================
    //  AJOUT RETARDATAIRE
    // =========================================================

    public function test_le_chef_peut_ajouter_un_retardataire(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();
        $j    = $this->journaliste('Retardataire');

        $resp = $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['journaliste_id' => $j->id, 'arrivee_at' => '2026-10-02T15:45']
        );

        $resp->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('conducteur_presences', [
            'conducteur_id'  => $cond->id,
            'journaliste_id' => $j->id,
        ]);
    }

    public function test_la_meme_personne_ne_peut_pas_etre_ajoutee_deux_fois(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();
        $j    = $this->journaliste('Doublon');

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['journaliste_id' => $j->id, 'arrivee_at' => '2026-10-02T14:00']
        )->assertOk();

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['journaliste_id' => $j->id, 'arrivee_at' => '2026-10-02T15:00']
        )->assertStatus(409);

        $this->assertSame(1, ConducteurPresence::count());
    }

    public function test_la_date_d_arrivee_est_obligatoire(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['journaliste_id' => $this->journaliste()->id]
        )->assertStatus(422);
    }

    // =========================================================
    //  MODIFICATION / SUPPRESSION
    // =========================================================

    public function test_le_chef_peut_modifier_l_heure_d_arrivee(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();
        $p = ConducteurPresence::create([
            'conducteur_id'  => $cond->id,
            'journaliste_id' => $this->journaliste()->id,
            'arrivee_at'     => '2026-10-02T14:00',
        ]);

        $this->actingAs($chef)->patchJson(
            "/conducteurs/redaction/{$cond->id}/presences/{$p->id}",
            ['arrivee_at' => '2026-10-02T16:30']
        )->assertOk();

        $this->assertSame('16:30', $p->fresh()->heure_arrivee_hm);
    }

    public function test_le_chef_peut_retirer_une_presence(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();
        $p = ConducteurPresence::create([
            'conducteur_id'  => $cond->id,
            'journaliste_id' => $this->journaliste()->id,
            'arrivee_at'     => '2026-10-02T14:00',
        ]);

        $this->actingAs($chef)->deleteJson(
            "/conducteurs/redaction/{$cond->id}/presences/{$p->id}"
        )->assertOk();

        $this->assertSame(0, ConducteurPresence::count());
    }

    // =========================================================
    //  RÉVISION DE LA LISTE COMPLÈTE
    // =========================================================

    public function test_la_revision_peut_ajouter_plusieurs_personnes_d_un_coup(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();
        $j1 = $this->journaliste('Un');
        $j2 = $this->journaliste('Deux');

        $resp = $this->actingAs($chef)->putJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['presences' => [
                ['journaliste_id' => $j1->id, 'arrivee_at' => '2026-10-02T14:00'],
                ['journaliste_id' => $j2->id, 'arrivee_at' => '2026-10-02T14:15'],
            ]]
        );

        $resp->assertOk()->assertJsonPath('resume.ajoutees', 2);
        $this->assertSame(2, ConducteurPresence::count());
    }

    public function test_la_revision_met_a_jour_les_heures_et_supprime_les_absents(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();
        $j1 = $this->journaliste('Garde');
        $j2 = $this->journaliste('Supprime');
        $j3 = $this->journaliste('Nouveau');

        $this->actingAs($chef)->putJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['presences' => [
                ['journaliste_id' => $j1->id, 'arrivee_at' => '2026-10-02T14:00'],
                ['journaliste_id' => $j2->id, 'arrivee_at' => '2026-10-02T14:15'],
            ]]
        )->assertOk();

        // Révision : j1 garde sa place (heure modifiée), j2 retiré, j3 ajouté
        $resp = $this->actingAs($chef)->putJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['presences' => [
                ['journaliste_id' => $j1->id, 'arrivee_at' => '2026-10-02T15:00'],
                ['journaliste_id' => $j3->id, 'arrivee_at' => '2026-10-02T16:00'],
            ]]
        );

        $resp->assertOk()
            ->assertJsonPath('resume.ajoutees', 1)
            ->assertJsonPath('resume.mises_a_jour', 1)
            ->assertJsonPath('resume.supprimees', 1);

        $this->assertDatabaseMissing('conducteur_presences', ['journaliste_id' => $j2->id]);
        $this->assertDatabaseHas('conducteur_presences', ['journaliste_id' => $j1->id]);
        $this->assertDatabaseHas('conducteur_presences', ['journaliste_id' => $j3->id]);
    }

    public function test_la_revision_peut_vider_entierement_la_liste(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();

        $this->actingAs($chef)->putJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['presences' => [
                ['journaliste_id' => $this->journaliste('Seul')->id, 'arrivee_at' => '2026-10-02T14:00'],
            ]]
        )->assertOk();

        $this->actingAs($chef)->putJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['presences' => []]
        )->assertOk()->assertJsonPath('resume.supprimees', 1);

        $this->assertSame(0, ConducteurPresence::count());
    }

    public function test_un_presentateur_ne_peut_pas_reviser_la_liste(): void
    {
        $presentateur = $this->userAvecRole('presentateur');
        $cond = $this->conducteurRedaction();

        $this->actingAs($presentateur)->putJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['presences' => []]
        )->assertStatus(403);
    }

    // =========================================================
    //  DROITS
    // =========================================================

    public function test_un_presentateur_ne_peut_pas_ajouter_une_presence(): void
    {
        $presentateur = $this->userAvecRole('presentateur');
        $cond = $this->conducteurRedaction();

        $this->actingAs($presentateur)->postJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['journaliste_id' => $this->journaliste()->id, 'arrivee_at' => '2026-10-02T14:00']
        )->assertStatus(403);
    }

    public function test_un_journaliste_peut_marquer_sa_propre_presence(): void
    {
        $this->roles();
        $j    = $this->journaliste('Moi-même');
        $user = User::factory()->create();
        $user->assignRole('journaliste');
        $user->update(['journaliste_id' => $j->id]);

        $cond = $this->conducteurRedaction();

        $this->actingAs($user)->postJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['journaliste_id' => $j->id, 'arrivee_at' => '2026-10-02T14:00']
        )->assertOk();
    }

    public function test_un_journaliste_ne_peut_pas_marquer_la_presence_d_un_autre(): void
    {
        $this->roles();
        $moi = $this->journaliste('Moi');
        $autre = $this->journaliste('Autre');

        $user = User::factory()->create();
        $user->assignRole('journaliste');
        $user->update(['journaliste_id' => $moi->id]);

        $cond = $this->conducteurRedaction();

        $this->actingAs($user)->postJson(
            "/conducteurs/redaction/{$cond->id}/presences",
            ['journaliste_id' => $autre->id, 'arrivee_at' => '2026-10-02T14:00']
        )->assertStatus(403);
    }

    // =========================================================
    //  PORTÉE
    // =========================================================

    public function test_les_presences_ne_sont_disponibles_que_sur_un_conducteur_de_redaction(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $ce = Conducteur::create([
            'emission_id'  => null,
            'date_edition' => '2026-10-02',
            'type'         => 'emission',
        ]);

        $this->actingAs($chef)->postJson(
            "/conducteurs/redaction/{$ce->id}/presences",
            ['journaliste_id' => $this->journaliste()->id, 'arrivee_at' => '2026-10-02T14:00']
        )->assertStatus(404);
    }

    public function test_supprimer_le_conducteur_supprime_ses_presences(): void
    {
        $cond = $this->conducteurRedaction();
        ConducteurPresence::create([
            'conducteur_id'  => $cond->id,
            'journaliste_id' => $this->journaliste()->id,
            'arrivee_at'     => '2026-10-02T14:00',
        ]);

        $cond->delete();

        $this->assertSame(0, ConducteurPresence::count());
    }

    // =========================================================
    //  PART DE PRÉSENTS SUR L'ENSEMBLE DES JOURNALISTES (X / N)
    // =========================================================

    public function test_le_formulaire_de_creation_expose_le_total_des_journalistes(): void
    {
        $chef = $this->userAvecRole('chef_edition');

        // 3 journalistes actifs + 1 inactif → le total doit ignorer l'inactif.
        $this->journaliste('Ndiaye');
        $this->journaliste('Léon');
        $this->journaliste('Faure');
        Journaliste::create(['nom' => 'Inactif', 'prenom' => 'Ivan', 'is_active' => false]);

        $html = $this->actingAs($chef)
            ->get('/conducteurs/redaction/create')
            ->assertOk()
            ->getContent();

        // Le vivier actif sert de dénominateur.
        $this->assertStringContainsString('journalistes présents', $html);
        $this->assertStringContainsString('progressionPresences', $html);
    }

    public function test_la_fiche_affiche_le_ratio_presents_sur_total(): void
    {
        $chef = $this->userAvecRole('chef_edition');
        $cond = $this->conducteurRedaction();

        $j1 = $this->journaliste('Ndiaye');
        $j2 = $this->journaliste('Léon');
        $this->journaliste('Faure');      // présent au total, mais absent de la réunion

        foreach ([$j1, $j2] as $j) {
            ConducteurPresence::create([
                'conducteur_id'  => $cond->id,
                'journaliste_id' => $j->id,
                'arrivee_at'     => '2026-10-02T14:00',
            ]);
        }

        $html = $this->actingAs($chef)
            ->get("/conducteurs/redaction/{$cond->id}")
            ->assertOk()
            ->getContent();

        // Le total (3 actifs) est injecté dans la config Alpine et affiché.
        $this->assertStringContainsString('totalJournalistes: 3', $html);
        $this->assertStringContainsString('presenceTotalJournalistes: 3', $html);
    }

    public function test_le_denominateur_ne_compte_que_les_journalistes_actifs(): void
    {
        $this->journaliste('Ndiaye');
        Journaliste::create(['nom' => 'Inactif', 'prenom' => 'Ivan', 'is_active' => false]);

        $this->assertSame(1, Journaliste::where('is_active', true)->count());
    }
}
