<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\StatutEvenementEditorialEnum;
use App\Enums\StatutSujetEditorialEnum;
use App\Enums\StatutVerificationEditorialEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\EditorialEvent;
use App\Models\EditorialStory;
use App\Models\Emission;
use App\Models\Journaliste;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use App\Services\EditorialWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private EditorialWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = app(EditorialWorkflowService::class);
    }

    private function responsable(): User
    {
        $role = Role::findOrCreate('chef_edition', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function elementVeille(): VeilleSubject
    {
        $source = SourceVeille::create([
            'name' => 'Source test workflow',
            'type' => 'rss',
            'url' => 'https://93.184.215.14/feed-workflow',
            'is_active' => true,
        ]);

        return VeilleSubject::create([
            'source_id' => $source->id,
            'title' => 'Information à vérifier',
            'slug' => 'information-a-verifier-'.uniqid(),
            'status' => 'pending',
        ]);
    }

    private function conducteurRedaction(User $owner): Conducteur
    {
        $emission = Emission::create([
            'nom' => 'JT test',
            'title' => 'JT test',
            'slug' => 'jt-test-'.uniqid(),
            'type' => TypeEmissionEnum::JT,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        return Conducteur::create([
            'emission_id' => $emission->id,
            'nom' => 'Conducteur de test',
            'date_edition' => today()->toDateString(),
            'owner_id' => $owner->id,
            'type' => 'redaction',
            'status' => 'Brouillon',
        ]);
    }

    public function test_un_evenement_ne_peut_pas_etre_valide_avec_une_verification_en_attente(): void
    {
        $user = $this->responsable();
        $event = $this->workflow->ouvrirEvenementDepuisVeille($this->elementVeille(), $user);
        $verification = $this->workflow->ajouterVerification($event, [
            'type' => 'source_confirmation',
            'claim' => 'La déclaration a été publiée par une source identifiable.',
        ], $user);

        try {
            $this->workflow->validerEvenement($event, $user);
            $this->fail('Une vérification encore en attente ne doit pas permettre de valider l’événement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow', $exception->errors());
        }

        $this->workflow->terminerVerification(
            $verification,
            StatutVerificationEditorialEnum::Confirmee,
            $user,
            'Confirmation humaine consignée.'
        );

        $event = $this->workflow->validerEvenement($event->fresh(), $user);

        $this->assertSame(StatutEvenementEditorialEnum::Verifie, $event->status);
        $this->assertSame($user->id, $event->verified_by);
        $this->assertNotNull($event->verified_at);
    }

    public function test_une_verification_contredite_ou_non_concluante_empeche_de_declarer_l_evenement_verifie(): void
    {
        $user = $this->responsable();

        foreach ([StatutVerificationEditorialEnum::Contredite, StatutVerificationEditorialEnum::NonConcluante] as $conclusion) {
            $event = $this->workflow->ouvrirEvenementDepuisVeille($this->elementVeille(), $user);
            $verification = $this->workflow->ajouterVerification($event, [
                'type' => 'source_confirmation',
                'claim' => 'Cette information doit être confirmée.',
            ], $user);
            $this->workflow->terminerVerification($verification, $conclusion, $user);

            $this->actingAs($user)->post(route('evenements-editoriaux.validate', $event))
                ->assertSessionHasErrors('workflow');

            $this->assertSame(StatutEvenementEditorialEnum::EnVerification, $event->fresh()->status);
            $this->assertNull($event->fresh()->verified_by);
        }
    }

    public function test_la_promotion_de_veille_ouvre_un_evenement_sans_creer_une_sequence_directement(): void
    {
        $user = $this->responsable();
        $veille = $this->elementVeille();

        $response = $this->actingAs($user)
            ->post(route('veille.sujets.promote', $veille));

        $event = EditorialEvent::query()->firstOrFail();
        $response->assertRedirect(route('evenements-editoriaux.show', $event));
        $this->assertSame(StatutEvenementEditorialEnum::EnVerification, $event->status);
        $this->assertTrue($event->sourcesVeille()->where('veille_subjects.id', $veille->id)->exists());
        $this->assertSame(0, $event->sujets()->count());

        $this->actingAs($user)->post(route('veille.sujets.promote', $veille));

        $this->assertSame(1, EditorialEvent::query()->count());
    }

    public function test_une_validation_urgente_est_motivee_et_attribuee_a_un_responsable(): void
    {
        $user = $this->responsable();
        $event = $this->workflow->ouvrirEvenementDepuisVeille($this->elementVeille(), $user);
        $story = $this->workflow->creerSujetEditorial($event, [
            'title' => 'Sujet urgent de test',
            'angle' => 'Angle éditorial de test',
        ], $user);
        $journaliste = Journaliste::factory()->create(['is_active' => true]);

        $story = $this->workflow->assignerSujet($story, $journaliste, $user);
        $story = $this->workflow->demarrerProduction($story, $user);
        $story = $this->workflow->soumettrePourValidation($story, $user);

        try {
            $this->workflow->validerSujetEditorial($story, $user);
            $this->fail('Une validation sans vérification exige une dérogation motivée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow', $exception->errors());
        }

        $story = $this->workflow->validerSujetEditorial(
            $story->fresh(),
            $user,
            'Urgence éditoriale — validation de la source en cours.'
        );

        $this->assertSame(StatutSujetEditorialEnum::Valide, $story->status);
        $this->assertSame($user->id, $story->verification_exception_by);
        $this->assertNotNull($story->verification_exception_at);
        $this->assertSame(StatutEvenementEditorialEnum::EnVerification, $event->fresh()->status);
    }

    public function test_le_rejet_de_sujets_et_evenements_conserve_un_motif_et_un_auteur(): void
    {
        $user = $this->responsable();
        $event = $this->workflow->ouvrirEvenementDepuisVeille($this->elementVeille(), $user);
        $story = $this->workflow->creerSujetEditorial($event, [
            'title' => 'Sujet à examiner',
        ], $user);
        $journaliste = Journaliste::factory()->create(['is_active' => true]);

        $story = $this->workflow->assignerSujet($story, $journaliste, $user);
        $story = $this->workflow->demarrerProduction($story, $user);
        $story = $this->workflow->soumettrePourValidation($story, $user);
        $story = $this->workflow->rejeterSujetEditorial($story, 'Angle à reprendre avant diffusion.', $user);
        $event = $this->workflow->rejeterEvenement($event, 'Information insuffisamment corroborée.', $user);

        $this->assertSame(StatutSujetEditorialEnum::Rejete, $story->status);
        $this->assertSame('Angle à reprendre avant diffusion.', $story->rejection_reason);
        $this->assertSame($user->id, $story->rejected_by);
        $this->assertSame(StatutEvenementEditorialEnum::Rejete, $event->status);
        $this->assertSame('Information insuffisamment corroborée.', $event->rejection_reason);
        $this->assertSame($user->id, $event->rejected_by);
        $this->assertSame(StatutSujetEditorialEnum::Rejete, $event->sujets()->firstOrFail()->status);
    }

    public function test_seul_le_journaliste_assigne_peut_faire_avancer_la_production(): void
    {
        $manager = $this->responsable();
        $journalisteUser = User::factory()->create();
        $journalisteUser->assignRole(Role::findOrCreate('journaliste', 'web'));
        $journaliste = Journaliste::factory()->create([
            'user_id' => $journalisteUser->id,
            'is_active' => true,
        ]);
        $event = $this->workflow->ouvrirEvenementDepuisVeille($this->elementVeille(), $manager);
        $story = $this->workflow->creerSujetEditorial($event, ['title' => 'Reportage confié'], $manager);
        $story = $this->workflow->assignerSujet($story, $journaliste, $manager);

        $story = $this->workflow->demarrerProduction($story, $journalisteUser);
        $story = $this->workflow->soumettrePourValidation($story, $journalisteUser);

        $this->assertSame(StatutSujetEditorialEnum::AValider, $story->status);
        $this->assertSame($journalisteUser->id, $story->submitted_for_validation_by);
        $this->assertNotNull($story->production_started_at);
        $this->assertNotNull($story->submitted_for_validation_at);
    }

    public function test_un_sujet_valide_est_lie_au_conducteur_sans_creation_en_double(): void
    {
        $user = $this->responsable();
        $event = $this->workflow->ouvrirEvenementDepuisVeille($this->elementVeille(), $user);
        $verification = $this->workflow->ajouterVerification($event, [
            'type' => 'source_confirmation',
            'claim' => 'Le fait central est confirmé.',
        ], $user);
        $this->workflow->terminerVerification($verification, StatutVerificationEditorialEnum::Confirmee, $user);
        $event = $this->workflow->validerEvenement($event, $user);

        $story = EditorialStory::factory()->create([
            'editorial_event_id' => $event->id,
            'title' => 'Sujet à vérifier',
            'status' => StatutSujetEditorialEnum::AValider,
            'created_by' => $user->id,
        ]);
        $story = $this->workflow->validerSujetEditorial($story, $user);
        $conducteur = $this->conducteurRedaction($user);

        $firstSequence = $this->workflow->placerAuConducteur($story, $conducteur, $user);
        $secondSequence = $this->workflow->placerAuConducteur($story, $conducteur, $user);

        $this->assertSame($firstSequence->id, $secondSequence->id);
        $this->assertSame($story->id, $firstSequence->editorial_story_id);
        $this->assertSame('Sujet à vérifier', $firstSequence->title);
        $this->assertSame(StatutEtapeWorkflowEnum::En_cours, $firstSequence->statut_terrain);
        $this->assertSame(1, $conducteur->sequencesAvecMasques()->count());
    }

    public function test_un_journaliste_ne_peut_pas_valider_un_sujet(): void
    {
        $manager = $this->responsable();
        $event = $this->workflow->ouvrirEvenementDepuisVeille($this->elementVeille(), $manager);
        $story = EditorialStory::factory()->create([
            'editorial_event_id' => $event->id,
            'status' => StatutSujetEditorialEnum::AValider,
        ]);
        $journaliste = User::factory()->create();
        $journaliste->assignRole(Role::findOrCreate('journaliste', 'web'));

        $this->expectException(AuthorizationException::class);
        $this->workflow->validerSujetEditorial($story, $journaliste);
    }
}
