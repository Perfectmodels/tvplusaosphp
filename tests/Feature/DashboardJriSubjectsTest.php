<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardJriSubjectsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole(['admin', Role::findOrCreate('chef_edition', 'web')]);

        return $user;
    }

    private function conducteur(array $attributes = []): Conducteur
    {
        $emission = Emission::create(['nom' => 'Journal enregistré', 'slug' => fake()->unique()->slug(), 'title' => 'Journal enregistré']);

        return Conducteur::create(array_replace([
            'nom' => 'Édition enregistrée', 'emission_id' => $emission->id,
            'type' => 'redaction', 'date_edition' => today()->toDateString(),
        ], $attributes));
    }

    private function sujet(Conducteur $conducteur, Journaliste $journaliste, array $attributes = []): Sequence
    {
        return Sequence::create(array_replace([
            'conducteur_id' => $conducteur->id, 'title' => 'Sujet saisi en rédaction',
            'type' => TypeSequenceEnum::Sujet, 'journaliste_responsable_id' => $journaliste->id,
            'position' => 1, 'statut_terrain' => StatutEtapeWorkflowEnum::En_attente, 'masque' => false,
        ], $attributes));
    }

    private function lignes(): array
    {
        return $this->actingAs($this->admin())->get(route('dashboard', ['role' => 'chef_edition']))->assertOk()->viewData('journalistesTerrain')->all();
    }

    public function test_aucun_sujet_jri_ni_nom_de_remplacement_sans_affectation_enregistree(): void
    {
        $journaliste = Journaliste::factory()->create();
        $conducteur = $this->conducteur();
        $this->sujet($conducteur, $journaliste, ['journaliste_responsable_id' => null]);
        $response = $this->actingAs($this->admin())->get(route('dashboard', ['role' => 'chef_edition']));

        $response->assertViewHas('journalistesTerrain', fn ($items) => $items->isEmpty())
            ->assertSee('Aucun sujet affecté à un journaliste / JRI aujourd’hui.');
    }

    public function test_nom_titre_emission_et_lien_proviennent_du_sujet_reel(): void
    {
        $journaliste = Journaliste::factory()->create(['prenom' => 'Awa', 'nom' => 'Ndiaye', 'civilite' => null]);
        $conducteur = $this->conducteur();
        $this->sujet($conducteur, $journaliste, ['title' => 'Enquête enregistrée sur les transports']);

        $this->assertSame([[
            'nom' => 'Awa Ndiaye', 'titre' => 'Enquête enregistrée sur les transports',
            'statut' => 'En attente', 'couleur' => 'text-on-surface-variant',
            'emission' => 'Journal enregistré', 'href' => route('conducteurs.redaction.show', $conducteur),
        ]], $this->lignes());
    }

    public static function etatsTerrain(): array
    {
        return [
            'attente' => [StatutEtapeWorkflowEnum::En_attente, 'En attente'],
            'en cours' => [StatutEtapeWorkflowEnum::En_cours, 'En cours'],
            'terminé' => [StatutEtapeWorkflowEnum::Termine, 'Terminé'],
        ];
    }

    #[DataProvider('etatsTerrain')]
    public function test_le_workflow_ne_fabrique_aucun_deplacement_ni_direct(StatutEtapeWorkflowEnum $etat, string $label): void
    {
        $conducteur = $this->conducteur();
        $journaliste = Journaliste::factory()->create();
        $this->sujet($conducteur, $journaliste, ['type' => TypeSequenceEnum::Duplex, 'statut_terrain' => $etat]);

        $lignes = $this->lignes();

        $this->assertSame($label, $lignes[0]['statut']);
        $this->assertNotContains($lignes[0]['statut'], ['En direct', 'En route', 'Tournage']);
    }

    public function test_sujets_hors_date_masques_et_elements_d_habillage_exclus(): void
    {
        $journaliste = Journaliste::factory()->create();
        $conducteur = $this->conducteur();
        $hier = $this->conducteur(['date_edition' => today()->subDay()->toDateString()]);
        $demain = $this->conducteur(['date_edition' => today()->addDay()->toDateString()]);
        $this->sujet($hier, $journaliste);
        $this->sujet($demain, $journaliste);
        $this->sujet($conducteur, $journaliste, ['masque' => true]);
        $this->sujet($conducteur, $journaliste, ['type' => TypeSequenceEnum::Generique]);

        $this->assertSame([], $this->lignes());
    }

    public function test_ancienne_affectation_journaliste_est_aussi_restituee(): void
    {
        $journaliste = Journaliste::factory()->create();
        $conducteur = $this->conducteur();
        $this->sujet($conducteur, $journaliste, ['journaliste_responsable_id' => null, 'journaliste_id' => $journaliste->id]);

        $this->assertSame($journaliste->nomComplet, $this->lignes()[0]['nom']);
    }

    public function test_modification_d_un_sujet_se_repercute_sur_le_tableau_de_bord(): void
    {
        $journaliste = Journaliste::factory()->create();
        $conducteur = $this->conducteur();
        $sujet = $this->sujet($conducteur, $journaliste);
        $this->assertSame('Sujet saisi en rédaction', $this->lignes()[0]['titre']);
        $sujet->update(['title' => 'Titre corrigé dans le conducteur', 'statut_terrain' => StatutEtapeWorkflowEnum::En_cours]);

        $lignes = $this->lignes();

        $this->assertSame('Titre corrigé dans le conducteur', $lignes[0]['titre']);
        $this->assertSame('En cours', $lignes[0]['statut']);
    }
}
