<?php

namespace Tests\Feature;

use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurFormLogicTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    public static function vues(): array
    {
        return ['émission' => ['emission'], 'présentateur' => ['presentateur']];
    }

    #[DataProvider('vues')]
    public function test_la_creation_d_une_edition_existante_ne_remplace_pas_son_presentateur(string $vue): void
    {
        $emission = Emission::create(['nom' => 'Programme enregistré']);
        $presentateur = Intervenant::factory()->create();
        $autre = Intervenant::factory()->create();
        $conducteur = Conducteur::create(['emission_id' => $emission->id, 'type' => 'emission', 'date_edition' => '2026-11-14', 'presentateur_id' => $presentateur->id, 'opening_text' => 'Texte déjà travaillé']);
        $this->actingAs($this->user())->post(route('conducteurs.'.$vue.'.store'), [
            'emission_id' => $emission->id, 'date_edition' => '2026-11-14', 'presentateur_id' => (string) $autre->id,
        ])->assertSessionHasNoErrors()->assertRedirect(route('conducteurs.'.$vue.'.show', $conducteur));
        $this->assertDatabaseCount('conducteurs', 1);
        $this->assertSame($presentateur->id, $conducteur->fresh()->presentateur_id);
        $this->assertSame('Texte déjà travaillé', $conducteur->fresh()->opening_text);
    }

    #[DataProvider('vues')]
    public function test_un_presentateur_peut_etre_renseigne_si_l_edition_n_en_a_pas(string $vue): void
    {
        $emission = Emission::create(['nom' => 'Programme enregistré']);
        $presentateur = Intervenant::factory()->create();
        $conducteur = Conducteur::create(['emission_id' => $emission->id, 'type' => 'emission', 'date_edition' => '2026-11-14']);
        $this->actingAs($this->user())->post(route('conducteurs.'.$vue.'.store'), [
            'emission_id' => $emission->id, 'date_edition' => '2026-11-14', 'presentateur_id' => (string) $presentateur->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('conducteurs', 1);
        $this->assertSame($presentateur->id, $conducteur->fresh()->presentateur_id);
    }

    public function test_la_redaction_retrouve_un_conducteur_du_meme_jour_sans_doublon(): void
    {
        $conducteur = Conducteur::create(['nom' => 'Édition préparée', 'type' => 'redaction', 'date_edition' => '2026-11-14']);
        $this->actingAs($this->user())->post(route('conducteurs.redaction.store'), ['nom' => 'Édition préparée', 'date_edition' => '2026-11-14'])
            ->assertSessionHasNoErrors()->assertRedirect(route('conducteurs.redaction.show', $conducteur));
        $this->assertDatabaseCount('conducteurs', 1);
    }

    public function test_la_creation_du_conducteur_ne_presente_pas_les_details_de_stockage_et_de_numerotation(): void
    {
        $this->actingAs($this->user())->get(route('conducteurs.emission.create'))->assertOk()
            ->assertSee('Choisissez l’émission, la date et le présentateur')
            ->assertDontSee('Identifiants générés automatiquement')
            ->assertDontSee('CE-AAAA-NNNN')->assertDontSee('1 row partagé');
    }

    public static function creationRoutes(): array
    {
        return ['rédaction' => ['redaction'], 'présentation' => ['presentateur']];
    }

    #[DataProvider('creationRoutes')]
    public function test_un_compte_en_lecture_ne_peut_pas_ouvrir_ou_soumettre_un_formulaire_de_creation(string $vue): void
    {
        $this->actingAs($this->user('lecture'))->get(route('conducteurs.'.$vue.'.create'))->assertForbidden();
        $this->post(route('conducteurs.'.$vue.'.store'), [])->assertForbidden();
        $this->assertDatabaseCount('conducteurs', 0);
    }
}
