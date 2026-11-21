<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regressions for the existing Émissions, Veilles and Intervenants modules.
 * No new routes or business workflow have been introduced.
 */
class EditorialModuleUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    public function test_emissions_catalogue_has_grounded_kpis_and_accessible_filters(): void
    {
        Emission::create([
            'nom' => 'TV+A Édition du matin',
            'type' => TypeEmissionEnum::JT->value,
            'statut' => StatutEmissionEnum::Planifiee->value,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('emissions.index'))
            ->assertOk()
            ->assertSee('Programmes actifs')
            ->assertSee('Indicateurs du catalogue des émissions')
            ->assertSee('Rechercher un programme')
            ->assertSee('TV+A Édition du matin');
    }

    public function test_emission_details_have_real_conductor_links_instead_of_inert_buttons(): void
    {
        $emission = Emission::create([
            'nom' => 'TV+A Journal du soir',
            'type' => TypeEmissionEnum::JT->value,
            'statut' => StatutEmissionEnum::Planifiee->value,
            'is_active' => true,
        ]);
        $conducteur = Conducteur::create([
            'nom' => 'Journal 20 heures',
            'emission_id' => $emission->id,
            'type' => 'emission',
            'date_edition' => '2026-11-21',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('emissions.show', $emission))
            ->assertOk()->getContent();

        $this->assertStringContainsString(route('conducteurs.emission.create', [
            'emission_id' => $emission->id, 'date' => today()->toDateString(),
        ]), $html);
        $this->assertStringContainsString(route('conducteurs.emission.show', $conducteur), $html);
        $this->assertStringContainsString('Ouvrir le conducteur', $html);
        $this->assertStringContainsString('21/11/2026', $html);
    }

    public function test_intervenant_cards_remain_legible_and_link_to_actual_parcours(): void
    {
        $person = Intervenant::create([
            'nom' => 'Mballa',
            'prenom' => 'Jeanne',
        ]);
        $html = $this->actingAs($this->admin())
            ->get(route('intervenants.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('xl:grid-cols-3 2xl:grid-cols-4', $html);
        $this->assertStringContainsString('Rechercher un intervenant', $html);
        $this->assertStringContainsString('Gérer le parcours', $html);
        $this->assertStringContainsString(route('parcours-intervenants.index', ['intervenant_id' => $person->id]), $html);
    }

    public function test_veille_subject_page_has_accessible_tabs_and_modal(): void
    {
        $subject = VeilleSubject::create([
            'title' => 'Veille Gabon — culture',
            'status' => 'pending',
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('veille.sujets.show', $subject))
            ->assertOk()->getContent();

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tabpanel"', $html);
        $this->assertStringContainsString('aria-controls="tab-contenu"', $html);
        $this->assertStringContainsString('aria-labelledby="edit-veille-title"', $html);
        $this->assertStringContainsString('id="veille-edit-title"', $html);
    }

    public function test_veille_import_dialog_keeps_focus_and_error_feedback_accessible(): void
    {
        $source = File::get(resource_path('views/veille/sujets/index.blade.php'));
        $detail = File::get(resource_path('views/veille/sujets/show.blade.php'));
        $sources = File::get(resource_path('views/veille/sources/index.blade.php'));

        $this->assertStringContainsString('aria-labelledby="scrape-modal-title"', $source);
        $this->assertStringContainsString('closeScrapeModal()', $source);
        $this->assertStringContainsString("e.key === 'Escape'", $source);
        $this->assertStringContainsString("event.key === 'ArrowRight'", $detail);
        $this->assertStringContainsString('role="dialog"', $detail);
        $this->assertStringContainsString('id="veille-source-url"', $sources);
        $this->assertStringNotContainsString('method="POST" novalidate', $sources);
    }
}
