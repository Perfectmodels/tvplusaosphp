<?php

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurSecurityAndOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): self
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $this->actingAs($user);
    }

    private function conducteur(string $type): Conducteur
    {
        $emission = $type === 'emission'
            ? Emission::create([
                'nom' => 'Emission de test',
                'title' => 'Emission de test',
                'slug' => 'emission-de-test-'.uniqid(),
                'is_active' => true,
            ])
            : null;

        return Conducteur::create([
            'emission_id' => $emission?->id,
            'date_edition' => now()->toDateString(),
            'type' => $type,
        ]);
    }

    private function sequence(Conducteur $conducteur, string $title, int $position): Sequence
    {
        return Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => $title,
            'type' => 'SUJET',
            'position' => $position,
            'duration_seconds' => 120,
        ]);
    }

    public function test_connected_user_without_permissions_cannot_create_modify_or_delete_an_emission_sequence(): void
    {
        $conducteur = $this->conducteur('emission');
        $sequence = $this->sequence($conducteur, 'Original', 1);

        $this->actingAsRole('invité');

        $this->postJson('/conducteurs/sequences', [
            'conducteur_id' => $conducteur->id,
            'title' => 'Intrusion',
            'type' => 'SUJET',
        ])->assertForbidden();

        $this->patchJson("/conducteurs/sequences/{$sequence->id}", [
            'title' => 'Modification non autorisée',
        ])->assertForbidden();

        $this->deleteJson("/conducteurs/sequences/{$sequence->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('sequences', ['id' => $sequence->id, 'title' => 'Original']);
        $this->assertDatabaseMissing('sequences', ['title' => 'Intrusion']);
    }

    public function test_sequence_payload_requires_permission_to_view_the_sequence(): void
    {
        $sequence = $this->sequence($this->conducteur('emission'), 'Confidentiel', 1);

        $this->actingAsRole('invité')
            ->getJson("/conducteurs/sequences/{$sequence->id}/payload")
            ->assertForbidden();
    }

    public function test_redaction_page_requires_conducteur_view_permission(): void
    {
        $conducteur = $this->conducteur('redaction');

        $this->actingAsRole('invité')
            ->get("/conducteurs/redaction/{$conducteur->id}")
            ->assertForbidden();
    }

    public function test_chef_edition_live_state_returns_shared_antenna_snapshot(): void
    {
        $conducteur = $this->conducteur('emission');
        $this->sequence($conducteur, 'Premier sujet', 1);

        $this->actingAsRole('admin')
            ->getJson("/conducteurs/chef-edition/{$conducteur->id}/live-state")
            ->assertOk()
            ->assertJsonPath('sequences.0.title', 'Premier sujet');
    }

    public function test_deleting_one_emission_sequence_compacts_positions_once(): void
    {
        $conducteur = $this->conducteur('emission');
        $a = $this->sequence($conducteur, 'A', 1);
        $this->sequence($conducteur, 'B', 2);
        $this->sequence($conducteur, 'C', 3);

        $this->actingAsRole('admin')
            ->deleteJson("/conducteurs/presentateur/{$conducteur->id}/sequences/{$a->id}")
            ->assertOk();

        $this->assertSame([1, 2], $conducteur->sequences()->pluck('position')->all());
        $this->assertSame(['B', 'C'], $conducteur->sequences()->pluck('title')->all());
    }

    public function test_deleting_one_redaction_subject_compacts_positions_once(): void
    {
        $conducteur = $this->conducteur('redaction');
        $a = $this->sequence($conducteur, 'A', 1);
        $this->sequence($conducteur, 'B', 2);
        $this->sequence($conducteur, 'C', 3);

        $this->actingAsRole('admin')
            ->deleteJson("/conducteurs/redaction/{$conducteur->id}/sujets/{$a->id}")
            ->assertOk();

        $this->assertSame([1, 2], $conducteur->sequences()->pluck('position')->all());
        $this->assertSame(['B', 'C'], $conducteur->sequences()->pluck('title')->all());
    }

    public function test_reordering_redaction_with_missing_or_foreign_ids_is_rejected(): void
    {
        $conducteur = $this->conducteur('redaction');
        $other = $this->conducteur('redaction');
        $a = $this->sequence($conducteur, 'A', 1);
        $this->sequence($conducteur, 'B', 2);
        $foreign = $this->sequence($other, 'Autre', 1);

        $this->actingAsRole('admin')
            ->patchJson("/conducteurs/redaction/{$conducteur->id}/sujets/reorder", [
                'ordered_ids' => [$a->id],
            ])->assertStatus(422);

        $this->patchJson("/conducteurs/redaction/{$conducteur->id}/sujets/reorder", [
            'ordered_ids' => [$a->id, $foreign->id],
        ])->assertStatus(422);

        $this->assertSame([1, 2], $conducteur->sequences()->pluck('position')->all());
    }
}
