<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ConducteurChanged;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ConducteurChanged::class]);
    }

    private function user(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function conducteur(string $type = 'redaction'): Conducteur
    {
        if ($type === 'redaction') {
            return Conducteur::create([
                'nom' => 'Conducteur temps réel',
                'date_edition' => now()->toDateString(),
                'type' => $type,
            ]);
        }

        $emission = Emission::create([
            'nom' => 'JT temps réel',
            'title' => 'JT temps réel',
            'slug' => 'jt-temps-reel-'.uniqid(),
            'is_active' => true,
        ]);

        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => $type,
        ]);
    }

    public function test_event_is_private_and_contains_only_the_conducteur_id(): void
    {
        $event = new ConducteurChanged(42);

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn()[0]);
        $this->assertSame('private-conducteur.42', $event->broadcastOn()[0]->name);
        $this->assertSame(['conducteur_id' => 42], $event->broadcastWith());
    }

    public function test_private_channel_authorization_requires_conducteur_view_permission(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $conducteur = $this->conducteur();
        $authorized = $this->user('chef_edition');

        $this->actingAs($authorized)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-conducteur.'.$conducteur->id,
            ])
            ->assertOk();

        $unauthorized = $this->user('invité');
        $this->actingAs($unauthorized)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-conducteur.'.$conducteur->id,
            ])
            ->assertForbidden();
    }

    public function test_live_snapshot_does_not_expose_private_notes_to_journalists(): void
    {
        $conducteur = $this->conducteur('emission');
        Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Séquence de test',
            'type' => 'SUJET',
            'position' => 1,
            'notes_perso' => 'information confidentielle',
        ]);

        $response = $this->actingAs($this->user('journaliste'))
            ->getJson("/conducteurs/presentateur/{$conducteur->id}/live-state")
            ->assertOk()
            ->assertJsonPath('sequences.0.notes_perso', '');

        $this->assertStringNotContainsString('information confidentielle', $response->getContent());
    }

    public function test_chef_edition_live_snapshot_returns_current_emission_sequences(): void
    {
        $conducteur = $this->conducteur('emission');
        $this->actingAs($this->user('admin'))
            ->postJson('/conducteurs/sequences', [
                'conducteur_id' => $conducteur->id,
                'title' => 'Sujet ajouté',
                'type' => 'SUJET',
                'duration_minutes' => 2,
            ])
            ->assertCreated();

        $this->getJson("/conducteurs/chef-edition/{$conducteur->id}/live-state")
            ->assertOk()
            ->assertJsonPath('sequences.0.title', 'Sujet ajouté');
    }

    public function test_presentateur_live_snapshot_includes_a_new_emission_sequence(): void
    {
        $conducteur = $this->conducteur('emission');

        $this->actingAs($this->user('admin'))
            ->postJson("/conducteurs/presentateur/{$conducteur->id}/sequences", [
                'position' => 1,
                'heure_debut' => '12:30',
                'heure_fin' => '12:32',
                'type' => 'Sujet',
                'title' => 'Séquence ajoutée',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson("/conducteurs/presentateur/{$conducteur->id}/live-state")
            ->assertOk()
            ->assertJsonPath('sequences.0.title', 'Séquence ajoutée');
    }

    public function test_redaction_live_snapshot_returns_ordered_subjects_and_progression(): void
    {
        $conducteur = $this->conducteur();
        Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Sujet deux',
            'type' => 'SUJET',
            'position' => 2,
        ]);
        Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Sujet un',
            'type' => 'SUJET',
            'position' => 1,
        ]);

        $this->actingAs($this->user('chef_edition'))
            ->getJson("/conducteurs/redaction/{$conducteur->id}/live-state")
            ->assertOk()
            ->assertJsonPath('sujets.0.title', 'Sujet un')
            ->assertJsonPath('sujets.1.title', 'Sujet deux')
            ->assertJsonPath('progression', 0);
    }
}
