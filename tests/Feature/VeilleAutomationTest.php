<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ScrapeVeilleSourcesJob;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use App\Services\ScraperService;
use App\Services\VeilleAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VeilleAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function source(array $attributes = []): SourceVeille
    {
        return SourceVeille::create(array_merge([
            'name' => 'Source réelle de test', 'url' => 'https://93.184.215.14/feed',
            'rss_feed' => 'https://93.184.215.14/feed', 'type' => 'rss', 'frequency' => '15min',
            'is_active' => true, 'category' => 'economie', 'priority' => 7,
        ], $attributes));
    }

    private function article(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Publication économique', 'url' => 'https://93.184.215.14/article',
            'summary' => 'Résumé effectivement fourni par la source.', 'content' => 'Contenu source.',
            'image_url' => 'https://93.184.215.14/image.jpg', 'published_at' => '2026-11-13 08:00:00',
        ], $overrides);
    }

    private function scraper(array $items): ScraperService
    {
        return new class($items) extends ScraperService
        {
            public function __construct(private array $items) {}

            public function scrapeUrl(string $url, ?string $type = null): array
            {
                return $this->items;
            }
        };
    }

    public function test_la_collecte_classe_les_articles_et_preserve_leur_ordre_aux_passages_suivants(): void
    {
        $this->freezeTime();
        $source = $this->source();
        $job = new ScrapeVeilleSourcesJob($source->id, true);
        $job->handle($this->scraper([$this->article()]));
        $subject = VeilleSubject::firstOrFail();
        $collected = $subject->scraped_at;
        $this->travel(20)->minutes();
        $job->handle($this->scraper([$this->article()]));

        $this->assertSame(1, VeilleSubject::count());
        $this->assertTrue($collected->equalTo($subject->fresh()->scraped_at));
        $this->assertSame('economie', $subject->category->value);
        $this->assertSame(7, $subject->priority);
        $this->assertSame('pending', $subject->status);
        $this->assertSame(0, $source->fresh()->last_new_items_count);
        $this->assertSame(1, $source->fresh()->last_items_count);
        $this->assertNotNull($source->fresh()->last_succeeded_at);
    }

    public function test_une_source_est_due_a_l_instant_exact_de_sa_frequence(): void
    {
        $this->freezeTime();
        $source = $this->source(['frequency' => '5min', 'last_scraped_at' => now()->subMinutes(5)]);

        (new ScrapeVeilleSourcesJob($source->id))->handle($this->scraper([$this->article()]));

        $this->assertSame(1, VeilleSubject::count());
    }

    public function test_un_verrou_empeche_de_collecter_deux_fois_la_meme_source(): void
    {
        $source = $this->source();
        $lock = Cache::lock('veille:source:'.$source->id, 60);
        $lock->get();
        try {
            (new ScrapeVeilleSourcesJob($source->id, true))->handle($this->scraper([$this->article()]));
            $this->assertSame(0, VeilleSubject::count());
            $this->assertNull($source->fresh()->last_scraped_at);
        } finally {
            $lock->release();
        }
    }

    public function test_un_echec_est_reessaye_sans_perdre_la_derniere_reussite(): void
    {
        $this->travelTo(now()->startOfSecond());
        $source = $this->source(['last_succeeded_at' => now()->subHour()]);
        $lastSuccess = $source->last_succeeded_at;
        (new ScrapeVeilleSourcesJob($source->id))->handle($this->scraper([]));

        $source->refresh();
        $this->assertSame('error', $source->status);
        $this->assertTrue($source->is_active);
        $this->assertSame(1, $source->consecutive_failures);
        $this->assertTrue($lastSuccess->equalTo($source->last_succeeded_at));
        $this->assertTrue(now()->addMinutes(5)->equalTo($source->prochainPassageAt()));
        $this->travel(5)->minutes();
        (new ScrapeVeilleSourcesJob($source->id))->handle($this->scraper([$this->article()]));
        $this->assertSame('active', $source->fresh()->status);
        $this->assertSame(0, $source->fresh()->consecutive_failures);
        $this->assertNull($source->fresh()->last_error);
    }

    public function test_une_source_defaillante_n_empeche_pas_les_autres(): void
    {
        $broken = $this->source(['rss_feed' => 'https://93.184.215.14/broken']);
        $good = $this->source();
        $scraper = $this->mock(ScraperService::class)->makePartial();
        $scraper->shouldReceive('scrapeUrl')->with($broken->rss_feed, 'rss')->once()->andThrow(new \RuntimeException('Erreur réseau de test'));
        $scraper->shouldReceive('scrapeUrl')->with($good->rss_feed, 'rss')->once()->andReturn([$this->article()]);

        (new ScrapeVeilleSourcesJob)->handle($scraper);

        $this->assertSame('error', $broken->fresh()->status);
        $this->assertSame('active', $good->fresh()->status);
        $this->assertDatabaseHas('veille_subjects', ['source_id' => $good->id]);
    }

    public function test_un_budget_de_cycle_epuise_ne_demarre_pas_une_source(): void
    {
        config(['veille.cycle_time_budget' => 0]);
        $source = $this->source();

        (new ScrapeVeilleSourcesJob)->handle($this->scraper([$this->article()]));

        $this->assertNull($source->fresh()->last_scraped_at);
        $this->assertSame(0, VeilleSubject::count());
    }

    public function test_une_date_de_publication_invalide_ne_fait_pas_echouer_la_collecte(): void
    {
        $source = $this->source();

        (new ScrapeVeilleSourcesJob($source->id))->handle($this->scraper([$this->article(['published_at' => 'invalide'])]));

        $this->assertNull(VeilleSubject::firstOrFail()->published_at);
        $this->assertSame('active', $source->fresh()->status);
    }

    public function test_le_declenchement_http_programme_sans_bloquer_la_requete(): void
    {
        $source = $this->source(['last_scraped_at' => now()]);
        $disabled = $this->source(['is_active' => false]);
        $this->mock(ScraperService::class)->shouldNotReceive('scrapeUrl');

        $this->actingAs($this->user())->post(route('veille.sources.trigger'), ['source_id' => $source->id])->assertRedirect()->assertSessionHas('status');

        $this->assertNotNull($source->fresh()->collection_requested_at);
        $this->assertTrue($source->fresh()->estDue());
        $this->assertNull($disabled->fresh()->collection_requested_at);
        $this->assertSame(0, VeilleSubject::count());
    }

    public function test_modifier_l_url_efface_l_ancien_flux_et_reprogramme_la_source(): void
    {
        $source = $this->source();

        $this->actingAs($this->user())->put(route('veille.sources.update', $source), [
            'name' => $source->name, 'url' => 'https://93.184.215.14/new-feed',
        ])->assertRedirect(route('veille.sources'));

        $this->assertNull($source->fresh()->rss_feed);
        $this->assertNotNull($source->fresh()->collection_requested_at);
    }

    public function test_l_etat_automatique_signale_un_planificateur_arrete_et_un_vrai_redemarrage(): void
    {
        $this->freezeTime();
        $this->source();
        $this->actingAs($this->user())->getJson(route('veille.automation'))
            ->assertOk()->assertJsonPath('scheduler_alive', false)->assertJsonPath('state', 'stopped');

        Cache::put(VeilleAutomationService::HEARTBEAT_KEY, now()->toIso8601String(), 3600);
        $this->getJson(route('veille.automation'))->assertOk()->assertJsonPath('scheduler_alive', true)->assertJsonPath('state', 'running');
        $this->travel(4)->minutes();
        $this->getJson(route('veille.automation'))->assertOk()->assertJsonPath('scheduler_alive', false);
    }

    public function test_l_etat_automatique_est_authentifie(): void
    {
        $this->getJson(route('veille.automation'))->assertUnauthorized();
    }

    public function test_les_listes_se_rafraichissent_en_conservant_les_filtres_et_la_pagination(): void
    {
        $source = $this->source();
        foreach (range(1, 13) as $number) {
            VeilleSubject::create(['source_id' => $source->id, 'title' => 'Sujet '.$number, 'slug' => 'sujet-'.$number, 'status' => 'pending', 'category' => 'economie']);
        }
        $this->actingAs($this->user())->get(route('veille.sujets.index', ['category' => 'economie', 'status' => 'pending']))
            ->assertOk()->assertSee('data-veille-live', false)->assertSee('id="veille-subject-results"', false)
            ->assertSee('category=economie', false)->assertSee('status=pending', false)->assertSee('page=2', false);
        $this->get(route('veille.sources'))->assertOk()->assertSee('id="veille-source-rows"', false);
    }

    public function test_la_commande_signale_les_echecs_effectifs(): void
    {
        $source = $this->source();
        $this->app->instance(ScraperService::class, $this->scraper([]));

        $this->artisan('veille:scrape', ['--source' => $source->id])->assertExitCode(1);
    }

    public function test_les_articles_sont_ordonnes_par_publication_et_non_par_ordre_du_flux(): void
    {
        $source = $this->source();
        (new ScrapeVeilleSourcesJob($source->id))->handle($this->scraper([
            $this->article(['title' => 'Article récent', 'url' => 'https://93.184.215.14/recent', 'published_at' => '2026-11-13 10:00:00']),
            $this->article(['title' => 'Article ancien', 'url' => 'https://93.184.215.14/ancien', 'published_at' => '2026-11-12 10:00:00']),
        ]));

        $this->actingAs($this->user())->get(route('veille.sujets.index'))
            ->assertOk()->assertViewHas('subjects', fn ($subjects) => $subjects->pluck('title')->all() === ['Article récent', 'Article ancien']);
    }

    public function test_la_collecte_ne_reactive_pas_une_source_desactivee_pendant_le_passage(): void
    {
        $source = $this->source();
        $scraper = $this->mock(ScraperService::class)->makePartial();
        $scraper->shouldReceive('scrapeUrl')->once()->andReturnUsing(function () use ($source): array {
            $source->update(['is_active' => false]);

            return [$this->article()];
        });

        (new ScrapeVeilleSourcesJob($source->id))->handle($scraper);

        $this->assertFalse($source->fresh()->is_active);
        $this->assertSame('disabled', $source->fresh()->status);
    }
}
