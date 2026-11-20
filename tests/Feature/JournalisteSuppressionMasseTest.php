<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suppression en masse des journalistes (sélection de la page courante).
 *
 * Régression couverte : le formulaire GET des filtres englobait le tableau,
 * ce qui rendait les formulaires de suppression unitaires imbriqués (donc
 * ignorés par le navigateur). Le formulaire de filtres doit être refermé
 * avant le tableau.
 */
class JournalisteSuppressionMasseTest extends TestCase
{
    use RefreshDatabase;

    private function userAvecRole(string $role): User
    {
        Role::findOrCreate($role);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function journaliste(string $nom): Journaliste
    {
        return Journaliste::create([
            'nom' => $nom,
            'prenom' => 'Prénom',
            'is_active' => true,
        ]);
    }

    // =========================================================
    //  1. CHEMIN NOMINAL
    // =========================================================

    public function test_la_selection_est_supprimee_en_un_seul_envoi(): void
    {
        $aSupprimerA = $this->journaliste('Dupont');
        $aSupprimerB = $this->journaliste('Martin');
        $aConserver = $this->journaliste('Durand');

        $this->actingAs($this->userAvecRole('admin'))
            ->post(route('journalistes.bulk-destroy'), [
                'ids' => [$aSupprimerA->id, $aSupprimerB->id],
            ])
            ->assertRedirect(route('journalistes.index'))
            ->assertSessionHas('status', '2 journalistes supprimés.');

        $this->assertDatabaseMissing('journalistes', ['id' => $aSupprimerA->id]);
        $this->assertDatabaseMissing('journalistes', ['id' => $aSupprimerB->id]);
        $this->assertDatabaseHas('journalistes', ['id' => $aConserver->id]);
    }

    public function test_un_seul_journaliste_selectionne_donne_un_message_au_singulier(): void
    {
        $seul = $this->journaliste('Nguyen');

        $this->actingAs($this->userAvecRole('admin'))
            ->from(route('journalistes.index'))
            ->post(route('journalistes.bulk-destroy'), ['ids' => [$seul->id]])
            ->assertRedirect(route('journalistes.index'))
            ->assertSessionHas('status', '1 journaliste supprimé.');

        $this->assertDatabaseMissing('journalistes', ['id' => $seul->id]);
    }

    // =========================================================
    //  2. LIMITES DE VALIDATION
    // =========================================================

    public function test_une_selection_vide_renvoie_une_erreur_de_validation(): void
    {
        $intouche = $this->journaliste('Intouché');

        $this->actingAs($this->userAvecRole('admin'))
            ->post(route('journalistes.bulk-destroy'), ['ids' => []])
            ->assertSessionHasErrors('ids');

        $this->assertDatabaseHas('journalistes', ['id' => $intouche->id]);
    }

    public function test_un_id_inexistant_renvoie_une_erreur_et_ne_supprime_rien(): void
    {
        $intouche = $this->journaliste('Intouché');

        $this->actingAs($this->userAvecRole('admin'))
            ->post(route('journalistes.bulk-destroy'), [
                'ids' => [$intouche->id, 999999],
            ])
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('journalistes', ['id' => $intouche->id]);
    }

    // =========================================================
    //  3. AUTORISATION (policy, jamais de string de permission)
    // =========================================================

    public function test_un_utilisateur_sans_droit_de_suppression_recoit_une_erreur_403(): void
    {
        $cible = $this->journaliste('Protégé');

        $this->actingAs($this->userAvecRole('journaliste'))
            ->post(route('journalistes.bulk-destroy'), ['ids' => [$cible->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('journalistes', ['id' => $cible->id]);
    }

    // =========================================================
    //  4. RENDU
    // =========================================================

    public function test_la_liste_affiche_les_cases_et_le_formulaire_de_masse(): void
    {
        $this->journaliste('Visible');

        $html = $this->actingAs($this->userAvecRole('admin'))
            ->get(route('journalistes.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="bulk-journalistes"', $html);
        $this->assertStringContainsString('action="'.route('bulk.destroy', 'journalistes').'"', $html);
        $this->assertStringContainsString('data-bulk-item', $html);
        $this->assertStringContainsString('form="bulk-journalistes"', $html);
        $this->assertStringContainsString('data-bulk-all', $html);
        $this->assertStringContainsString('Supprimer la sélection', $html);
        $this->assertStringContainsString('data-confirm-delete=', $html);
    }

    public function test_aucune_case_de_selection_sans_droit_de_suppression(): void
    {
        $this->journaliste('Visible');

        $html = $this->actingAs($this->userAvecRole('journaliste'))
            ->get(route('journalistes.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="bulk-journalistes"', $html);
        $this->assertStringNotContainsString('data-bulk-item', $html);
        $this->assertStringNotContainsString('data-bulk-all', $html);
    }

    public function test_le_formulaire_de_filtres_n_englobe_plus_le_tableau(): void
    {
        $this->journaliste('Visible');

        $html = $this->actingAs($this->userAvecRole('admin'))
            ->get(route('journalistes.index'))
            ->assertOk()
            ->getContent();

        $debutFiltres = strpos($html, '<form method="GET"');
        $debutTableau = strpos($html, '<table');

        $this->assertNotFalse($debutFiltres, 'Le formulaire de filtres GET est introuvable.');
        $this->assertNotFalse($debutTableau, 'Le tableau est introuvable.');
        $this->assertGreaterThan($debutFiltres, $debutTableau, 'Le tableau doit suivre le formulaire de filtres.');

        $entre = substr($html, $debutFiltres, $debutTableau - $debutFiltres);

        $this->assertStringContainsString(
            '</form>',
            $entre,
            'Le formulaire de filtres doit être refermé avant le tableau (form imbriqués = suppression unitaire cassée).'
        );
    }
}
