<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleSequenceIntervenantEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\StatutSondageEnum;
use App\Enums\TypeEmissionEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Sequence;
use App\Models\Sondage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SondageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_peut_creer_un_sondage_a_partir_des_debatteurs(): void
    {
        $emission = $this->emissionDebat();

        $this->actingAs($this->acteur('admin'))
            ->get(route('sondages.create', $emission))
            ->assertOk()
            ->assertSee('Alice Ouattara')
            ->assertSee('Bob Koffi');

        $this->actingAs($this->acteur('admin'))
            ->post(route('sondages.store', $emission), ['question' => 'Qui a gagné le débat ?'])
            ->assertRedirect();

        $sondage = Sondage::firstOrFail();
        $this->assertSame('Qui a gagné le débat ?', $sondage->question);
        $this->assertSame(StatutSondageEnum::Brouillon, $sondage->statut);
        $this->assertSame(48, strlen($sondage->token));
        $this->assertSame(2, $sondage->candidats()->count());
    }

    public function test_un_journaliste_ne_peut_pas_creer_de_sondage(): void
    {
        $emission = $this->emissionDebat();

        $this->actingAs($this->acteur('journaliste'))
            ->get(route('sondages.create', $emission))
            ->assertForbidden();
    }

    public function test_le_chef_edition_de_lemission_peut_ouvrir_et_clore_le_sondage(): void
    {
        $chef = $this->acteur('chef_edition');
        $emission = $this->emissionDebat($chef);
        $sondage = $this->sondage($emission);

        $this->actingAs($chef)
            ->post(route('sondages.ouvrir', $sondage))
            ->assertRedirect();

        $this->assertSame(StatutSondageEnum::Ouvert, $sondage->fresh()->statut);
        $this->assertNotNull($sondage->fresh()->ouvert_at);

        $this->actingAs($chef)
            ->post(route('sondages.clore', $sondage))
            ->assertRedirect();

        $this->assertSame(StatutSondageEnum::Clos, $sondage->fresh()->statut);
        $this->assertNotNull($sondage->fresh()->clos_at);
    }

    public function test_un_autre_chef_edition_ne_peut_pas_gerer_le_sondage(): void
    {
        $emission = $this->emissionDebat($this->acteur('chef_edition'));
        $sondage = $this->sondage($emission);

        $this->actingAs($this->acteur('chef_edition'))
            ->get(route('sondages.show', $sondage))
            ->assertForbidden();
    }

    public function test_aucun_sondage_n_est_possible_sur_une_emission_non_debat(): void
    {
        $emission = Emission::create([
            'nom' => 'Journal test',
            'type' => TypeEmissionEnum::JT->value,
            'statut' => StatutEmissionEnum::Planifiee->value,
        ]);

        $this->actingAs($this->acteur('admin'))
            ->get(route('sondages.create', $emission))
            ->assertNotFound();
    }

    public function test_le_qr_public_est_servi_en_svg_sans_mise_en_cache(): void
    {
        $emission = $this->emissionDebat();
        $sondage = $this->sondage($emission);

        $response = $this->actingAs($this->acteur('admin'))
            ->get(route('sondages.qr', $sondage))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function acteur(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function emissionDebat(?User $chef = null): Emission
    {
        $emission = Emission::create([
            'nom' => 'Le débat test',
            'type' => TypeEmissionEnum::Debat->value,
            'statut' => StatutEmissionEnum::En_cours->value,
            'chef_edition_id' => $chef?->id,
        ]);

        $conducteur = Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);
        $sequence = Sequence::create([
            'emission_id' => $emission->id,
            'conducteur_id' => $conducteur->id,
            'title' => 'Débat',
            'type' => TypeSequenceEnum::Debat->value,
            'position' => 1,
        ]);

        foreach ([['Alice', 'Ouattara'], ['Bob', 'Koffi']] as $index => [$prenom, $nom]) {
            $intervenant = Intervenant::create([
                'prenom' => $prenom,
                'nom' => $nom,
                'is_active' => true,
            ]);
            $sequence->intervenants()->attach($intervenant->id, [
                'role_dans_sequence' => RoleSequenceIntervenantEnum::Debatteur->value,
                'ordre_passage' => $index + 1,
            ]);
        }

        return $emission;
    }

    private function sondage(Emission $emission): Sondage
    {
        $sondage = Sondage::create([
            'emission_id' => $emission->id,
            'question' => 'Qui a gagné le débat ?',
            'statut' => StatutSondageEnum::Brouillon,
        ]);

        foreach (Intervenant::limit(2)->get() as $index => $intervenant) {
            $sondage->candidats()->create([
                'intervenant_id' => $intervenant->id,
                'nom' => $intervenant->nomComplet,
                'ordre' => $index + 1,
            ]);
        }

        return $sondage;
    }
}
