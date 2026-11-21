<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutConducteurEnum;
use App\Enums\TypeEmissionEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Sequence;
use App\Models\User;
use App\Services\ConducteurPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConducteurExportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_export_pdf_contient_le_script_et_les_donnees_du_conducteur(): void
    {
        $admin = $this->acteur();
        [$conducteur] = $this->conducteurAvecSequence();

        $response = $this->actingAs($admin)
            ->get(route('conducteurs.emission.export-pdf', $conducteur))
            ->assertOk();

        $body = app(ConducteurPdfExporter::class)->export($conducteur->fresh());
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertStringContainsString('Conducteur', $body);
        $this->assertStringContainsString('Script', $body);
        $this->assertStringContainsString('Evenement', $body);
        $this->assertStringContainsString('Statut', $body);
    }

    public function test_l_export_pdf_nest_pas_autorise_sans_permission_view(): void
    {
        $plain = User::factory()->create();
        [$conducteur] = $this->conducteurAvecSequence();

        $this->actingAs($plain)
            ->get(route('conducteurs.emission.export-pdf', $conducteur))
            ->assertForbidden();
    }

    private function acteur(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    /** @return array{0: Conducteur} */
    private function conducteurAvecSequence(): array
    {
        $emission = Emission::create([
            'nom' => 'Émission export',
            'type' => TypeEmissionEnum::JT->value,
        ]);

        $conducteur = Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => '2026-11-20',
            'type' => 'emission',
            'nom' => 'Conducteur export',
            'status' => StatutConducteurEnum::Brouillon,
            'total_duration' => 3600,
            'target_duration_sec' => 1800,
        ]);

        Sequence::create([
            'conducteur_id' => $conducteur->id,
            'emission_id' => $emission->id,
            'title' => 'Evenement de présentation',
            'type' => TypeSequenceEnum::Sujet,
            'position' => 1,
            'duration_seconds' => 900,
        ]);

        return [$conducteur];
    }
}
