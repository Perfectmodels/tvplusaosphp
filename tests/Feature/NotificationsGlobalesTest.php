<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\EmissionInteractionMessage;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationsGlobalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_letat_global_renvoie_le_conducteur_a_lantenne_et_sa_sequence(): void
    {
        $admin = $this->acteur('admin');
        [$conducteur, $sequence] = $this->conducteurEnDirect($admin);

        $this->actingAs($admin)
            ->getJson(route('notifications.etat'))
            ->assertOk()
            ->assertJsonPath('live.id', $conducteur->id)
            ->assertJsonPath('live.emission', 'MID’INFOS')
            ->assertJsonPath('live.running', true)
            ->assertJsonPath('live.sequence.id', $sequence->id)
            ->assertJsonPath('live.sequence.title', 'Entretien du jour')
            ->assertJsonPath('public.last_id', 0)
            ->assertJsonCount(0, 'public.messages');
    }

    public function test_les_nouveaux_messages_du_public_sont_renvoyes_de_facon_incrementale(): void
    {
        $admin = $this->acteur('admin');
        [$conducteur] = $this->conducteurEnDirect($admin);

        $message = EmissionInteractionMessage::create([
            'conducteur_id' => $conducteur->id,
            'nom' => 'Téléspectateur test',
            'message' => 'Bravo pour l’émission !',
        ]);

        $this->actingAs($admin)
            ->getJson(route('notifications.etat'))
            ->assertOk()
            ->assertJsonPath('public.last_id', $message->id)
            ->assertJsonCount(0, 'public.messages');

        $this->actingAs($admin)
            ->getJson(route('notifications.etat', ['after' => $message->id - 1]))
            ->assertOk()
            ->assertJsonPath('public.last_id', $message->id)
            ->assertJsonCount(1, 'public.messages')
            ->assertJsonPath('public.messages.0.message', 'Bravo pour l’émission !')
            ->assertJsonPath('public.messages.0.name', 'Téléspectateur test');

        $this->assertStringEndsWith('#tv-qr-public-card', (string) $this->getJson(route('notifications.etat', ['after' => $message->id - 1]))->json('public.messages.0.url'));
    }

    public function test_la_pile_globale_est_presente_sur_les_autres_sections(): void
    {
        $admin = $this->acteur('admin');

        $html = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('__tvNotificationsGlobal', $html);
        $this->assertStringContainsString('etatUrl', $html);
        $this->assertStringContainsString('notifications\\/etat', $html);
        $this->assertStringContainsString('Message en cours', $html);
        $this->assertStringContainsString('Message du public', $html);
    }

    public function test_un_invite_ne_peut_pas_interroger_letat_global(): void
    {
        $this->get(route('notifications.etat'))->assertRedirect(route('login'));
    }

    private function acteur(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    /** @return array{0: Conducteur, 1: Sequence} */
    private function conducteurEnDirect(User $owner): array
    {
        $emission = Emission::create([
            'nom' => 'MID’INFOS',
            'type' => TypeEmissionEnum::JT->value,
            'is_active' => true,
        ]);

        $conducteur = Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
            'owner_id' => $owner->id,
            'antenne_started_at' => now()->subMinutes(2),
        ]);

        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Entretien du jour',
            'type' => 'SUJET',
            'position' => 1,
            'duration_seconds' => 780,
        ]);

        $conducteur->timestamps = false;
        $conducteur->updateQuietly(['active_sequence_id' => $sequence->id]);

        return [$conducteur->fresh(), $sequence];
    }
}
