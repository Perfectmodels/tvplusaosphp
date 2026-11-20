<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutConducteurEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Emission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RÉGRESSION Lot 5 — StatutEmissionEnum::selectOptions() retournait des clés
 * NUMÉRIQUES (array_map sur cases()) au lieu des valeurs de l'enum :
 * le formulaire envoyait « statut=2 » → règle Enum en échec →
 * « The selected statut is invalid. » à la création ET à la modification,
 * et le filtre statut du catalogue ne retournait jamais rien.
 */
class EmissionStatutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function emissionPayload(array $overrides = []): array
    {
        return array_merge([
            'nom' => 'JT 20h',
            'type' => TypeEmissionEnum::JT->value,
            'heure_debut' => '20:00',
            'heure_fin' => '20:30',
            'duree_minutes' => 30,
            'statut' => StatutEmissionEnum::Planifiee->value,
        ], $overrides);
    }

    public function test_les_options_de_statut_utilisent_les_valeurs_de_l_enum(): void
    {
        $this->assertSame(
            array_column(StatutEmissionEnum::cases(), 'value'),
            array_keys(StatutEmissionEnum::selectOptions()),
            'Les clés de StatutEmissionEnum::selectOptions() doivent être les valeurs de l\'enum (sinon « The selected statut is invalid. »).'
        );

        $this->assertSame(
            array_column(StatutConducteurEnum::cases(), 'value'),
            array_keys(StatutConducteurEnum::selectOptions()),
            'Les clés de StatutConducteurEnum::selectOptions() doivent être les valeurs de l\'enum.'
        );
    }

    public function test_l_admin_peut_creer_une_emission_avec_un_statut_valide(): void
    {
        $response = $this->actingAs($this->admin())
            ->post('/emissions', $this->emissionPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('emissions', [
            'nom' => 'JT 20h',
            'statut' => StatutEmissionEnum::Planifiee->value,
        ]);
    }

    public function test_l_admin_peut_changer_le_statut_en_modifiant_une_emission(): void
    {
        $emission = Emission::create($this->emissionPayload([
            'statut' => StatutEmissionEnum::Brouillon->value,
        ]));

        $response = $this->actingAs($this->admin())
            ->put("/emissions/{$emission->id}", $this->emissionPayload([
                'nom' => 'JT 20h',
                'statut' => StatutEmissionEnum::Diffusee->value,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('emissions', [
            'id' => $emission->id,
            'statut' => StatutEmissionEnum::Diffusee->value,
        ]);
    }

    public function test_le_filtre_statut_du_catalogue_filtre_correctement(): void
    {
        Emission::create($this->emissionPayload([
            'nom' => 'JT 20h',
            'statut' => StatutEmissionEnum::Planifiee->value,
        ]));
        Emission::create($this->emissionPayload([
            'nom' => 'Le Mag Culture',
            'statut' => StatutEmissionEnum::Brouillon->value,
        ]));

        $this->actingAs($this->admin())
            ->get('/emissions?statut='.urlencode(StatutEmissionEnum::Planifiee->value))
            ->assertOk()
            ->assertSee('JT 20h')
            ->assertDontSee('Le Mag Culture');
    }
}
