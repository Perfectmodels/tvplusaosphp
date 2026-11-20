<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ScrapeVeilleSourcesJob;
use App\Models\SourceVeille;
use App\Models\VeilleSubject;
use App\Services\ScraperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScrapeVeilleJobTest extends TestCase
{
    use RefreshDatabase;

    private function source(): SourceVeille
    {
        return SourceVeille::create([
            'name' => 'Source de test',
            'type' => 'rss',
            'url' => 'https://93.184.215.14/feed',
            'rss_feed' => 'https://93.184.215.14/feed.xml',
            'frequency' => '15min',
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function scraper(array $items): ScraperService
    {
        return new class($items) extends ScraperService
        {
            /** @param array<int, array<string, mixed>> $items */
            public function __construct(private array $items) {}

            public function scrapeUrl(string $url, ?string $type = null): array
            {
                return $this->items;
            }
        };
    }

    public function test_un_nouveau_scrape_necrase_pas_les_retouches_editoriales(): void
    {
        $source = $this->source();
        $subject = VeilleSubject::create([
            'source_id' => $source->id,
            'title' => 'Titre corrigé par la rédaction',
            'slug' => 'titre-corrige-redaction',
            'url' => 'https://93.184.215.14/article-1',
            'summary' => 'Résumé éditorial relu',
            'priority' => 9,
            'category' => 'economie',
            'status' => 'approved',
        ]);

        $job = new ScrapeVeilleSourcesJob($source->id);
        $job->handle($this->scraper([[
            'title' => 'Titre automatique différent',
            'url' => 'https://93.184.215.14/article-1',
            'summary' => 'Résumé généré automatiquement',
            'content' => 'Contenu détaillé récupéré au second passage.',
            'image_url' => 'https://93.184.215.14/image.jpg',
            'category' => 'politique',
        ]]));

        $subject->refresh();

        $this->assertSame('Titre corrigé par la rédaction', $subject->title);
        $this->assertSame('Résumé éditorial relu', $subject->summary);
        $this->assertSame('Contenu détaillé récupéré au second passage.', $subject->content);
        $this->assertSame('economie', $subject->category->value);
        $this->assertSame(9, $subject->priority);
        $this->assertSame('approved', $subject->status);
    }

    public function test_un_article_sans_url_ne_se_duplique_pas_a_chaque_passage(): void
    {
        $source = $this->source();
        $job = new ScrapeVeilleSourcesJob($source->id);
        $items = [[
            'title' => 'Article de test sans lien',
            'summary' => 'Résumé de test',
        ]];

        $job->handle($this->scraper($items));
        $source->update(['last_scraped_at' => null]);
        $job->handle($this->scraper($items));

        $this->assertSame(1, VeilleSubject::query()
            ->where('source_id', $source->id)
            ->where('title', 'Article de test sans lien')
            ->count());
    }

    public function test_une_collecte_sans_donnee_exploitable_est_signalee_sans_desactiver_la_source(): void
    {
        $source = $this->source();

        (new ScrapeVeilleSourcesJob($source->id))->handle($this->scraper([]));

        $source->refresh();
        $this->assertSame('error', $source->status);
        $this->assertTrue($source->is_active);
        $this->assertNotNull($source->last_scraped_at);
    }
}
