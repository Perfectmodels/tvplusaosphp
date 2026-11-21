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

    public function test_les_trois_formats_pdf_ont_la_charte_et_la_bonne_orientation(): void
    {
        [$conducteur] = $this->conducteurAvecSequence();
        $exporter = app(ConducteurPdfExporter::class);

        $technique = $exporter->export($conducteur->fresh(), ConducteurPdfExporter::TECHNIQUE);
        $script = $exporter->export($conducteur->fresh(), ConducteurPdfExporter::SCRIPT);
        $complet = $exporter->export($conducteur->fresh(), ConducteurPdfExporter::COMPLET);

        foreach ([$technique, $script, $complet] as $pdf) {
            $this->assertStringStartsWith('%PDF-1.4', $pdf);
            $this->assertStringContainsString('/FontFile2', $pdf);
            $this->assertStringContainsString('/ToUnicode', $pdf);
            $this->assertStringContainsString('/BaseFont /TVAOSPilatBook', $pdf);
            $this->assertStringContainsString('/BaseFont /TVAOSPilatBold', $pdf);
            $this->assertStringContainsString('/Type /Pages', $pdf);
            $this->assertStringContainsString('%%EOF', $pdf);
        }

        $this->assertStringContainsString('/MediaBox [0 0 841.89 595.28]', $technique);
        $this->assertStringNotContainsString('/MediaBox [0 0 595.28 841.89]', $technique);
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $script);
        $this->assertStringNotContainsString('/MediaBox [0 0 841.89 595.28]', $script);
        $this->assertStringContainsString('/MediaBox [0 0 841.89 595.28]', $complet);
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $complet);
    }

    public function test_les_routes_pdf_sont_accessibles_et_separees(): void
    {
        [$conducteur] = $this->conducteurAvecSequence();
        $this->actingAs($this->acteur());

        foreach ([
            'conducteurs.emission.export-pdf' => 'Conducteur_Regie',
            'conducteurs.emission.export-script-pdf' => 'Script_Presentateur',
            'conducteurs.emission.export-dossier-pdf' => 'Dossier_Conducteur',
        ] as $route => $prefix) {
            $response = $this->get(route($route, $conducteur))->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
            $this->assertStringContainsString($prefix, (string) $response->headers->get('content-disposition'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        }
    }

    public function test_les_exports_pdf_sont_interdits_sans_permission_view(): void
    {
        $plain = User::factory()->create();
        [$conducteur] = $this->conducteurAvecSequence();
        $this->actingAs($plain);

        foreach ([
            'conducteurs.emission.export-pdf',
            'conducteurs.emission.export-script-pdf',
            'conducteurs.emission.export-dossier-pdf',
        ] as $route) {
            $this->get(route($route, $conducteur))->assertForbidden();
        }
    }

    public function test_le_script_long_pagine_sans_contrainte_de_longueur(): void
    {
        [$conducteur, $sequence] = $this->conducteurAvecSequence();
        $sequence->updateQuietly([
            'script_text' => '<p>'.str_repeat('Bonsoir à toutes et à tous. Bienvenue à notre émission. ', 320).'</p>',
            'notes_presentateur' => '<p>Caméra 1. Marquer une pause.</p>',
        ]);

        $script = app(ConducteurPdfExporter::class)
            ->export($conducteur->fresh(), ConducteurPdfExporter::SCRIPT);

        $this->assertStringStartsWith('%PDF', $script);
        $this->assertMatchesRegularExpression('/\/Count ([2-9]|[1-9][0-9]+)/', $script);
        $this->assertStringContainsString('/ToUnicode', $script);
    }

    public function test_les_notes_privees_ne_sont_jamais_inclues_dans_les_exports(): void
    {
        [$conducteur, $sequence] = $this->conducteurAvecSequence();
        $exporter = app(ConducteurPdfExporter::class);
        $sans = $exporter->export($conducteur->fresh(), ConducteurPdfExporter::COMPLET);

        $sequence->forceFill(['notes_perso' => 'NOTE CONFIDENTIELLE NON IMPRIMABLE'])->saveQuietly();
        $avec = $exporter->export($conducteur->fresh(), ConducteurPdfExporter::COMPLET);

        $this->assertSame($sans, $avec);
    }

    public function test_le_pdf_historique_conserve_un_export_complet_par_defaut(): void
    {
        [$conducteur] = $this->conducteurAvecSequence();

        $pdf = app(ConducteurPdfExporter::class)->export($conducteur->fresh());

        $this->assertStringContainsString('/MediaBox [0 0 841.89 595.28]', $pdf);
        $this->assertStringContainsString('/MediaBox [0 0 595.28 841.89]', $pdf);
    }

    private function acteur(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    /** @return array{0: Conducteur,1: Sequence} */
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
            'target_duration_sec' => 1800,
        ]);

        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'emission_id' => $emission->id,
            'title' => 'Événement de présentation',
            'type' => TypeSequenceEnum::Sujet,
            'position' => 1,
            'duration_seconds' => 900,
            'script_text' => '<p>Bonsoir à toutes et à tous, bienvenue dans notre émission.</p>',
            'note_regie' => 'Lancement générique, puis caméra 1.',
            'notes_presentateur' => 'Regarder la caméra 1.',
        ]);

        return [$conducteur, $sequence];
    }
}
