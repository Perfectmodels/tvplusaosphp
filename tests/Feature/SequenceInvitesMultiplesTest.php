<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Invités du sujet dans le conducteur du chef d'édition :
 * un ou plusieurs intervenants par sujet.
 *
 * Le pivot `sequence_intervenants` est many-to-many avec `position` — seul
 * le contrôleur était bloqué sur 1 invité (`sync` d'un seul id). Le front
 * envoie la liste en JSON ("[1,2]" / "[]") pour que « retirer tous les
 * invités » reste détectable côté serveur.
 */
class SequenceInvitesMultiplesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Role::findOrCreate('admin');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function conducteur(): Conducteur
    {
        $emission = Emission::create([
            'nom' => 'JT Invités Test',
            'title' => 'JT Invités Test',
            'slug' => 'jt-invites-test-'.uniqid(),
            'is_active' => true,
        ]);

        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);
    }

    private function intervenant(string $nom, string $prenom): Intervenant
    {
        return Intervenant::create([
            'nom' => $nom,
            'prenom' => $prenom,
            'fonction' => 'Expert',
            'is_active' => true,
        ]);
    }

    private function sequence(Conducteur $conducteur, string $titre = 'Sujet test'): Sequence
    {
        return Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => $titre,
            'type' => 'SUJET',
            'position' => 1,
        ]);
    }

    /**
     * Les erreurs portent des clés imbriquées (`intervenant_ids.0`,
     * `intervenant_ids.1` pour `distinct`) : on filtre par préfixe plutôt
     * que de parier sur l'index.
     */
    private function aDesErreursSurIntervenantIds(TestResponse $response): void
    {
        $response->assertSessionHasErrors();

        // TestResponse::session() est protégé : on lit le même store que lui.
        $session = app('session.store');
        if (! $session->isStarted()) {
            $session->start();
        }

        $messageBag = $session->get('errors')->getBag('default');
        $cles = array_keys($messageBag->getMessages());

        $this->assertNotEmpty(
            array_filter($cles, fn (string $cle) => str_starts_with($cle, 'intervenant_ids')),
            'Aucune erreur de validation sur intervenant_ids — clés reçues : '.implode(', ', $cles)
        );
    }

    // =========================================================
    //  1. CHEMIN NOMINAL — PLUSIEURS INVITÉS
    // =========================================================

    public function test_un_sujet_accepte_plusieurs_invites_a_la_creation(): void
    {
        $conducteur = $this->conducteur();
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');

        $this->actingAs($this->user())
            ->from('/conducteurs')
            ->post(route('conducteurs.sequences.store'), [
                'conducteur_id' => $conducteur->id,
                'title' => 'Table ronde',
                'type' => 'SUJET',
                'intervenant_ids' => [$awa->id, $paul->id],
            ])
            ->assertRedirect('/conducteurs');

        $sequence = Sequence::where('title', 'Table ronde')->firstOrFail();
        $invites = $sequence->intervenants;

        $this->assertEquals([$awa->id, $paul->id], $invites->pluck('id')->all());
        $this->assertSame([1, 2], $invites->map(fn ($i) => (int) $i->pivot->position)->all());

        // Textes du sujet pré-remplis depuis l'invité principal (n° 1)
        $this->assertSame('A. Dupont', $sequence->fresh()->nom_prenom_text);
        $this->assertSame('Expert', $sequence->fresh()->fonction_text);
    }

    // =========================================================
    //  2. REMPLACEMENT DE LA LISTE
    // =========================================================

    public function test_la_modification_remplace_la_liste_des_invites(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Débat');
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');
        $marc = $this->intervenant('Bernard', 'Marc');

        $sequence->intervenants()->sync([
            $awa->id => ['position' => 1],
            $paul->id => ['position' => 2],
        ]);

        // Envoi en JSON, exactement comme le front (conducteur-redaction.js)
        $this->actingAs($this->user())
            ->from('/conducteurs')
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'title' => $sequence->title,
                'type' => 'SUJET',
                'intervenant_ids' => json_encode([$marc->id]),
            ])
            ->assertRedirect('/conducteurs');

        $invites = $sequence->fresh()->intervenants;

        $this->assertEquals([$marc->id], $invites->pluck('id')->all());
        $this->assertSame([1], $invites->map(fn ($i) => (int) $i->pivot->position)->all());
        $this->assertSame(1, $sequence->intervenants()->count());
    }

    public function test_la_modification_avec_une_liste_vide_retire_tous_les_invites(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Sans invité');
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');

        $sequence->intervenants()->sync([
            $awa->id => ['position' => 1],
            $paul->id => ['position' => 2],
        ]);

        $this->actingAs($this->user())
            ->from('/conducteurs')
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'title' => $sequence->title,
                'type' => 'SUJET',
                'intervenant_ids' => '[]',
            ])
            ->assertRedirect('/conducteurs');

        $this->assertSame(0, $sequence->intervenants()->count());
        $this->assertSame('', $sequence->fresh()->intervenant_nom_affichage);
    }

    // =========================================================
    //  3. LIMITES DE VALIDATION
    // =========================================================

    public function test_un_invite_inexistant_renvoie_une_erreur_et_nest_pas_attache(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur);

        $response = $this->actingAs($this->user())
            ->from('/conducteurs')
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'intervenant_ids' => json_encode([999999]),
            ])
            ->assertSessionHasErrors();

        $this->aDesErreursSurIntervenantIds($response);
        $this->assertSame(0, $sequence->intervenants()->count());
    }

    public function test_un_invite_en_double_dans_la_liste_renvoie_une_erreur(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur);
        $awa = $this->intervenant('Dupont', 'Awa');

        $response = $this->actingAs($this->user())
            ->from('/conducteurs')
            ->patch(route('conducteurs.sequences.update', $sequence), [
                'intervenant_ids' => json_encode([$awa->id, $awa->id]),
            ])
            ->assertSessionHasErrors();

        $this->aDesErreursSurIntervenantIds($response);
        $this->assertSame(0, $sequence->intervenants()->count());
    }

    // =========================================================
    //  4. PAYLOAD DE LA MODALE (contrat avec le front)
    // =========================================================

    public function test_le_payload_renvoie_la_liste_ordonnee_et_l_invite_principal(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Débat');
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');

        $sequence->intervenants()->sync([
            $paul->id => ['position' => 2],
            $awa->id => ['position' => 1],
        ]);

        $json = $this->actingAs($this->user())
            ->get(route('conducteurs.sequences.payload', $sequence), [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('intervenant_ids', [$awa->id, $paul->id])
            ->assertJsonPath('intervenant_id', $awa->id)
            ->json();

        $this->assertStringContainsString('Dupont', $json['intervenants'][0]['label']);
        $this->assertStringContainsString('Martin', $json['intervenants'][1]['label']);
    }

    public function test_le_payload_d_un_sujet_sans_invite_est_vide(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Sans invité');

        $this->actingAs($this->user())
            ->get(route('conducteurs.sequences.payload', $sequence), [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonPath('intervenant_ids', [])
            ->assertJsonPath('intervenant_id', null);

        $this->assertSame('', $sequence->intervenant_nom_affichage);
    }

    // =========================================================
    //  5. RENDU — MODALE MULTI-INVITÉS + COLONNE DU TABLEAU
    // =========================================================

    public function test_la_page_du_chef_edition_affiche_la_selection_multiple_et_les_noms_joints(): void
    {
        $conducteur = $this->conducteur();
        $sequence = $this->sequence($conducteur, 'Débat');
        $awa = $this->intervenant('Dupont', 'Awa');
        $paul = $this->intervenant('Martin', 'Paul');

        $sequence->intervenants()->sync([
            $awa->id => ['position' => 1],
            $paul->id => ['position' => 2],
        ]);

        $html = $this->actingAs($this->user())
            ->get(route('conducteurs.chef_edition', $conducteur->id))
            ->assertOk()
            ->getContent();

        // Modale : sélecteur + état multi-invités (Alpine)
        $this->assertStringContainsString('Intervenants invités', $html);
        $this->assertStringContainsString('id="invite-ajout-select"', $html);
        $this->assertStringContainsString('formSeq.intervenant_ids', $html);
        $this->assertStringContainsString('ajouterInvite()', $html);

        // Colonne du tableau (sérialisée en @json → « · » échappé en ·)
        $this->assertStringContainsString(
            '"intervenant_nom":"Awa Dupont \u00b7 Paul Martin"',
            $html
        );
    }
}
