<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleSequenceIntervenantEnum;
use App\Enums\StatutConducteurEnum;
use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\StatutPresentationSequenceEnum;
use App\Enums\TypeEmissionEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\Sondage;
use App\Models\User;
use App\Services\ConducteurAntenneService;
use App\Services\ParcoursIntervenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteursAuditPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function emission(bool $debat = false): Emission
    {
        return Emission::create([
            'nom' => 'Emission audit',
            'title' => 'Emission audit',
            'slug' => 'audit-'.uniqid(),
            'type' => $debat ? TypeEmissionEnum::Debat->value : null,
            'is_active' => true,
        ]);
    }

    private function conducteur(Emission $emission, string $date, string $type = 'emission'): Conducteur
    {
        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => $date,
            'type' => $type,
            'status' => StatutConducteurEnum::Brouillon,
        ]);
    }

    private function sequence(Conducteur $conducteur, int $pos, int $duration = 60, string $type = 'SUJET'): Sequence
    {
        return Sequence::create([
            'conducteur_id' => $conducteur->id,
            'emission_id' => $conducteur->emission_id,
            'title' => 'Séquence '.$pos,
            'type' => $type,
            'position' => $pos,
            'duration_seconds' => $duration,
        ]);
    }

    private function parcours(Emission $emission, string $date): ParcoursIntervenant
    {
        $intervenant = Intervenant::create([
            'nom' => 'Invité test',
            'prenom' => 'Alice',
            'is_active' => true,
        ]);

        return ParcoursIntervenant::create([
            'intervenant_id' => $intervenant->id,
            'emission_id' => $emission->id,
            'heure_passage_prevue' => $date.' 18:30:00',
        ]);
    }

    public function test_an_unprivileged_user_cannot_access_or_modify_guest_parcours(): void
    {
        $emission = $this->emission();
        $p = $this->parcours($emission, '2026-12-03');
        $this->actingAs($this->user('visiteur'));

        $this->getJson("/parcours-intervenants/{$p->id}/etat")->assertForbidden();
        $this->postJson("/parcours-intervenants/{$p->id}/no-show")->assertForbidden();
        $this->postJson("/parcours-intervenants/{$p->id}/retard", ['minutes' => 10])->assertForbidden();
        $this->postJson('/parcours-intervenants/programmer', [
            'intervenant_id' => $p->intervenant_id,
            'emission_id' => $emission->id,
            'heure_passage' => '2026-12-03T18:30',
        ])->assertForbidden();

        $this->assertFalse($p->fresh()->no_show);
        $this->assertSame(0, (int) $p->fresh()->retard_minutes);
    }

    public function test_guest_acceptance_never_falls_back_to_previous_broadcast_edition(): void
    {
        $emission = $this->emission();
        $old = $this->conducteur($emission, '2026-12-01');
        $p = $this->parcours($emission, '2026-12-03');
        $service = app(ParcoursIntervenantService::class);

        $this->assertFalse($service->placerDansConducteur($p));
        $this->assertNull($p->fresh()->conducteur_id);

        $correct = $this->conducteur($emission, '2026-12-03');
        $this->assertTrue($service->placerDansConducteur($p->fresh()));
        $this->assertSame($correct->id, $p->fresh()->conducteur_id);
        $this->assertNull($p->fresh()->sequence_intervenant_id);
        $this->assertNotSame($old->id, $p->fresh()->conducteur_id);
    }

    public function test_redaction_progression_counts_all_five_workflow_stages(): void
    {
        $conducteur = $this->conducteur($this->emission(), '2026-12-03', 'redaction');
        $seq = $this->sequence($conducteur, 1);

        $seq->update([
            'statut_terrain' => StatutEtapeWorkflowEnum::Termine,
            'statut_montage' => StatutEtapeWorkflowEnum::Termine,
        ]);

        $this->assertSame(40, $conducteur->fresh()->progression_percent);
    }

    public function test_server_reconciles_active_antenna_sequence_without_browser_commands(): void
    {
        $conducteur = $this->conducteur($this->emission(), '2026-12-03');
        [$first, $second] = [$this->sequence($conducteur, 1), $this->sequence($conducteur, 2)];

        $conducteur->forceFill([
            'antenne_started_at' => now()->subSeconds(65),
            'antenne_offset_sec' => 0,
            'antenne_ended_at' => null,
            'status' => StatutConducteurEnum::En_direct,
        ])->saveQuietly();

        app(ConducteurAntenneService::class)->reconcilier($conducteur->fresh());
        $this->assertSame($second->id, (int) $conducteur->fresh()->active_sequence_id);
        $this->assertSame(StatutPresentationSequenceEnum::Termine, $first->fresh()->statut_presentation);
        $this->assertSame(StatutPresentationSequenceEnum::EnCours, $second->fresh()->statut_presentation);

        $conducteur->forceFill(['antenne_started_at' => now()->subSeconds(130)])->saveQuietly();
        app(ConducteurAntenneService::class)->reconcilier($conducteur->fresh());

        $this->assertNotNull($conducteur->fresh()->antenne_ended_at);
        $this->assertSame(StatutConducteurEnum::Cloture, $conducteur->fresh()->status);
        $this->assertSame(120, (int) $conducteur->fresh()->antenne_offset_sec);
    }

    public function test_lifecycle_validates_archives_and_restores_without_deleting_sequences(): void
    {
        $conducteur = $this->conducteur($this->emission(), '2026-12-03');
        $seq = $this->sequence($conducteur, 1);
        $this->actingAs($this->user());

        $this->patch(route('conducteurs.lifecycle.soumettre', $conducteur))->assertRedirect();
        $this->assertSame(StatutConducteurEnum::A_valider, $conducteur->fresh()->status);

        $this->patch(route('conducteurs.lifecycle.valider', $conducteur))->assertRedirect();
        $this->assertSame(StatutConducteurEnum::Valide, $conducteur->fresh()->status);
        $this->assertNotNull($conducteur->fresh()->validated_at);

        $this->patch(route('conducteurs.lifecycle.cloturer', $conducteur))->assertRedirect();
        $this->patch(route('conducteurs.lifecycle.archiver', $conducteur))->assertRedirect();
        $this->assertSame(StatutConducteurEnum::Archive, $conducteur->fresh()->status);
        $this->assertNotNull($conducteur->fresh()->date_locked_at);
        $this->assertDatabaseHas('sequences', ['id' => $seq->id]);

        $this->patch(route('conducteurs.lifecycle.restaurer', $conducteur))->assertRedirect();
        $this->assertSame(StatutConducteurEnum::Cloture, $conducteur->fresh()->status);
        $this->assertNull($conducteur->fresh()->date_locked_at);
    }

    public function test_inserting_sequence_in_first_position_keeps_all_positions_unique(): void
    {
        $conducteur = $this->conducteur($this->emission(), '2026-12-03');
        $this->sequence($conducteur, 1);
        $this->sequence($conducteur, 2);

        $this->actingAs($this->user())
            ->postJson(route('conducteurs.sequences.store'), [
                'conducteur_id' => $conducteur->id,
                'title' => 'Ouverture',
                'type' => 'SUJET',
                'position' => 1,
            ])->assertCreated();

        $this->assertSame([1, 2, 3], $conducteur->sequences()->pluck('position')->all());
        $this->assertSame('Ouverture', $conducteur->sequences()->first()->title);
    }

    public function test_sondage_requires_an_edition_when_several_debates_exist(): void
    {
        $emission = $this->emission(true);
        $old = $this->conducteur($emission, '2026-12-01');
        $new = $this->conducteur($emission, '2026-12-03');
        $this->sequence($old, 1, 60, TypeSequenceEnum::Debat->value);
        $seq = $this->sequence($new, 1, 60, TypeSequenceEnum::Debat->value);

        $intervenant = Intervenant::create(['nom' => 'Débatteur', 'prenom' => 'Nina', 'is_active' => true]);
        $seq->intervenants()->attach($intervenant->id, [
            'role_dans_sequence' => RoleSequenceIntervenantEnum::Debatteur->value,
            'ordre_passage' => 1,
        ]);

        $this->actingAs($this->user())
            ->postJson(route('sondages.store', $emission), ['question' => 'Qui a convaincu ?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conducteur_id');

        $this->post(route('sondages.store', $emission), [
            'conducteur_id' => $new->id,
            'question' => 'Qui a convaincu ?',
        ])->assertRedirect();

        $this->assertSame($seq->id, (int) Sondage::firstOrFail()->sequence_id);
    }
    public function test_confirmed_guest_can_be_assigned_only_to_a_sequence_of_its_edition(): void
    {
        $emission = $this->emission();
        $correct = $this->conducteur($emission, '2026-12-03');
        $other = $this->conducteur($emission, '2026-12-04');
        $target = $this->sequence($correct, 1);
        $foreign = $this->sequence($other, 1);
        $p = $this->parcours($emission, '2026-12-03');
        $p->update([
            'conducteur_id' => $correct->id,
            'confirme_at' => now(),
        ]);

        $this->actingAs($this->user());

        $this->postJson(route('parcours-intervenants.sequence.assign', $p), [
            'sequence_id' => $foreign->id,
        ])->assertUnprocessable();

        $this->postJson(route('parcours-intervenants.sequence.assign', $p), [
            'sequence_id' => $target->id,
        ])->assertOk()->assertJsonPath('parcours.statut_affectation', 'affecte_sequence');

        $this->assertSame($target->id, (int) $p->fresh()->sequence_id);
        $this->assertNotNull($p->fresh()->sequence_intervenant_id);
    }

    public function test_unconfirmed_guest_cannot_be_assigned_to_a_sequence(): void
    {
        $emission = $this->emission();
        $conducteur = $this->conducteur($emission, '2026-12-03');
        $sequence = $this->sequence($conducteur, 1);
        $p = $this->parcours($emission, '2026-12-03');
        $p->update(['conducteur_id' => $conducteur->id]);

        $this->actingAs($this->user())
            ->postJson(route('parcours-intervenants.sequence.assign', $p), [
                'sequence_id' => $sequence->id,
            ])->assertUnprocessable();

        $this->assertNull($p->fresh()->sequence_intervenant_id);
    }

}
