<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Enums\PosteScanEnum;
use App\Models\Conducteur;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\SequenceIntervenantPivot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParcoursIntervenantHistoriqueTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    private function parcours(): ParcoursIntervenant
    {
        $conducteur = Conducteur::create([
            'nom' => 'Conducteur historique',
            'date_edition' => today()->toDateString(),
            'type' => 'redaction',
        ]);
        $sequence = Sequence::create([
            'conducteur_id' => $conducteur->id,
            'title' => 'Invité du jour',
            'type' => 'SUJET',
            'position' => 1,
        ]);
        $intervenant = Intervenant::create(['nom' => 'Test', 'prenom' => 'Invité', 'is_active' => true]);
        $pivot = SequenceIntervenantPivot::create([
            'sequence_id' => $sequence->id,
            'intervenant_id' => $intervenant->id,
        ]);

        return $pivot->parcours()->firstOrFail();
    }

    public function test_revalider_une_etape_conserve_la_date_et_l_auteur_initial(): void
    {
        $this->travelTo(now()->startOfSecond());
        $parcours = $this->parcours();
        $user = $this->user();

        $this->actingAs($user)->postJson(route('parcours-intervenants.etape', $parcours), [
            'etape' => 'confirme', 'notes' => 'Confirmation initiale',
        ])->assertOk()->assertJsonPath('deja_fait', false);

        $date = $parcours->fresh()->confirme_at;
        $this->travel(10)->minutes();
        $this->actingAs($this->user())->postJson(route('parcours-intervenants.etape', $parcours), [
            'etape' => 'confirme', 'notes' => 'Nouvelle tentative',
        ])->assertOk()->assertJsonPath('deja_fait', true);

        $events = $parcours->events()->where('etape', 'confirme')->get();
        $this->assertCount(1, $events);
        $this->assertSame($user->id, $events->first()->triggered_by_user_id);
        $this->assertSame('Confirmation initiale', $events->first()->notes);
        $this->assertTrue($date->equalTo($parcours->fresh()->confirme_at));
        $this->assertTrue($date->equalTo($events->first()->recorded_at));
    }

    public function test_la_liste_et_le_detail_affichent_la_date_et_l_heure_des_etapes_validees(): void
    {
        $date = now()->startOfDay()->setTime(15, 42, 0);
        $this->travelTo($date);

        $parcours = $this->parcours();
        $user = $this->user();
        $parcours->avancerEtape(EtapeParcoursIntervenantEnum::Confirme, $user, 'bouton_web');
        $this->travel(2)->minutes();
        $parcours->avancerEtape(EtapeParcoursIntervenantEnum::EnChemin, $user, 'bouton_web');

        $horodatageConfirme = $date->format('d/m/Y').' · '.$date->format('H:i');
        $horodatageEnChemin = $date->copy()->addMinutes(2)->format('d/m/Y').' · '.$date->copy()->addMinutes(2)->format('H:i');
        $html = $this->actingAs($user)
            ->get(route('parcours-intervenants.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Horodatages des étapes validées', $html);
        $this->assertStringContainsString($horodatageConfirme, $html);
        $this->assertStringContainsString($horodatageEnChemin, $html);

        $this->actingAs($user)
            ->getJson(route('parcours-intervenants.etat', $parcours))
            ->assertOk()
            ->assertJsonPath('parcours.etapes.1.horodatage', $horodatageConfirme)
            ->assertJsonPath('parcours.etapes.2.horodatage', $horodatageEnChemin);
    }

    public function test_les_nouvelles_etapes_et_les_etapes_historiques_restent_exploitables(): void
    {
        $parcours = $this->parcours();
        $user = $this->user();

        // ⛔ SCAN OBLIGATOIRE : l'arrivée se valide au poste Accueil du Scanner
        //    QR avant que les étapes studio ne soient accessibles manuellement.
        $parcours->intervenant->triggerScanWorkflow(PosteScanEnum::Accueil, $user->id, $parcours->sequence);

        foreach (['en_chemin', 'en_attente', 'diffuse_termine'] as $etape) {
            $this->actingAs($user)->postJson(route('parcours-intervenants.etape', $parcours), [
                'etape' => $etape,
            ])->assertOk();

            $this->assertDatabaseHas('parcours_intervenant_events', [
                'parcours_intervenant_id' => $parcours->id,
                'etape' => $etape,
                'triggered_by_user_id' => $user->id,
            ]);
        }

        $this->assertNotNull($parcours->fresh()->en_attente_at);
        $this->assertNotNull($parcours->fresh()->diffuse_termine_at);
    }

    public function test_chaque_parcours_a_un_jeton_distinct_et_un_qr_identifiable(): void
    {
        $first = $this->parcours();
        $second = $this->parcours();

        $path = (string) parse_url($first->urlQr(), PHP_URL_PATH);

        $this->assertNotEmpty($first->qr_token);
        $this->assertNotSame($first->qr_token, $second->qr_token);
        $this->assertSame(1, preg_match('~^/i/checkin/([A-Za-z0-9]{48})\.png$~', $path, $matches));
        $this->assertSame($first->qr_token, $matches[1]);
    }

    public function test_un_scan_reitere_preserve_l_arrivee_et_rattache_les_traces_au_parcours(): void
    {
        $this->travelTo(now()->startOfSecond());
        $parcours = $this->parcours();
        $user = $this->user();
        $intervenant = $parcours->intervenant;

        $first = $intervenant->triggerScanWorkflow(PosteScanEnum::Accueil, $user->id, $parcours->sequence);
        $this->travel(10)->minutes();
        $second = $intervenant->triggerScanWorkflow(PosteScanEnum::Accueil, $user->id, $parcours->sequence);

        $this->assertFalse($first['deja_fait']);
        $this->assertTrue($second['deja_fait']);
        $this->assertTrue($first['date']->equalTo($second['date']));
        $this->assertSame(1, $parcours->events()->where('etape', 'arrive_accueil')->count());
        $this->assertSame(2, $intervenant->scanLogs()->where('parcours_intervenant_id', $parcours->id)->count());
    }

    public function test_les_anciens_horodatages_du_pivot_sont_repris_dans_l_historique(): void
    {
        $parcours = $this->parcours();
        $pivot = $parcours->pivotSequenceIntervenant;
        $date = now()->subMinutes(15)->startOfSecond();
        $pivot->update(['heure_arrive' => $date]);

        $pivot->retroActiverParcoursDepuisPivot();

        $this->assertTrue($parcours->fresh()->arrive_accueil_at->equalTo($date));
        $event = $parcours->events()->where('etape', 'arrive_accueil')->firstOrFail();
        $this->assertTrue($event->recorded_at->equalTo($date));
    }
}
