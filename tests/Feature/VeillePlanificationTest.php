<?php

namespace Tests\Feature;

use App\Models\SourceVeille;
use App\Services\VeilleAutomationService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Automatisation de la veille : planification, exécution sans worker,
 * commande de diagnostic et contrôle dans l'audit de déploiement.
 */
class VeillePlanificationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    //  Planification
    // ---------------------------------------------------------------------

    protected function evenementVeille(): ?Event
    {
        // Force le chargement de routes/console.php (kernel console).
        app(Kernel::class);

        return collect(app(Schedule::class)->events())
            ->first(fn (Event $e) => str_contains((string) $e->command, 'veille:scrape'));
    }

    public function test_le_planificateur_verifie_les_sources_chaque_minute(): void
    {
        $evenement = $this->evenementVeille();

        $this->assertNotNull($evenement, 'La tâche « veille:scrape » devrait être planifiée.');
        $this->assertSame('* * * * *', $evenement->getExpression());
    }

    public function test_le_scraping_planifie_ne_se_chevauche_pas(): void
    {
        $evenement = $this->evenementVeille();

        $this->assertTrue($evenement->withoutOverlapping, 'Le scraping doit être protégé contre les chevauchements.');
        $this->assertSame(60, $evenement->expiresAt, 'Le verrou doit expirer au bout de 60 minutes.');
        $this->assertTrue($evenement->shouldAppendOutput);
        $this->assertStringContainsString('veille-scheduler-', $evenement->output);
    }

    public function test_la_file_est_synchrone_pour_tourner_sans_worker(): void
    {
        // En mutualisé, aucun worker ne tourne : la veille doit s'exécuter
        // directement dans le processus du cron.
        $this->assertSame('sync', config('queue.default'));
    }

    // ---------------------------------------------------------------------
    //  Fréquences par source
    // ---------------------------------------------------------------------

    protected function source(array $attributs = []): SourceVeille
    {
        return SourceVeille::create(array_merge([
            'name' => 'Source de test',
            'url' => 'https://exemple.test/feed',
            'type' => 'rss',
            'frequency' => '15min',
            'is_active' => true,
        ], $attributs));
    }

    public function test_une_source_jamais_scrapee_est_due(): void
    {
        $source = $this->source();

        $this->assertNull($source->prochainPassageAt());
        $this->assertTrue($source->estDue());
        $this->assertSame(0, $source->retardMinutes());
    }

    public function test_une_source_recemment_scrapee_n_est_pas_due(): void
    {
        $source = $this->source(['last_scraped_at' => now()->subMinutes(5)]);

        $this->assertFalse($source->estDue());
        $this->assertSame(0, $source->retardMinutes());
    }

    public function test_une_source_en_retard_est_due(): void
    {
        $source = $this->source(['last_scraped_at' => now()->subMinutes(40)]);

        $this->assertTrue($source->estDue());
        $this->assertSame(25, $source->retardMinutes());
    }

    public function test_les_frequences_sont_converties_en_minutes(): void
    {
        $this->assertSame(5, $this->source(['frequency' => '5min'])->frequenceMinutes());
        $this->assertSame(15, $this->source(['frequency' => '15min'])->frequenceMinutes());
        $this->assertSame(60, $this->source(['frequency' => '1h'])->frequenceMinutes());
        $this->assertSame(1440, $this->source(['frequency' => 'daily'])->frequenceMinutes());
        $this->assertSame(15, $this->source(['frequency' => 'inconnue'])->frequenceMinutes());
    }

    public function test_un_statut_erreur_ne_desactive_pas_la_source(): void
    {
        $source = $this->source();

        // Le job positionne « error » en cas d'échec réseau ponctuel : la
        // source doit rester active pour être retentée au passage suivant.
        $source->update(['status' => 'error']);
        $source->refresh();

        $this->assertSame('error', $source->status);
        $this->assertTrue($source->is_active, 'Une erreur ponctuelle ne doit pas désactiver la source.');
        $this->assertTrue($source->estDue() || $source->last_scraped_at !== null);
    }

    public function test_la_desactivation_met_a_jour_le_statut(): void
    {
        $source = $this->source();

        $source->update(['is_active' => false]);

        $this->assertSame('disabled', $source->fresh()->status);
        $this->assertFalse($source->fresh()->estDue());
    }

    public function test_la_reactivation_met_a_jour_le_statut(): void
    {
        $source = $this->source(['status' => 'disabled', 'is_active' => false]);

        $this->assertSame('disabled', $source->fresh()->status);

        $source->update(['status' => 'active']);

        $this->assertTrue($source->fresh()->is_active);
        $this->assertSame('active', $source->fresh()->status);
    }

    // ---------------------------------------------------------------------
    //  Commande de diagnostic
    // ---------------------------------------------------------------------

    public function test_le_statut_signale_une_veille_arretee(): void
    {
        $this->artisan('veille:statut')
            ->expectsOutputToContain('Aucune source de veille')
            ->assertExitCode(1);
    }

    public function test_le_statut_confirme_une_veille_operationnelle(): void
    {
        Cache::put(VeilleAutomationService::HEARTBEAT_KEY, now()->toIso8601String(), 600);
        $this->source(['last_scraped_at' => now()]);

        $this->artisan('veille:statut')
            ->expectsOutputToContain('Veille opérationnelle')
            ->assertExitCode(0);
    }

    public function test_le_statut_est_exportable_en_json(): void
    {
        Cache::put(VeilleAutomationService::HEARTBEAT_KEY, now()->toIso8601String(), 600);
        $this->source(['last_scraped_at' => now()]);

        $this->assertSame(0, Artisan::call('veille:statut', ['--json' => true]));

        $donnees = json_decode(Artisan::output(), true);

        $this->assertIsArray($donnees);
        $this->assertFalse($donnees['veille_arretee']);
        $this->assertSame(1, $donnees['sources_total']);
        $this->assertSame(1, $donnees['sources_actives']);
        $this->assertSame(0, $donnees['sources_dues']);
        $this->assertSame('15min', $donnees['sources'][0]['frequence']);
        $this->assertFalse($donnees['sources'][0]['due']);
    }

    // ---------------------------------------------------------------------
    //  Audit de déploiement
    // ---------------------------------------------------------------------

    public function test_l_audit_verifie_la_planification_de_la_veille(): void
    {
        $this->source(['last_scraped_at' => now()]);

        $this->assertSame(1, Artisan::call('app:audit-deploiement', ['--json' => true]));

        $resultats = json_decode(Artisan::output(), true);
        $this->assertIsArray($resultats);

        $libelles = array_column($resultats, 'niveau', 'libelle');

        $this->assertSame('ok', $libelles['Tâche « veille:scrape » planifiée'] ?? null);
        $this->assertSame('ok', $libelles['File d\'attente compatible sans worker'] ?? null);
        $this->assertSame('ok', $libelles['Sources en erreur'] ?? null);
        $this->assertSame('ok', $libelles['Dernier passage de la veille'] ?? null);
    }

    public function test_l_audit_signale_une_veille_arretee(): void
    {
        $this->source(['last_scraped_at' => now()->subHours(30)]);

        $this->assertSame(1, Artisan::call('app:audit-deploiement', ['--json' => true]));

        $resultats = json_decode(Artisan::output(), true);
        $this->assertIsArray($resultats);

        $libelles = array_column($resultats, 'niveau', 'libelle');

        $this->assertSame('erreur', $libelles['Dernier passage de la veille'] ?? null);
    }
}
