<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\StatutJournalisteEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JournalisteDailyIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function conducteur(?string $date = null): Conducteur
    {
        return Conducteur::create([
            'nom' => 'Conducteur enregistré', 'type' => 'redaction',
            'date_edition' => $date ?? today()->toDateString(),
        ]);
    }

    private function sujet(Conducteur $conducteur, Journaliste $journaliste, array $attributes = []): Sequence
    {
        return Sequence::create(array_replace([
            'title' => 'Sujet enregistré', 'conducteur_id' => $conducteur->id,
            'journaliste_responsable_id' => $journaliste->id, 'type' => TypeSequenceEnum::Reportage,
            'statut_terrain' => StatutEtapeWorkflowEnum::En_attente,
            'masque' => false,
        ], $attributes));
    }

    private function page(): TestResponse
    {
        return $this->actingAs($this->admin())->get(route('journalistes.index'))->assertOk();
    }

    public function test_compteurs_nuls_quand_aucun_sujet_n_est_enregistre(): void
    {
        Journaliste::factory()->create(['statut' => StatutJournalisteEnum::Actif]);

        $this->page()->assertViewHas('totalJ', 1)->assertViewHas('enMission', 0)->assertViewHas('enConge', 0)
            ->assertViewHas('sujetsEnCours', 0)->assertViewHas('sujetsEnProduction', 0)
            ->assertSee('0 en production')->assertDontSee('7 en production')
            ->assertDontSee('Retours prévus cette semaine');
    }

    public function test_sujets_du_jour_affectes_non_masques_et_non_diffuses_uniquement(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
        $journaliste = Journaliste::factory()->create();
        $conducteur = $this->conducteur();
        $this->sujet($conducteur, $journaliste);
        $this->sujet($conducteur, $journaliste, ['statut_terrain' => StatutEtapeWorkflowEnum::En_cours]);
        $this->sujet($conducteur, $journaliste, [
            'statut_terrain' => StatutEtapeWorkflowEnum::Termine,
            'statut_montage' => StatutEtapeWorkflowEnum::En_cours,
            'statut_habillage' => StatutEtapeWorkflowEnum::En_cours,
        ]);
        $this->sujet($conducteur, $journaliste, ['statut_terrain' => StatutEtapeWorkflowEnum::Termine]);
        $this->sujet($conducteur, $journaliste, ['statut_diffusion' => StatutEtapeWorkflowEnum::Termine]);
        $this->sujet($conducteur, $journaliste, ['masque' => true]);
        $this->sujet($conducteur, $journaliste, ['journaliste_responsable_id' => null]);
        $this->sujet($conducteur, $journaliste, ['type' => TypeSequenceEnum::Generique]);
        $this->sujet($this->conducteur('2026-11-12'), $journaliste, ['statut_terrain' => StatutEtapeWorkflowEnum::En_cours]);
        $this->sujet($this->conducteur('2026-11-14'), $journaliste, ['statut_terrain' => StatutEtapeWorkflowEnum::En_cours]);
        $this->sujet($conducteur, $journaliste, ['conducteur_id' => null]);

        $this->page()->assertViewHas('sujetsEnCours', 4)->assertViewHas('sujetsEnProduction', 2)
            ->assertSee('2 en production');
    }

    public function test_statistiques_rh_proviennent_des_statuts_enregistres(): void
    {
        Journaliste::factory()->create(['statut' => StatutJournalisteEnum::Actif]);
        Journaliste::factory()->count(2)->create(['statut' => StatutJournalisteEnum::Mission]);
        Journaliste::factory()->create(['statut' => StatutJournalisteEnum::Conge]);

        $this->page()->assertViewHas('totalJ', 4)->assertViewHas('enMission', 2)->assertViewHas('enConge', 1)
            ->assertSee('50% de l')->assertSee('25% de l')->assertDontSee('Retours prévus');
    }

    public function test_compteurs_suivent_le_workflow_et_le_changement_de_jour(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
        $journaliste = Journaliste::factory()->create();
        $sujet = $this->sujet($this->conducteur(), $journaliste);
        $this->page()->assertViewHas('sujetsEnCours', 1)->assertViewHas('sujetsEnProduction', 0);
        $sujet->update(['statut_terrain' => StatutEtapeWorkflowEnum::En_cours]);
        $this->page()->assertViewHas('sujetsEnCours', 1)->assertViewHas('sujetsEnProduction', 1);
        $sujet->update(['statut_diffusion' => StatutEtapeWorkflowEnum::Termine]);
        $this->page()->assertViewHas('sujetsEnCours', 0)->assertViewHas('sujetsEnProduction', 0);
        $sujet->update(['statut_diffusion' => StatutEtapeWorkflowEnum::En_attente]);
        $this->travelTo(now()->addDay());

        $this->page()->assertViewHas('sujetsEnCours', 0)->assertViewHas('sujetsEnProduction', 0);
    }

    public function test_indicateurs_globaux_independants_de_la_pagination_des_journalistes(): void
    {
        $journalistes = Journaliste::factory()->count(31)->create(['statut' => StatutJournalisteEnum::Actif]);
        $conducteur = $this->conducteur();
        $this->sujet($conducteur, $journalistes->first(), ['statut_terrain' => StatutEtapeWorkflowEnum::En_cours]);
        $this->sujet($conducteur, $journalistes->last());
        $this->actingAs($this->admin());

        $this->get(route('journalistes.index', ['page' => 1]))->assertViewHas('totalJ', 31)
            ->assertViewHas('sujetsEnCours', 2)->assertViewHas('sujetsEnProduction', 1);
        $this->get(route('journalistes.index', ['page' => 2]))->assertViewHas('totalJ', 31)
            ->assertViewHas('sujetsEnCours', 2)->assertViewHas('sujetsEnProduction', 1);
    }
}
