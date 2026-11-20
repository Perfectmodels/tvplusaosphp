<?php

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmissionInteractionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function emission(string $name = 'Le direct'): Emission
    {
        return Emission::create([
            'nom' => $name,
            'title' => $name,
            'slug' => str()->slug($name).'-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function conducteur(Emission $emission, string $date = '2026-11-15'): Conducteur
    {
        return Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => $date,
            'type' => 'emission',
        ]);
    }

    public function test_chaque_edition_de_conducteur_recoit_un_qr_independant(): void
    {
        $emission = $this->emission();
        $premiereEdition = $this->conducteur($emission, '2026-11-15');
        $secondeEdition = $this->conducteur($emission, '2026-11-16');

        $this->assertSame(48, strlen($premiereEdition->public_interaction_token));
        $this->assertNotSame($premiereEdition->public_interaction_token, $secondeEdition->public_interaction_token);
    }

    public function test_le_public_peut_envoyer_un_message_visible_uniquement_dans_le_conducteur_concerne(): void
    {
        $emission = $this->emission();
        $conducteur = $this->conducteur($emission);
        $autreConducteur = $this->conducteur($emission, '2026-11-16');
        $message = '<script>alert(1)</script>';
        $nom = '<img src=x onerror=alert(1)>';
        $destinataire = '<svg onload=alert(1)>';

        $this->get(route('emission-interactions.show', $conducteur->public_interaction_token))
            ->assertOk()
            ->assertSee('Votre question ou message');

        $this->post(route('emission-interactions.store', $conducteur->public_interaction_token), [
            'nom' => $nom,
            'destinataire' => $destinataire,
            'message' => $message,
        ])->assertRedirect(route('emission-interactions.show', $conducteur->public_interaction_token))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('emission_interaction_messages', [
            'conducteur_id' => $conducteur->id,
            'nom' => $nom,
            'destinataire' => $destinataire,
            'message' => $message,
        ]);

        $this->actingAs($this->admin())
            ->get(route('conducteurs.emission.show', $conducteur))
            ->assertOk()
            ->assertSee('data-emission-interactions')
            ->assertSee($nom)
            ->assertDontSee($nom, false)
            ->assertSee($destinataire)
            ->assertDontSee($destinataire, false)
            ->assertSee($message)
            ->assertDontSee($message, false);

        $this->get(route('conducteurs.emission.interactions.index', $conducteur))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('messages.0.message', $message);

        $this->actingAs(User::factory()->create())
            ->get(route('conducteurs.emission.interactions.index', $conducteur))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('conducteurs.emission.show', $autreConducteur))
            ->assertOk()
            ->assertDontSee($message);
    }

    public function test_le_formulaire_refuse_un_message_vide_sans_creer_de_message(): void
    {
        $conducteur = $this->conducteur($this->emission());

        $this->from(route('emission-interactions.show', $conducteur->public_interaction_token))
            ->post(route('emission-interactions.store', $conducteur->public_interaction_token), [
                'message' => '',
            ])
            ->assertRedirect(route('emission-interactions.show', $conducteur->public_interaction_token))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('emission_interaction_messages', 0);
    }

    public function test_la_production_peut_generer_et_telecharger_le_qr_de_chaque_edition(): void
    {
        $conducteur = $this->conducteur($this->emission());
        DB::table('conducteurs')->where('id', $conducteur->id)->update(['public_interaction_token' => null]);
        $conducteur->refresh();

        $this->actingAs(User::factory()->create())
            ->post(route('conducteurs.emission.interactions.generate-qr', $conducteur))
            ->assertForbidden();

        $this->assertNull($conducteur->fresh()->public_interaction_token);

        $this->actingAs($this->admin())
            ->post(route('conducteurs.emission.interactions.generate-qr', $conducteur))
            ->assertRedirect(route('conducteurs.emission.show', $conducteur))
            ->assertSessionHas('download_public_qr', true)
            ->assertSessionHas('success');

        $token = $conducteur->fresh()->public_interaction_token;
        $this->assertNotNull($token);
        $this->assertSame(48, strlen($token));

        $this->actingAs($this->admin())
            ->get(route('conducteurs.emission.interactions.qr', $conducteur))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);

        $this->actingAs($this->admin())
            ->get(route('conducteurs.emission.interactions.qr.download', $conducteur))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="qr-le-direct-20261115.svg"')
            ->assertSee('<svg', false);

        $this->get(route('emission-interactions.show', $token))
            ->assertOk()
            ->assertSee('Votre question ou message');

        $this->actingAs($this->admin())
            ->get(route('conducteurs.emission.show', $conducteur))
            ->assertOk()
            ->assertSee('QR code public')
            ->assertSee('Télécharger le QR')
            ->assertSee(route('conducteurs.emission.interactions.qr', $conducteur), false)
            ->assertSee(route('conducteurs.emission.interactions.qr.download', $conducteur), false)
            ->assertSee('<details data-emission-interactions', false)
            ->assertDontSee('Afficher à l’antenne')
            ->assertDontSee('Ouvrir le formulaire');
    }

    public function test_le_tableau_de_bord_affiche_et_actualise_les_messages_du_public(): void
    {
        $admin = $this->admin();
        $admin->assignRole(Role::findOrCreate('chef_edition', 'web'));
        $conducteur = $this->conducteur($this->emission());
        $message = 'Question reçue pendant le direct';

        $conducteur->emissionInteractionMessages()->create([
            'nom' => 'Amira',
            'destinataire' => 'Présentatrice',
            'message' => $message,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', ['role' => 'chef_edition']))
            ->assertOk()
            ->assertSee('Messages du public')
            ->assertSee($message)
            ->assertSee(route('conducteurs.emission.show', $conducteur), false);

        $this->get(route('dashboard.emission-interactions'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('messages.0.message', $message);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.emission-interactions'))
            ->assertForbidden();
    }
}
