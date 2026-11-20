<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Enums\PosteScanEnum;
use App\Models\Conducteur;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\SequenceIntervenantPivot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Règle « SCAN OBLIGATOIRE » : au-delà de la confirmation d'invitation,
 * l'arrivée de l'invité est validée UNIQUEMENT par le scan de son QR à
 * l'accueil (poste Accueil, par un utilisateur autorisé). Les étapes studio
 * suivent ensuite manuellement.
 */
class ParcoursIntervenantScanObligatoireTest extends TestCase
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
            'nom' => 'JT — Test scan obligatoire',
            'date_edition' => now()->toDateString(),
            'type' => 'redaction',
        ]);
        $seq = Sequence::create([
            'conducteur_id' => $cond->id,
            'title' => 'Sujet test',
            'type' => 'SUJET',
            'position' => 1,
        ]);
        $int = Intervenant::create([
            'nom' => 'Ngoma',
            'prenom' => 'Sylvie',
            'is_active' => true,
        ]);

        $pivot = SequenceIntervenantPivot::create([
            'sequence_id' => $seq->id,
            'intervenant_id' => $int->id,
        ]);

        return $pivot->parcours()->firstOrFail();
    }

    // =========================================================
    //  ARRIVÉE : LE SCAN EST LA SEULE VOIE
    // =========================================================

    public function test_l_arrivee_ne_peut_pas_etre_validee_manuellement(): void
    {
        $p = $this->parcours();

        $this->actingAs($this->user())
            ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => 'arrive_accueil'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', static fn (string $m): bool => str_contains($m, 'Scan QR obligatoire'));

        $this->assertNull($p->fresh()->arrive_accueil_at);
    }

    public function test_le_scan_du_poste_accueil_valide_l_arrivee(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        $p->intervenant->triggerScanWorkflow(PosteScanEnum::Accueil, $user->id, $p->sequence);

        $this->assertNotNull($p->fresh()->arrive_accueil_at);
    }

    public function test_le_modele_refuse_l_arrivee_hors_scan(): void
    {
        // Filet de sécurité : quel que soit l'appelant, un média manuel ne
        // peut pas écrire l'arrivée — seul le scan (média système) le peut.
        $p = $this->parcours();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Scan QR obligatoire');

        $p->avancerEtape(EtapeParcoursIntervenantEnum::ArriveSurSite, null, 'bouton_web');
    }

    // =========================================================
    //  ÉTAPES STUDIO : BLOQUÉES AVANT LE SCAN, MANUELLES APRÈS
    // =========================================================

    public function test_les_etapes_studio_sont_bloquees_avant_le_scan(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        // Invitation → Confirmation → En chemin : validation manuelle normale.
        foreach (['confirme', 'en_chemin'] as $etape) {
            $this->actingAs($user)
                ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => $etape])
                ->assertOk();
        }

        // ⛔ Sans scan d'accueil, aucune étape studio n'est accessible.
        foreach (['en_attente', 'en_maquillage', 'sur_plateau', 'diffuse_termine'] as $etape) {
            $this->actingAs($user)
                ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => $etape])
                ->assertStatus(422);
        }

        $this->assertNull($p->fresh()->en_attente_at);
    }

    public function test_le_scan_debloque_les_etapes_studio_manuelles(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        foreach (['confirme', 'en_chemin'] as $etape) {
            $this->actingAs($user)
                ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => $etape])
                ->assertOk();
        }

        // 📷 Scan du QR au poste Accueil (utilisateur autorisé).
        $p->intervenant->triggerScanWorkflow(PosteScanEnum::Accueil, $user->id, $p->sequence);

        // Les étapes studio reprennent manuellement.
        foreach (['en_attente', 'en_maquillage', 'sur_plateau', 'diffuse_termine'] as $etape) {
            $this->actingAs($user)
                ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => $etape])
                ->assertOk();
        }

        $p->refresh();
        $this->assertNotNull($p->en_attente_at);
        $this->assertNotNull($p->sur_plateau_at);
        $this->assertNotNull($p->diffuse_termine_at);
    }

    // =========================================================
    //  INTERFACE
    // =========================================================

    public function test_l_ui_propose_le_scan_au_lieu_du_bouton_arrive(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        // Valider jusqu'à « En chemin » : l'étape courante devient « Arrivé ».
        foreach (['confirme', 'en_chemin'] as $etape) {
            $this->actingAs($user)
                ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => $etape])
                ->assertOk();
        }

        $html = $this->actingAs($user)
            ->get('/parcours-intervenants')
            ->assertOk()
            ->getContent();

        // Plus de bouton manuel « Arrivé » : le scan est la seule voie.
        $this->assertStringNotContainsString('data-etape="arrive_accueil"', $html);
        // L'appel au scan renvoie vers le Scanner QR (poste Accueil).
        $this->assertStringContainsString(route('scanner-qr.index'), $html);
        // La règle est expliquée dans le modal.
        $this->assertStringContainsString('Quand scanner le QR ?', $html);
    }
}
