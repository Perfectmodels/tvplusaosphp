<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutPresentationSequenceEnum;
use App\Models\Conducteur;
use App\Models\Emission;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CHRONO ANTENNE — UN SEUL COMPTE À REBOURS POUR TOUTE L'ÉMISSION.
 *
 * Le présentateur démarre un unique chrono ; il décompte ensuite les séquences
 * les unes après les autres, dans l'ordre, en s'appuyant sur la durée de chacune.
 * La page doit afficher les temps écoulés « à travers chaque séquence, en ordre ».
 */
class ConducteurPresentateurAntenneTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        foreach (['admin', 'presentateur', 'chef_plateau', 'chef_edition', 'journaliste'] as $r) {
            Role::findOrCreate($r);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<int, int>  $durees  durée (s) de chaque séquence, dans l'ordre
     * @return array{0: Conducteur, 1: array<int, Sequence>}
     */
    private function conducteurAvecSequences(array $durees = [120, 90, 60]): array
    {
        $emission = Emission::create([
            'nom' => 'JT Antenne Test',
            'title' => 'JT Antenne Test',
            'slug' => 'jt-antenne-test-'.uniqid(),
            'is_active' => true,
        ]);

        $conducteur = Conducteur::create([
            'emission_id' => $emission->id,
            'date_edition' => now()->toDateString(),
            'type' => 'emission',
        ]);

        $sequences = [];
        foreach ($durees as $index => $duree) {
            $sequences[] = Sequence::create([
                'conducteur_id' => $conducteur->id,
                'title' => 'Séquence '.($index + 1),
                'type' => 'SUJET',
                'position' => $index + 1,
                'duration_seconds' => $duree,
            ]);
        }

        return [$conducteur, $sequences];
    }

    private function url(Conducteur $conducteur, string $action): string
    {
        return "/conducteurs/presentateur/{$conducteur->id}/antenne/{$action}";
    }

    // =========================================================
    //  DURÉE TOTALE
    // =========================================================

    public function test_le_total_antenne_est_la_somme_des_durees_des_sequences(): void
    {
        [$conducteur] = $this->conducteurAvecSequences([120, 90, 60]);

        $this->assertSame(270, (int) $conducteur->fresh()->antenne_total_sec);
    }

    public function test_une_sequence_sans_duree_compte_pour_soixante_secondes(): void
    {
        [$conducteur] = $this->conducteurAvecSequences([0, 120]);

        // 0 s → 60 s (défaut) + 120 s = 180 s
        $this->assertSame(180, (int) $conducteur->fresh()->antenne_total_sec);
    }

    // =========================================================
    //  DÉMARRAGE
    // =========================================================

    public function test_demarrer_le_chrono_active_la_premiere_sequence(): void
    {
        [$conducteur, $sequences] = $this->conducteurAvecSequences([120, 90, 60]);

        $this->actingAs($this->user('presentateur'))
            ->postJson($this->url($conducteur, 'start'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('antenne.total_sec', 270)
            ->assertJsonPath('antenne.en_marche', true);

        $conducteur->refresh();
        $this->assertNotNull($conducteur->antenne_started_at);
        $this->assertNull($conducteur->antenne_ended_at);
        $this->assertSame((int) $sequences[0]->id, (int) $conducteur->active_sequence_id);

        $this->assertSame(StatutPresentationSequenceEnum::EnCours, $sequences[0]->fresh()->statut_presentation);
        $this->assertSame(StatutPresentationSequenceEnum::Avenir, $sequences[1]->fresh()->statut_presentation);
        $this->assertSame(StatutPresentationSequenceEnum::Avenir, $sequences[2]->fresh()->statut_presentation);
    }

    // =========================================================
    //  BASCULE AUTOMATIQUE SÉQUENCE PAR SÉQUENCE
    // =========================================================

    public function test_basculer_marque_la_precedente_terminee_et_la_nouvelle_en_cours(): void
    {
        [$conducteur, $sequences] = $this->conducteurAvecSequences([120, 90, 60]);
        $user = $this->user('presentateur');

        $this->actingAs($user)->postJson($this->url($conducteur, 'start'))->assertOk();

        // Le client signale le passage à la 2ᵉ séquence (dépassement de la 1ʳᵉ).
        $this->actingAs($user)
            ->postJson($this->url($conducteur, 'sequence'), ['sequence_id' => $sequences[1]->id])
            ->assertOk()
            ->assertJsonPath('active_id', (int) $sequences[1]->id);

        $premiere = $sequences[0]->fresh();
        $this->assertSame(StatutPresentationSequenceEnum::Termine, $premiere->statut_presentation);
        $this->assertNotNull($premiere->presentation_ended_at);

        $seconde = $sequences[1]->fresh();
        $this->assertSame(StatutPresentationSequenceEnum::EnCours, $seconde->statut_presentation);
        $this->assertNotNull($seconde->presentation_started_at);
        $this->assertNull($seconde->presentation_ended_at);

        $this->assertSame(StatutPresentationSequenceEnum::Avenir, $sequences[2]->fresh()->statut_presentation);
        $this->assertSame((int) $sequences[1]->id, (int) $conducteur->fresh()->active_sequence_id);
    }

    // =========================================================
    //  PAUSE
    // =========================================================

    public function test_la_pause_gele_le_temps_ecoule(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        [$conducteur] = $this->conducteurAvecSequences([120, 90, 60]);
        $user = $this->user('presentateur');

        $this->actingAs($user)->postJson($this->url($conducteur, 'start'))->assertOk();

        // 2 minutes plus tard, on met en pause.
        Carbon::setTestNow('2026-10-13 12:02:00');
        $this->actingAs($user)
            ->postJson($this->url($conducteur, 'pause'))
            ->assertOk()
            ->assertJsonPath('antenne.en_marche', false);

        $conducteur->refresh();
        $this->assertNull($conducteur->antenne_started_at);
        $this->assertSame(120, (int) $conducteur->antenne_offset_sec);
        $this->assertSame(120, (int) $conducteur->antenne_ecoule_sec);

        // 10 minutes en pause : le temps écoulé ne bouge pas.
        Carbon::setTestNow('2026-10-13 12:12:00');
        $this->assertSame(120, (int) $conducteur->fresh()->antenne_ecoule_sec);

        Carbon::setTestNow();
    }

    public function test_reprendre_apres_une_pause_ne_remet_pas_a_zero(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        [$conducteur] = $this->conducteurAvecSequences([120, 90, 60]);
        $user = $this->user('presentateur');

        $this->actingAs($user)->postJson($this->url($conducteur, 'start'))->assertOk();
        Carbon::setTestNow('2026-10-13 12:02:00');
        $this->actingAs($user)->postJson($this->url($conducteur, 'pause'))->assertOk();

        Carbon::setTestNow('2026-10-13 12:05:00');
        $this->actingAs($user)
            ->postJson($this->url($conducteur, 'start'))
            ->assertOk()
            ->assertJsonPath('antenne.offset_sec', 120);

        $conducteur->refresh();
        $this->assertNotNull($conducteur->antenne_started_at);
        // 120 s avant la pause + 0 s depuis la reprise
        $this->assertSame(120, (int) $conducteur->antenne_ecoule_sec);

        Carbon::setTestNow();
    }

    // =========================================================
    //  RÉINITIALISATION
    // =========================================================

    public function test_reinitialiser_remet_tout_a_zero(): void
    {
        [$conducteur, $sequences] = $this->conducteurAvecSequences([120, 90, 60]);
        $user = $this->user('presentateur');

        $this->actingAs($user)->postJson($this->url($conducteur, 'start'))->assertOk();
        $this->actingAs($user)
            ->postJson($this->url($conducteur, 'sequence'), ['sequence_id' => $sequences[1]->id])
            ->assertOk();

        $this->actingAs($user)
            ->postJson($this->url($conducteur, 'reset'))
            ->assertOk()
            ->assertJsonPath('antenne.offset_sec', 0)
            ->assertJsonPath('antenne.en_marche', false);

        $conducteur->refresh();
        $this->assertNull($conducteur->antenne_started_at);
        $this->assertNull($conducteur->antenne_ended_at);
        $this->assertSame(0, (int) $conducteur->antenne_offset_sec);
        $this->assertNull($conducteur->active_sequence_id);

        foreach ($sequences as $sequence) {
            $this->assertSame(StatutPresentationSequenceEnum::Avenir, $sequence->fresh()->statut_presentation);
            $this->assertNull($sequence->fresh()->presentation_started_at);
            $this->assertNull($sequence->fresh()->presentation_ended_at);
        }
    }

    // =========================================================
    //  FIN DE L'ÉMISSION
    // =========================================================

    public function test_la_fin_d_emission_fige_le_chrono_sur_la_duree_totale(): void
    {
        Carbon::setTestNow('2026-10-13 12:00:00');
        [$conducteur, $sequences] = $this->conducteurAvecSequences([120, 90, 60]);
        $user = $this->user('presentateur');

        $this->actingAs($user)->postJson($this->url($conducteur, 'start'))->assertOk();

        // Large dépassement de la durée totale (270 s).
        Carbon::setTestNow('2026-10-13 12:10:00');

        $derniere = $sequences[2];
        $this->actingAs($user)
            ->postJson($this->url($conducteur, 'sequence'), ['sequence_id' => $derniere->id, 'fin' => true])
            ->assertOk()
            ->assertJsonPath('antenne.terminee', true);

        $conducteur->refresh();
        $this->assertNotNull($conducteur->antenne_ended_at);
        $this->assertNull($conducteur->antenne_started_at);
        $this->assertSame(270, (int) $conducteur->antenne_offset_sec);
        $this->assertSame(270, (int) $conducteur->antenne_ecoule_sec);

        $this->assertSame(StatutPresentationSequenceEnum::Termine, $derniere->fresh()->statut_presentation);

        Carbon::setTestNow();
    }

    // =========================================================
    //  AUTORISATION
    // =========================================================

    public function test_un_journaliste_ne_peut_pas_piloter_le_chrono(): void
    {
        [$conducteur] = $this->conducteurAvecSequences([120]);

        $this->actingAs($this->user('journaliste'))
            ->postJson($this->url($conducteur, 'start'))
            ->assertForbidden();
    }

    // =========================================================
    //  AFFICHAGE DE LA PAGE
    // =========================================================

    public function test_la_page_affiche_le_chrono_emission(): void
    {
        [$conducteur] = $this->conducteurAvecSequences([120, 90]);

        $html = $this->actingAs($this->user('presentateur'))
            ->get("/conducteurs/presentateur/{$conducteur->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("Chrono de l'émission", $html);
        $this->assertStringContainsString('antenneStart', $html);
        $this->assertStringContainsString('antenneSequence', $html);
        $this->assertSame(2, substr_count($html, 'tv-live-fill'));
    }
}
