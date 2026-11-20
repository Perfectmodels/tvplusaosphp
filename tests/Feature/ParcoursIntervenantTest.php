<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PosteScanEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\SequenceIntervenantPivot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Workflow « Parcours intervenant » à validation MANUELLE :
 * Invitation → Accepté → En chemin → Arrivé → Maquillage → Plateau
 * + bouton « En retard » / « Absent (no-show) ».
 */
class ParcoursIntervenantTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        foreach (['admin', 'chef_edition'] as $r) {
            Role::findOrCreate($r);
        }
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    /** Crée conducteur → séquence → pivot (ce qui déclenche la création du parcours). */
    private function parcours(): ParcoursIntervenant
    {
        $cond = Conducteur::create([
            'nom' => 'JT — Test parcours',
            'date_edition' => '2026-10-02',
            'type' => 'redaction',
        ]);
        $seq = Sequence::create([
            'conducteur_id' => $cond->id,
            'title' => 'Sujet test',
            'type' => 'SUJET',
            'position' => 1,
        ]);
        $int = Intervenant::create([
            'nom' => 'Test',
            'prenom' => 'Invité',
            'is_active' => true,
        ]);

        $pivot = SequenceIntervenantPivot::create([
            'sequence_id' => $seq->id,
            'intervenant_id' => $int->id,
        ]);

        return $pivot->parcours()->firstOrFail();
    }

    // =========================================================
    //  ÉTAT INITIAL
    // =========================================================

    public function test_le_parcours_demarre_a_invitation(): void
    {
        $p = $this->parcours();

        $resp = $this->actingAs($this->user())
            ->getJson("/parcours-intervenants/{$p->id}/etat")
            ->assertOk();

        $resp->assertJsonPath('parcours.etape_courante', 'confirme');
        $resp->assertJsonPath('parcours.etapes.0.value', 'invitation_envoyee');
        $resp->assertJsonPath('parcours.etapes.0.fait', true);
    }

    // =========================================================
    //  VALIDATION PAS À PAS
    // =========================================================

    public function test_le_bouton_accepte_valide_la_confirmation(): void
    {
        $p = $this->parcours();

        $this->actingAs($this->user())->postJson(
            "/parcours-intervenants/{$p->id}/etape",
            ['etape' => 'confirme']
        )->assertOk()->assertJsonPath('parcours.etape_courante', 'en_chemin');

        $this->assertNotNull($p->fresh()->confirme_at);
    }

    public function test_le_workflow_complet_mene_jusqu_au_plateau(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        // Invitation → Confirmation → En chemin : validation manuelle.
        foreach (['confirme', 'en_chemin'] as $etape) {
            $this->actingAs($user)
                ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => $etape])
                ->assertOk();
        }

        // ⛔ SCAN OBLIGATOIRE : l'arrivée se valide au poste Accueil du Scanner QR.
        $p->intervenant->triggerScanWorkflow(PosteScanEnum::Accueil, $user->id, $p->sequence);

        // Les étapes studio suivent manuellement.
        foreach (['en_attente', 'en_maquillage', 'sur_plateau', 'diffuse_termine'] as $etape) {
            $this->actingAs($user)
                ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => $etape])
                ->assertOk();
        }

        $p->refresh();
        $this->assertNotNull($p->confirme_at);
        $this->assertNotNull($p->en_chemin_at);
        $this->assertNotNull($p->arrive_accueil_at);
        $this->assertNotNull($p->en_maquillage_at);
        $this->assertNotNull($p->sur_plateau_at);

        // Tout est fait → plus d'étape courante
        $this->actingAs($user)
            ->getJson("/parcours-intervenants/{$p->id}/etat")
            ->assertOk()
            ->assertJsonPath('parcours.etape_courante', null);
    }

    public function test_les_etapes_sont_prises_dans_l_ordre(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        // On valide « En chemin » AVANT « Confirmation » : chaque étape garde
        // SA propre date (aucune n'est écrasée). L'arrivée, elle, exige le scan.
        $this->actingAs($user)->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => 'en_chemin'])->assertOk();
        $this->actingAs($user)->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => 'confirme'])->assertOk();

        $p->refresh();
        // Chaque étape garde SA propre date (aucune n'est écrasée)
        $this->assertNotNull($p->confirme_at);
        $this->assertNotNull($p->en_chemin_at);
    }

    public function test_revalider_la_meme_etape_ne_rewind_pas(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        $user->id && $this->actingAs($user)->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => 'confirme'])->assertOk();
        $premiereDate = $p->fresh()->confirme_at;

        sleep(1);

        $this->actingAs($user)
            ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => 'confirme'])
            ->assertOk()
            ->assertJsonPath('deja_fait', true);

        $this->assertEquals($premiereDate, $p->fresh()->confirme_at);
    }

    // =========================================================
    //  CAS PARTICULIERS
    // =========================================================

    public function test_le_bouton_en_retard_marque_le_retard(): void
    {
        $p = $this->parcours();

        $this->actingAs($this->user())
            ->postJson("/parcours-intervenants/{$p->id}/retard", ['minutes' => 25])
            ->assertOk()
            ->assertJsonPath('parcours.en_retard', true)
            ->assertJsonPath('parcours.retard_minutes', 25);

        $this->assertSame(25, (int) $p->fresh()->retard_minutes);
    }

    public function test_le_bouton_absent_marque_le_no_show(): void
    {
        $p = $this->parcours();

        $this->actingAs($this->user())
            ->postJson("/parcours-intervenants/{$p->id}/no-show")
            ->assertOk()
            ->assertJsonPath('parcours.no_show', true);

        $this->assertTrue((bool) $p->fresh()->no_show);
    }

    // =========================================================
    //  PROGRAMMATION → PLACEMENT DANS LE CONDUCTEUR À L'ACCEPTATION
    // =========================================================

    public function test_programmer_laisse_l_invitation_a_confirmer(): void
    {
        $user = $this->user();
        [$int, $em] = $this->intervenantEtEmission();

        $resp = $this->actingAs($user)->postJson('/parcours-intervenants/programmer', [
            'intervenant_id' => $int->id,
            'emission_id' => $em->id,
            'heure_passage' => '2026-10-02T18:30',
        ]);

        $resp->assertOk()
            ->assertJsonPath('parcours.hors_conducteur', true)
            // L'invitation se confirme MANUELLEMENT : c'est la première étape.
            ->assertJsonPath('parcours.etape_courante', 'invitation_envoyee')
            ->assertJsonPath('parcours.etapes.0.fait', false);

        // Le pivot (conducteur) ne doit PAS encore exister
        $this->assertSame(0, SequenceIntervenantPivot::count());

        // L'invitation n'est pas encore horodatée : elle attend son bouton.
        $p = ParcoursIntervenant::where('intervenant_id', $int->id)->firstOrFail();
        $this->assertNull($p->invitation_envoyee_at);
    }

    public function test_l_acceptation_place_l_intervenant_dans_le_conducteur(): void
    {
        $user = $this->user();
        [$int, $em] = $this->intervenantEtEmission();

        $created = $this->actingAs($user)->postJson('/parcours-intervenants/programmer', [
            'intervenant_id' => $int->id,
            'emission_id' => $em->id,
            'heure_passage' => '2026-10-02T18:30',
        ])->assertOk();

        $parcId = (int) $created->json('parcours.id');

        $resp = $this->actingAs($user)
            ->postJson("/parcours-intervenants/{$parcId}/etape", ['etape' => 'confirme']);

        $resp->assertOk()
            ->assertJsonPath('place_dans_conducteur', true);

        // Le conducteur de l'émission pour cette date est rattaché
        $this->assertNotNull(ParcoursIntervenant::find($parcId)->conducteur_id);
    }

    public function test_la_programmation_doublon_est_refusee(): void
    {
        $user = $this->user();
        [$int, $em] = $this->intervenantEtEmission();

        $this->actingAs($user)->postJson('/parcours-intervenants/programmer', [
            'intervenant_id' => $int->id,
            'emission_id' => $em->id,
            'heure_passage' => '2026-10-02T18:30',
        ])->assertOk();

        $this->actingAs($user)->postJson('/parcours-intervenants/programmer', [
            'intervenant_id' => $int->id,
            'emission_id' => $em->id,
            'heure_passage' => '2026-10-02T18:30',
        ])->assertStatus(409);
    }

    public function test_la_programmation_exige_intervenant_emission_et_heure(): void
    {
        $this->actingAs($this->user())
            ->postJson('/parcours-intervenants/programmer', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intervenant_id', 'emission_id', 'heure_passage']);
    }

    public function test_l_heure_de_passage_est_memorisee(): void
    {
        $user = $this->user();
        [$int, $em] = $this->intervenantEtEmission();

        $created = $this->actingAs($user)->postJson('/parcours-intervenants/programmer', [
            'intervenant_id' => $int->id,
            'emission_id' => $em->id,
            'heure_passage' => '2026-10-02T18:45',
        ])->assertOk();

        $this->assertNotNull($created->json('parcours.heure_passage'));
        $this->assertSame('18:45', $created->json('parcours.heure_passage_hm'));
    }

    // =========================================================
    //  FIXTURES
    // =========================================================

    /** [Intervenant, Emission] — la programmation se fait au niveau de l'émission. */
    private function intervenantEtEmission(): array
    {
        $em = Emission::create([
            'nom' => 'JT Test programmation',
            'title' => 'JT Test programmation',
            'slug' => 'jt-test-programmation-'.uniqid(),
            'is_active' => true,
        ]);

        // Un conducteur existe pour cette émission à la date de passage :
        // c'est lui que l'acceptation doit rattacher.
        Conducteur::create([
            'emission_id' => $em->id,
            'nom' => 'Conducteur test programmation',
            'date_edition' => '2026-10-02',
            'type' => 'emission',
        ]);

        $int = Intervenant::create([
            'nom' => 'Programmé',
            'prenom' => 'Invité',
            'is_active' => true,
        ]);

        return [$int, $em];
    }

    public function test_une_etape_hors_workflow_est_refusee(): void
    {
        $p = $this->parcours();

        // `qr_envoye` existe dans l'enum mais n'est pas exposée à l'UI
        $this->actingAs($this->user())
            ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => 'etape_inconnue'])
            ->assertStatus(422);
    }

    public function test_le_retard_rejette_une_valeur_aberrante(): void
    {
        $p = $this->parcours();

        $this->actingAs($this->user())
            ->postJson("/parcours-intervenants/{$p->id}/retard", ['minutes' => 999999])
            ->assertStatus(422);
    }
}
