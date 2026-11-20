<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ScraperService;
use Tests\TestCase;

/**
 * Scraping de la Veille — tests unitaires SANS réseau (fixtures locales).
 *
 * Ils couvrent les cas réels rencontrés sur les sources d'actualité africaine :
 * entités HTML dans un flux RSS, Atom avec <link rel="alternate">, Google News
 * sitemap, JSON-LD (Next.js), pages rendues en JavaScript où seul un scoring
 * heuristique des liens permet de retrouver les articles.
 */
class ScraperServiceTest extends TestCase
{
    private ScraperService $scraper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scraper = new ScraperService;
    }

    private function fixture(string $name): string
    {
        $path = __DIR__.'/../Fixtures/veille/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    // =========================================================
    //  FLUX RSS
    // =========================================================

    public function test_un_rss_avec_entites_html_est_analyse(): void
    {
        $items = $this->scraper->parseFeedBody($this->fixture('rss_entites_html.xml'), 'https://exemple.test/rss/actualites');

        $this->assertCount(2, $items);

        // &nbsp; et le « & » nu ne doivent pas invalider le document XML.
        $this->assertStringContainsString('MOLO', $items[0]['title']);
        $this->assertStringContainsString('PAY', $items[0]['title']);
        $this->assertSame('https://exemple.test/marches/molo-pay_65189', $items[0]['url']);
        $this->assertSame('https://exemple.test/img/65189.jpg', $items[0]['image_url']);
        $this->assertStringContainsString('expansion', $items[0]['summary']);

        $this->assertSame('https://exemple.test/marches/nouveau-fonds_65186', $items[1]['url']);
        $this->assertStringContainsString('Recherche & Développement', $items[1]['title']);
    }

    public function test_un_rss_conserve_la_date_de_publication(): void
    {
        $items = $this->scraper->parseFeedBody($this->fixture('rss_entites_html.xml'));

        $this->assertNotNull($items[0]['published_at']);
        $this->assertSame('2026-10-22', $items[0]['published_at']->toDateString());
    }

    // =========================================================
    //  FLUX ATOM
    // =========================================================

    public function test_un_atom_exploite_le_lien_alternate(): void
    {
        $items = $this->scraper->parseFeedBody($this->fixture('atom_alternate.xml'), 'https://exemple.test/atom.xml');

        $this->assertCount(2, $items);

        // rel="alternate" doit être préféré à rel="self".
        $this->assertSame('https://exemple.test/atom/premier-article', $items[0]['url']);
        $this->assertSame('https://exemple.test/atom/second-article', $items[1]['url']);
        $this->assertSame('Titre Atom numéro deux', $items[1]['title']);
        $this->assertStringContainsString('Contenu de l\'article deux', $items[1]['summary']);
    }

    // =========================================================
    //  GOOGLE NEWS SITEMAP
    // =========================================================

    public function test_un_news_sitemap_exploite_les_balises_news(): void
    {
        $items = $this->scraper->parseFeedBody($this->fixture('news_sitemap.xml'));

        $this->assertCount(2, $items);

        // <news:title> est frère de <news:publication>, pas son enfant.
        $this->assertSame('Gabon : le projet gazier de Perenco accuse deux ans de retard', $items[0]['title']);
        $this->assertSame('https://ecomatin.test/gabon/projet-gazier-perenco', $items[0]['url']);
        $this->assertSame('https://ecomatin.test/img/perenco.jpg', $items[0]['image_url']);
        $this->assertSame('2026-10-22', $items[0]['published_at']->toDateString());

        $this->assertSame('Cameroun : la BEAC relève ses taux directeurs', $items[1]['title']);
    }

    // =========================================================
    //  JSON-LD (pages rendues par JavaScript)
    // =========================================================

    public function test_le_json_ld_item_list_est_exploite(): void
    {
        $items = $this->scraper->parseHtmlBody($this->fixture('jsonld_itemlist.html'), 'https://ecomatin.test/countries/gabon');

        $this->assertCount(2, $items);

        $this->assertSame('Gabon : 16 000 nouveaux titres fonciers finalisés', $items[0]['title']);
        $this->assertSame('https://ecomatin.test/gabon/titres-fonciers', $items[0]['url']);
        $this->assertSame('https://ecomatin.test/img/titres-fonciers.jpg', $items[0]['image_url']);

        // L'image peut être une chaîne ou un objet { "@type": "ImageObject", "url": … }.
        $this->assertSame('https://ecomatin.test/img/beac.jpg', $items[1]['image_url']);
        $this->assertSame('EcoMatin', $items[0]['source']);
    }

    // =========================================================
    //  SCORING HEURISTIQUE DES LIENS
    // =========================================================

    public function test_le_listing_heuristique_ne_remonte_que_les_articles(): void
    {
        $items = $this->scraper->parseHtmlBody($this->fixture('listing_heuristique.html'), 'https://exemple.test/economie/');

        $urls = array_column($items, 'url');

        $this->assertContains('https://exemple.test/2026/10/22/le-gabon-relance-ses-exportations', $urls);
        $this->assertContains('https://exemple.test/2026/10/21/cameroun-inflation', $urls);

        // Ni la navigation, ni le pied de page, ni la pagination.
        foreach (['/a-propos', '/category/economie', '/contact', '/mentions-legales', '/page/2'] as $indesirable) {
            foreach ($urls as $url) {
                $this->assertStringNotContainsString($indesirable, $url);
            }
        }
    }

    public function test_le_listing_heuristique_extrait_titres_et_images(): void
    {
        $items = $this->scraper->parseHtmlBody($this->fixture('listing_heuristique.html'), 'https://exemple.test/economie/');

        $this->assertNotEmpty($items);
        $this->assertSame('Le Gabon relance ses exportations de manganèse', $items[0]['title']);
    }

    // =========================================================
    //  PAGE D'ARTICLE ISOLÉE
    // =========================================================

    public function test_une_page_article_renvoie_un_seul_element(): void
    {
        $items = $this->scraper->parseHtmlBody($this->fixture('article_og.html'), 'https://www.france24.com/fr/afrique/tchad-crise');

        $this->assertCount(1, $items);
        $this->assertSame('Le Tchad fait face à une crise alimentaire', $items[0]['title']);
        $this->assertStringContainsString('organisations humanitaires', $items[0]['summary']);
        $this->assertSame('https://static.test.fr/tchad-secheresse.jpg', $items[0]['image_url']);
        $this->assertSame('France 24', $items[0]['source']);
    }

    // =========================================================
    //  DÉTECTION DE FLUX
    // =========================================================

    public function test_un_flux_avec_prologue_joomla_est_reconnu(): void
    {
        // Déclaration XML, commentaires et « xml-stylesheet » avant <rss>.
        $this->assertTrue($this->scraper->looksLikeFeedBody($this->fixture('rss_prologue_joomla.xml')));
        $this->assertTrue($this->scraper->looksLikeFeedBody($this->fixture('rss_entites_html.xml')));
        $this->assertTrue($this->scraper->looksLikeFeedBody($this->fixture('atom_alternate.xml')));
        $this->assertTrue($this->scraper->looksLikeFeedBody($this->fixture('news_sitemap.xml')));
    }

    public function test_une_page_html_n_est_pas_un_flux(): void
    {
        // Un SVG embarqué contient « <?xml » : ce n'est pas pour autant un flux.
        $html = <<<'HTML'
        <?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE html>
        <html><body>
        <svg><?xml version="1.0"?><rect/></svg>
        </body></html>
        HTML;

        $this->assertFalse($this->scraper->looksLikeFeedBody($html));
        $this->assertFalse($this->scraper->looksLikeFeedBody($this->fixture('article_og.html')));
    }

    // =========================================================
    //  URLS RELATIVES
    // =========================================================

    public function test_les_urls_relatives_sont_resolues(): void
    {
        $base = 'https://exemple.test/dossier/page.html';

        $this->assertSame('https://exemple.test/a/b', $this->scraper->absoluteUrl('/a/b', $base));
        $this->assertSame('https://exemple.test/dossier/c', $this->scraper->absoluteUrl('./c', $base));
        $this->assertSame('https://exemple.test/racine', $this->scraper->absoluteUrl('../racine', $base));
        $this->assertSame('https://cdn.test/img.jpg', $this->scraper->absoluteUrl('//cdn.test/img.jpg', $base));
        $this->assertSame('https://autre.test/x', $this->scraper->absoluteUrl('https://autre.test/x', $base));
    }

    public function test_les_urls_non_navigables_sont_rejetees(): void
    {
        $base = 'https://exemple.test/dossier/page.html';

        $this->assertSame('', $this->scraper->absoluteUrl('#ancre', $base));
        $this->assertSame('', $this->scraper->absoluteUrl('mailto:a@b.test', $base));
        $this->assertSame('', $this->scraper->absoluteUrl('javascript:void(0)', $base));
        $this->assertSame('', $this->scraper->absoluteUrl('', $base));
    }

    public function test_le_fetch_refuse_une_url_non_publique_avant_toute_requete_http(): void
    {
        $this->assertNull($this->scraper->fetchPage('http://127.0.0.1/private-feed'));
        $this->assertNull($this->scraper->fetchPage('http://metadata.google.internal/'));
    }

    public function test_un_url_absolue_conserve_son_hote(): void
    {
        // « www. » ne doit jamais être retiré : certains sites servent un
        // contenu différent sur l'apex et sur www.
        $this->assertSame(
            'https://www.rfi.fr/fr/afrique/rss',
            $this->scraper->absoluteUrl('https://www.rfi.fr/fr/afrique/rss', 'https://www.rfi.fr/fr/afrique/')
        );
    }

    // =========================================================
    //  CHOIX DU FLUX
    // =========================================================

    public function test_un_flux_non_vide_est_prefere_a_un_flux_vide(): void
    {
        // Régression « Agence Ecofin » : /rss est un flux valide mais VIDE,
        // /feed contient les articles. S'arrêter au premier XML valide perdait
        // toute la source.
        $choisi = $this->scraper->pickFeed([
            ['url' => 'https://exemple.test/rss', 'items' => 0],
            ['url' => 'https://exemple.test/feed', 'items' => 10],
            ['url' => 'https://exemple.test/atom.xml', 'items' => 0],
        ]);

        $this->assertSame('https://exemple.test/feed', $choisi);
    }

    public function test_un_flux_vide_est_accepte_si_c_est_le_seul(): void
    {
        $this->assertSame(
            'https://exemple.test/rss',
            $this->scraper->pickFeed([['url' => 'https://exemple.test/rss', 'items' => 0]])
        );

        $this->assertNull($this->scraper->pickFeed([]));
    }
}
