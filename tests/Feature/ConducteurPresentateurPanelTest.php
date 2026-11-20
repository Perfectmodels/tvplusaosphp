<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Panneau « Résumé · Séquence en cours » du conducteur présentateur :
 * le corps doit être DÉFILABLE et l'éditeur de notes TOUJOURS visible,
 * y compris en mode réduit (500 px de large).
 */
class ConducteurPresentateurPanelTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        foreach (['admin', 'presentateur'] as $r) {
            Role::findOrCreate($r);
        }
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function conducteur(): Conducteur
    {
        $em = Emission::create([
            'nom' => 'JT Panel Test', 'title' => 'JT Panel Test',
            'slug' => 'jt-panel-test-'.uniqid(), 'is_active' => true,
        ]);

        return Conducteur::create([
            'emission_id' => $em->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);
    }

    private function panneau(): string
    {
        return $this->actingAs($this->user())
            ->get('/conducteurs/presentateur/'.$this->conducteur()->id)
            ->assertOk()
            ->getContent();
    }

    // =========================================================
    //  DÉFILEMENT
    // =========================================================

    public function test_le_corps_du_panneau_est_defilable(): void
    {
        $html = $this->panneau();

        $this->assertStringContainsString('cp-panel-scroll', $html);
        $this->assertStringContainsString('overflow-y-auto', $html);
    }

    public function test_les_conteneurs_ne_coupent_plus_le_contenu(): void
    {
        $html = $this->panneau();

        // Ces overflow-hidden empêchaient l'éditeur d'être visible en mode réduit
        $this->assertStringNotContainsString('flex-1 flex flex-col overflow-hidden', $html);
    }

    // =========================================================
    //  ÉDITEUR DE NOTES TOUJOURS VISIBLE
    // =========================================================

    public function test_l_editeur_de_notes_garde_une_hauteur_exploitable(): void
    {
        $html = $this->panneau();

        $this->assertStringContainsString('tiptap-cp-mount', $html);
        $this->assertStringContainsString('min-h-[340px]', $html);
    }

    public function test_les_deux_onglets_de_notes_sont_presents(): void
    {
        $html = $this->panneau();

        $this->assertStringContainsString('Notes Présentateur', $html);
        $this->assertStringContainsString('Notes perso', $html);
    }

    public function test_les_actions_de_sequence_sont_disponibles_en_fiches_sans_defilement_horizontal(): void
    {
        $html = $this->panneau();

        $this->assertStringContainsString('id="cp-card-list"', $html);
        $this->assertStringContainsString('min-[2200px]:hidden', $html);
        $this->assertStringContainsString('min-h-11 min-w-11', $html);
        $this->assertStringContainsString('hidden min-[2200px]:block overflow-x-auto', $html);
    }

    // =========================================================
    //  PERSISTANCE PAR SÉQUENCE
    // =========================================================

    public function test_les_notes_sont_stockees_par_sequence(): void
    {
        $cond = $this->conducteur();
        $s1 = Sequence::create(['conducteur_id' => $cond->id, 'title' => 'Sequence A', 'type' => 'SUJET', 'position' => 1]);
        $s2 = Sequence::create(['conducteur_id' => $cond->id, 'title' => 'Sequence B', 'type' => 'SUJET', 'position' => 2]);

        $this->actingAs($this->user())->patchJson(
            "/conducteurs/sequences/{$s1->id}/notes-presentateur",
            ['notes_presentateur' => '<p>Note de la sequence A</p>']
        )->assertOk();

        $this->actingAs($this->user())->patchJson(
            "/conducteurs/sequences/{$s2->id}/notes-presentateur",
            ['notes_presentateur' => '<p>Note de la sequence B</p>']
        )->assertOk();

        // Chaque séquence garde SES notes
        $this->assertStringContainsString('Note de la sequence A', (string) $s1->fresh()->notes_presentateur);
        $this->assertStringContainsString('Note de la sequence B', (string) $s2->fresh()->notes_presentateur);
        $this->assertStringNotContainsString('Note de la sequence B', (string) $s1->fresh()->notes_presentateur);
    }

    public function test_modifier_une_sequence_n_ecrase_pas_les_autres(): void
    {
        $cond = $this->conducteur();
        $s1 = Sequence::create(['conducteur_id' => $cond->id, 'title' => 'Seq 1', 'type' => 'SUJET', 'position' => 1, 'notes_presentateur' => '<p>Alpha</p>']);
        $s2 = Sequence::create(['conducteur_id' => $cond->id, 'title' => 'Seq 2', 'type' => 'SUJET', 'position' => 2, 'notes_presentateur' => '<p>Bravo</p>']);
        $s3 = Sequence::create(['conducteur_id' => $cond->id, 'title' => 'Seq 3', 'type' => 'SUJET', 'position' => 3, 'notes_presentateur' => '<p>Charlie</p>']);

        // On ne modifie QUE la séquence 2
        $this->actingAs($this->user())->patchJson(
            "/conducteurs/sequences/{$s2->id}/notes-presentateur",
            ['notes_presentateur' => '<p>Bravo modifie</p>']
        )->assertOk();

        $this->assertStringContainsString('Alpha', (string) $s1->fresh()->notes_presentateur);
        $this->assertStringContainsString('Bravo modifie', (string) $s2->fresh()->notes_presentateur);
        $this->assertStringContainsString('Charlie', (string) $s3->fresh()->notes_presentateur);
    }

    public function test_sauvegarder_les_notes_perso_n_efface_pas_les_notes_presentateur(): void
    {
        $cond = $this->conducteur();
        $s = Sequence::create([
            'conducteur_id' => $cond->id,
            'title' => 'Seq notes',
            'type' => 'SUJET',
            'position' => 1,
            'notes_presentateur' => '<p>Notes publiques</p>',
        ]);

        // On envoie UNIQUEMENT notes_perso -> notes_presentateur doit être préservé
        $this->actingAs($this->user())->patchJson(
            "/conducteurs/sequences/{$s->id}/notes-presentateur",
            ['notes_perso' => 'Notes privées']
        )->assertOk();

        $this->assertStringContainsString('Notes publiques', (string) $s->fresh()->notes_presentateur);
        $this->assertSame('Notes privées', (string) $s->fresh()->notes_perso);
    }
}
