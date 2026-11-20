<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Enums\PosteScanEnum;
use App\Http\Controllers\ParcoursIntervenantController;
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
 * Parcours intervenant — CHAQUE étape se confirme en cliquant sur un bouton.
 *
 * Invitation → Confirmation → En chemin → Arrivé → Maquillage → Plateau
 */
class ParcoursIntervenantEtapesTest extends TestCase
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

    private function parcours(): ParcoursIntervenant
    {
        // date du jour : la liste « Parcours intervenant » est filtrée sur aujourd'hui.
        $cond = Conducteur::create([
            'nom' => 'JT — Test étapes',
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
            'nom' => 'Mballa',
            'prenom' => 'Jean',
            'is_active' => true,
        ]);

        $pivot = SequenceIntervenantPivot::create([
            'sequence_id' => $seq->id,
            'intervenant_id' => $int->id,
        ]);

        return $pivot->parcours()->firstOrFail();
    }

    // =========================================================
    //  BOUTONS DE CONFIRMATION
    // =========================================================

    public function test_chaque_etape_a_un_bouton_de_confirmation(): void
    {
        foreach (ParcoursIntervenantController::ETAPES_UI as $def) {
            $this->assertNotEmpty(
                $def['bouton'],
                "L'étape « {$def['label']} » n'a pas de bouton de confirmation."
            );
        }
    }

    public function test_les_huit_etapes_sont_presentes_dans_l_ordre(): void
    {
        $valeurs = array_column(ParcoursIntervenantController::ETAPES_UI, 'value');

        $this->assertSame([
            EtapeParcoursIntervenantEnum::Invitation->value,
            EtapeParcoursIntervenantEnum::Confirme->value,
            EtapeParcoursIntervenantEnum::EnChemin->value,
            EtapeParcoursIntervenantEnum::ArriveAccueil->value,
            EtapeParcoursIntervenantEnum::EnAttente->value,
            EtapeParcoursIntervenantEnum::Maquillage->value,
            EtapeParcoursIntervenantEnum::SurPlateau->value,
            EtapeParcoursIntervenantEnum::Diffuse->value,
        ], $valeurs);
    }

    // =========================================================
    //  AFFICHAGE
    // =========================================================

    public function test_la_liste_affiche_le_bouton_de_l_etape_courante(): void
    {
        $this->parcours();

        $html = $this->actingAs($this->user())
            ->get('/parcours-intervenants')
            ->assertOk()
            ->getContent();

        // Le parcours vient d'être créé : l'étape courante est la Confirmation,
        // son bouton « Accepté » doit être cliquable dans le tableau.
        $this->assertStringContainsString('data-etape="confirme"', $html);
        $this->assertStringNotContainsString('data-etape="en_chemin"', $html);
    }

    // =========================================================
    //  VALIDATION PAR LE BOUTON
    // =========================================================

    public function test_apres_programmation_le_premier_bouton_est_inviter(): void
    {
        $emission = Emission::create([
            'nom' => 'JT 18h',
            'title' => 'JT 18h',
            'slug' => 'jt-18h-'.uniqid(),
            'is_active' => true,
        ]);
        $intervenant = Intervenant::create(['nom' => 'Fouda', 'prenom' => 'Awa', 'is_active' => true]);

        // Programmation : l'invitation n'est PAS validée automatiquement.
        // ⚠️ La liste est filtrée sur la date du jour → on programme pour aujourd'hui.
        $this->actingAs($this->user())->postJson('/parcours-intervenants/programmer', [
            'intervenant_id' => $intervenant->id,
            'emission_id' => $emission->id,
            'heure_passage' => now()->format('Y-m-d').'T18:30',
        ])->assertOk()->assertJsonPath('parcours.etape_courante', 'invitation_envoyee');

        $html = $this->actingAs($this->user())
            ->get('/parcours-intervenants')
            ->assertOk()
            ->getContent();

        // Le bouton proposé est celui de l'étape Invitation (étape courante).
        $this->assertStringContainsString('data-etape="invitation_envoyee"', $html);
        // L'étape suivante ne propose pas encore son bouton.
        $this->assertStringNotContainsString('data-etape="confirme"', $html);
    }

    public function test_le_bouton_invitation_confirme_l_etape(): void
    {
        $p = $this->parcours();

        $this->actingAs($this->user())
            ->postJson("/parcours-intervenants/{$p->id}/etape", ['etape' => 'invitation_envoyee'])
            ->assertOk();
    }

    public function test_on_peut_parcourir_les_huit_etapes_en_cliquant(): void
    {
        $p = $this->parcours();
        $user = $this->user();

        // Invitation → Confirmation → En chemin : validation manuelle.
        foreach (['invitation_envoyee', 'confirme', 'en_chemin'] as $etape) {
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
        $this->assertNotNull($p->invitation_envoyee_at);
        $this->assertNotNull($p->confirme_at);
        $this->assertNotNull($p->en_chemin_at);
        $this->assertNotNull($p->arrive_accueil_at);
        $this->assertNotNull($p->en_maquillage_at);
        $this->assertNotNull($p->sur_plateau_at);
        $this->assertNotNull($p->en_attente_at);
        $this->assertNotNull($p->diffuse_termine_at);
    }
}
