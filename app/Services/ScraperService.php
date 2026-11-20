<?php

declare(strict_types=1);

namespace App\Services;

use App\Rules\UrlPublique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Extraction de contenus d'actualité (module Veille).
 *
 * Problème d'origine : un simple parseur DOM avec une liste de sélecteurs CSS
 * codés en dur ne suffit plus. Les sites de presse modernes (RFI, France 24,
 * EcoMatin…) ont des balisages très différents, et beaucoup sont rendus par
 * JavaScript (Next.js / React Server Components) : la liste des articles n'est
 * alors PAS dans le HTML servi.
 *
 * Stratégies appliquées, du plus fiable au plus fragile :
 *   1. Flux d'actualité (RSS 2.0 · Atom · RDF · Google News sitemap) → scrapeFeed()
 *   2. Données structurées JSON-LD (schema.org NewsArticle/ItemList)  → parseJsonLd()
 *   3. Payload applicatif embarqué (SPA : __NEXT_DATA__)              → parseSpaPayload()
 *   4. Scoring heuristique des liens de la page HTML                  → extractLinks()
 *
 * Une URL de « page » est automatiquement résolue vers son flux quand il existe
 * (resolveFeedUrl) : la plupart des sites de presse en exposent un.
 */
class ScraperService
{
    /** Nombre maximal d'articles renvoyés par source. */
    public const MAX_ITEMS = 25;

    /** User-Agent de navigateur : beaucoup de sites filtrent les robots nommés. */
    protected const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /** Chemins usuels d'un flux, sondés si l'auto-découverte échoue. */
    protected const FEED_PATHS = [
        '/feed.xml',
        '/rss',
        '/rss.xml',
        '/atom.xml',
        '/feed',
        '/index.xml',
        '/news-sitemap.xml',
        '/sitemap-news.xml',
    ];

    /** Sélecteurs historiques : conservés comme simple BONUS de priorité. */
    protected const KNOWN_SELECTORS = [
        'article h2 a', 'article h3 a',
        '.post h2 a', '.post h3 a',
        '.card h2 a', '.card h3 a',
        '.list-news li a', '.news-list li a',
        'h2.entry-title a', 'h3.entry-title a',
        '.c-news__title a', '.title a',
    ];

    /** Libellés de liens qui ne sont jamais un titre d'article. */
    protected const GENERIC_ANCHORS = [
        'lire', 'lire la suite', 'lire plus', 'lire l\'article', 'en savoir plus',
        'voir plus', 'voir', 'voir tout', 'plus', 'suite', 'read more', 'read',
        'more', 'cliquez ici', 'ici', '→', '›', '>>', 'suivant', 'précédent',
    ];

    /** Segments d'URL qui signalent une page de rubrique, pas un article. */
    protected const NON_ARTICLE_PATH = '~/(category|categories|tag|tags|author|authors|auteur|page|rubrique|rubriques|theme|themes|a-propos|about|contact|mentions[^/]*|cgv|cgu|search|recherche|login|connexion|inscription|newsletter|archives?|plan-du-site|sitemap)[/.]*$~i';

    /** Titres JSON-LD correspondant à un article. */
    protected const JSONLD_ARTICLE_TYPES = [
        'newsarticle', 'article', 'reportagenewsarticle', 'analysisnewsarticle',
        'backgroundnewsarticle', 'opinionnewsarticle', 'liveblogposting', 'blogposting',
    ];

    /** Titres JSON-LD correspondant à une liste d'articles. */
    protected const JSONLD_LIST_TYPES = ['itemlist', 'collectionpage', 'blog'];

    protected ?HttpBrowser $browser = null;

    protected mixed $client = null;

    private ?float $deadline = null;

    public function withinBudget(int $seconds, callable $callback): mixed
    {
        $previous = $this->deadline;
        $this->deadline = microtime(true) + max(1, $seconds);

        try {
            return $callback();
        } finally {
            $this->deadline = $previous;
        }
    }

    // =====================================================================
    //  HTTP
    // =====================================================================

    protected function headers(array $extra = []): array
    {
        return array_merge([
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'DNT' => '1',
        ], $extra);
    }

    /** Second profil d'en-têtes pour les sites protégés par un WAF. */
    protected function fallbackHeaders(array $extra = []): array
    {
        return array_merge([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            'Accept' => '*/*',
            'Accept-Language' => 'fr,fr-FR;q=0.9,en;q=0.8',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
        ], $extra);
    }

    protected function client(): mixed
    {
        if (! $this->client) {
            $this->client = HttpClient::create([
                'headers' => $this->headers(),
                'timeout' => 30,
                'max_redirects' => 5,
                'verify_peer' => true,
            ]);
        }

        return $this->client;
    }

    protected function getBrowser(): HttpBrowser
    {
        if (! $this->browser) {
            $this->browser = new HttpBrowser(HttpClient::create([
                'headers' => $this->headers(),
                'timeout' => 30,
                'max_redirects' => 5,
                'verify_peer' => true,
            ]));
        }

        return $this->browser;
    }

    /** Télécharge un document et renvoie son corps, ou null. */
    public function fetchPage(string $url): ?string
    {
        return $this->fetch($url);
    }

    protected function fetch(string $url, array $extraHeaders = []): ?string
    {
        if ($this->deadline !== null && microtime(true) >= $this->deadline) {
            return null;
        }
        if (! $this->urlIsPublic($url)) {
            Log::warning("[Scraper] URL non publique refusée: {$url}");

            return null;
        }

        $profiles = [$this->headers($extraHeaders), $this->fallbackHeaders($extraHeaders)];

        foreach ($profiles as $index => $headers) {
            $currentUrl = $url;

            for ($redirectCount = 0; $redirectCount <= 5; $redirectCount++) {
                if (! $this->urlIsPublic($currentUrl)) {
                    Log::warning("[Scraper] Redirection vers une URL non publique refusée: {$currentUrl}");

                    return null;
                }

                try {
                    $remaining = $this->deadline !== null ? $this->deadline - microtime(true) : config('veille.http_timeout', 12);
                    if ($remaining <= 0) {
                        return null;
                    }
                    // Les redirections sont suivies manuellement afin de valider
                    // chaque destination au lieu de laisser le client HTTP les
                    // suivre sans contrôle.
                    $response = $this->client()->request('GET', $currentUrl, [
                        'headers' => $headers,
                        'max_redirects' => 0,
                        'timeout' => min(config('veille.http_timeout', 12), $remaining),
                        'max_duration' => min(config('veille.http_timeout', 12), $remaining),
                    ]);
                    $status = $response->getStatusCode();

                    if (in_array($status, [301, 302, 303, 307, 308], true)) {
                        if ($redirectCount === 5) {
                            Log::warning("[Scraper] Trop de redirections pour {$url}");

                            return null;
                        }

                        $location = $response->getHeaders(false)['location'][0] ?? '';
                        $nextUrl = $location !== '' ? $this->absoluteUrl($location, $currentUrl) : '';
                        if ($nextUrl === '' || ! $this->urlIsPublic($nextUrl)) {
                            Log::warning("[Scraper] Destination de redirection refusée pour {$url}");

                            return null;
                        }

                        $currentUrl = $nextUrl;

                        continue;
                    }

                    if ($status < 400) {
                        $body = $response->getContent(false);

                        return $body === '' ? null : $body;
                    }

                    Log::warning("[Scraper] HTTP {$status} sur {$currentUrl}");

                    // 403/429 : on tente un second profil d'en-têtes avant d'abandonner.
                    if ($index === 0 && in_array($status, [403, 429], true)) {
                        continue 2;
                    }

                    return null;
                } catch (\Throwable $e) {
                    Log::warning("[Scraper] Échec HTTP {$currentUrl}: {$e->getMessage()}");

                    // On tente le profil suivant, puis on abandonne.
                    continue 2;
                }
            }
        }

        return null;
    }

    protected function urlIsPublic(string $url): bool
    {
        $invalid = false;
        (new UrlPublique)->validate('url', $url, static function () use (&$invalid): void {
            $invalid = true;
        });

        return ! $invalid;
    }

    // =====================================================================
    //  POINT D'ENTRÉE
    // =====================================================================

    /**
     * @param  string|null  $type  rss|atom|feed · html|web · article|page · auto
     * @return array<int, array<string, mixed>>
     */
    public function scrapeUrl(string $url, ?string $type = null): array
    {
        $type = $type !== null && trim($type) !== '' ? strtolower(trim($type)) : 'auto';

        try {
            $items = match ($type) {
                'rss', 'atom', 'feed', 'flux', 'sitemap' => $this->scrapeFeed($url),
                'html', 'web' => $this->scrapeHtml($url),
                'article', 'page', 'detail' => $this->scrapeArticle($url),
                default => $this->scrapeAuto($url),
            };
        } catch (\Throwable $e) {
            Log::error("[Scraper] Erreur sur {$url}: {$e->getMessage()}");

            return [];
        }

        // Une source déclarée « rss » pointant vers une page HTML ne doit pas
        // renvoyer vide : on retombe sur la résolution automatique.
        if ($items === [] && in_array($type, ['rss', 'atom', 'feed', 'flux', 'sitemap'], true)) {
            $items = $this->scrapeAuto($url);
        }

        return $this->limit($items);
    }

    /** Résout le flux du site si possible, sinon scrape la page HTML. */
    protected function scrapeAuto(string $url): array
    {
        $feedUrl = $this->resolveFeedUrl($url);

        if ($feedUrl && $this->normalizeUrl($feedUrl) !== $this->normalizeUrl($url)) {
            $items = $this->scrapeFeed($feedUrl);
            if (count($items) > 0) {
                Log::info('[Scraper] '.$url.' → flux '.$feedUrl.' ('.count($items).' items)');

                return $items;
            }
        }

        return $this->scrapeHtml($url);
    }

    public function detectType(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $lower = strtolower($url);

        if (str_contains($lower, 'rss')
            || str_contains($lower, 'atom')
            || str_contains($lower, 'feed')
            || str_contains($lower, 'sitemap')
            || str_ends_with(strtolower($path), '.xml')) {
            return 'rss';
        }

        return 'html';
    }

    // =====================================================================
    //  RÉSOLUTION DU FLUX
    // =====================================================================

    /**
     * Trouve l'URL du flux d'actualité d'un site.
     *
     * 1. <link rel="alternate" type="application/rss+xml|atom+xml"> du <head>
     * 2. chemins usuels (/feed.xml, /rss, /atom.xml, …)
     * 3. lignes « Sitemap: » du robots.txt
     */
    public function resolveFeedUrl(string $url): ?string
    {
        $origin = $this->originOf($url);
        if ($origin === null) {
            return null;
        }

        $candidates = [];

        $html = $this->fetch($url);
        if ($html !== null) {
            if ($this->looksLikeFeedBody($html) && $this->parseFeedBody($html, $url) !== []) {
                return $url;
            }
            foreach ($this->findFeedLinksInHtml($html, $url) as $link) {
                $candidates[] = $link;
            }
        }

        foreach (self::FEED_PATHS as $path) {
            $candidates[] = $origin.$path;
        }

        $robots = $this->fetch($origin.'/robots.txt');
        if ($robots !== null && preg_match_all('/^Sitemap:\s*(.+?)\s*$/mi', $robots, $m)) {
            foreach ($m[1] as $line) {
                $candidates[] = $line;
            }
        }

        $seen = [];
        $probed = [];

        foreach ($candidates as $candidate) {
            $candidate = $this->absoluteUrl($candidate, $url);
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;

            $body = $this->fetch($candidate);
            if ($body === null || ! $this->looksLikeFeedBody($body)) {
                continue;
            }

            $probed[] = [
                'url' => $candidate,
                'items' => count($this->parseFeedBody($body, $candidate)),
            ];
            if ($probed[array_key_last($probed)]['items'] > 0) {
                return $candidate;
            }
        }

        return $this->pickFeed($probed);
    }

    /**
     * Choisit le meilleur flux parmi les candidats déjà testés.
     *
     * Un flux qui contient des articles l'emporte toujours sur un flux vide :
     * certains sites exposent plusieurs URL de flux dont une seule est réellement
     * alimentée (ex. Agence Ecofin : /rss est vide alors que /feed contient les
     * articles). S'arrêter au premier XML valide ferait perdre toute la source.
     *
     * @param  array<int, array{url: string, items: int}>  $probed
     */
    public function pickFeed(array $probed): ?string
    {
        $empty = null;

        foreach ($probed as $entry) {
            if ((int) ($entry['items'] ?? 0) > 0) {
                return (string) $entry['url'];
            }

            $empty ??= (string) $entry['url'];
        }

        return $empty;
    }

    /** @return array<int, string> Liens de flux déclarés dans le <head> d'une page. */
    public function findFeedLinksInHtml(string $html, string $baseUrl): array
    {
        if (! preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            return [];
        }

        $out = [];
        foreach ($tags[0] as $tag) {
            $rel = strtolower((string) $this->attrOf($tag, 'rel'));
            if ($rel !== 'alternate') {
                continue;
            }

            $type = strtolower((string) $this->attrOf($tag, 'type'));
            if ($type !== '' && ! preg_match('/(rss|atom|xml|json)/i', $type)) {
                continue;
            }

            $href = (string) $this->attrOf($tag, 'href');
            if ($href === '') {
                continue;
            }

            $abs = $this->absoluteUrl($href, $baseUrl);
            if ($abs !== '') {
                $out[] = $abs;
            }
        }

        return $out;
    }

    /**
     * Un corps de document est-il un flux ? (pur, sans réseau → testable)
     */
    public function looksLikeFeedBody(string $body): bool
    {
        $head = ltrim($body, "\xEF\xBB\xBF \t\n\r");
        if ($head === '') {
            return false;
        }

        // Retire le prologue XML : déclaration, instructions de traitement
        // (balises « xml-stylesheet ») et commentaires. La racine doit ensuite
        // être un vrai document de flux (rss / feed / rdf:RDF / urlset) — on
        // impose sa position en tête, car une page HTML contient souvent une
        // déclaration XML via un SVG embarqué, ce qui provoquait de faux positifs.
        while (preg_match('/^<(\?[^>]*\?|!--.*?--)>/s', $head, $match)) {
            $head = ltrim(substr($head, strlen($match[0])), " \t\n\r");
        }

        return (bool) preg_match('/^<(rss|feed|rdf:RDF|urlset)\b/i', $head);
    }

    // =====================================================================
    //  FLUX : RSS 2.0 · ATOM · RDF · NEWS SITEMAP
    // =====================================================================

    /** @return array<int, array<string, mixed>> */
    protected function scrapeFeed(string $url): array
    {
        $body = $this->fetch($url);
        if ($body === null) {
            return [];
        }

        return $this->parseFeedBody($body, $url);
    }

    /**
     * Parse le corps d'un flux (méthode pure, sans réseau → testable).
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseFeedBody(string $body, string $url = ''): array
    {
        $body = ltrim($body, "\xEF\xBB\xBF \t\n\r");

        if ($body === '') {
            return [];
        }

        // Google News sitemap (<urlset><url><news:news>…)
        if (stripos($body, '<news:news') !== false) {
            $items = $this->parseNewsSitemap($body);
            if (count($items) > 0) {
                return $items;
            }
        }

        $xml = $this->toXml($body);
        if ($xml === null) {
            return [];
        }

        $items = $this->parseRss($xml, $url);
        if (count($items) === 0) {
            $items = $this->parseAtom($xml, $url);
        }

        // Sitemap « plat » (sans news:) en dernier recours.
        if (count($items) === 0 && isset($xml->url)) {
            $items = $this->parseNewsSitemap($body);
        }

        return $items;
    }

    protected function toXml(string $body): ?\SimpleXMLElement
    {
        $flags = LIBXML_NOCDATA | LIBXML_NONET;
        $body = $this->sanitizeXml($body);

        $previous = libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', $flags);

        if (! $xml instanceof \SimpleXMLElement && preg_match('/<(\?xml|rss|feed|rdf:RDF|urlset)\b/i', $body, $m, PREG_OFFSET_CAPTURE)) {
            // Certains flux sont précédés de texte parasite : on repart du premier tag.
            libxml_clear_errors();
            $xml = @simplexml_load_string(substr($body, (int) $m[0][1]), 'SimpleXMLElement', $flags);
        }

        if (! $xml instanceof \SimpleXMLElement) {
            $first = libxml_get_errors()[0]->message ?? 'erreur inconnue';
            Log::warning('[Scraper] XML illisible : '.trim($first));
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml instanceof \SimpleXMLElement ? $xml : null;
    }

    /**
     * Rend un document XML « sale » analysable.
     *
     * Beaucoup de flux de presse embarquent des entités HTML (&nbsp;, &eacute;…)
     * qui n'existent pas en XML : simplexml échoue alors sur tout le document.
     */
    protected function sanitizeXml(string $body): string
    {
        // 1. Entités HTML nommées → leur caractère Unicode.
        $body = preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]{1,31});/', static function (array $m): string {
            $name = $m[1];
            if (in_array($name, ['amp', 'lt', 'gt', 'quot', 'apos'], true)) {
                return $m[0];
            }

            $decoded = html_entity_decode('&'.$name.';', ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Entité inconnue : neutralisée plutôt que laissée invalide.
            return $decoded === '&'.$name.';' ? ' ' : $decoded;
        }, $body) ?? $body;

        // 2. Esperluettes nues (invalides en XML) → &amp;
        return preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9A-Fa-f]+);)/', '&amp;', $body) ?? $body;
    }

    /** @return array<int, array<string, mixed>> */
    protected function parseRss(\SimpleXMLElement $xml, string $baseUrl = ''): array
    {
        if (! isset($xml->channel->item)) {
            return [];
        }

        $feedTitle = $this->cleanText((string) ($xml->channel->title ?? ''));
        $items = [];

        foreach ($xml->channel->item as $entry) {
            $title = $this->cleanText((string) ($entry->title ?? ''));
            $link = trim((string) ($entry->link ?? ''));
            if ($link === '') {
                $link = trim((string) ($entry->guid ?? ''));
            }

            $desc = (string) ($entry->description ?? '');
            $content = $this->namespacedText($entry, 'http://purl.org/rss/1.0/modules/content/', 'encoded');
            if ($desc === '' && $content !== '') {
                $desc = $content;
            }

            $pub = trim((string) ($entry->pubDate ?? ''));
            if ($pub === '') {
                $pub = $this->namespacedText($entry, 'http://purl.org/dc/elements/1.1/', 'date');
            }

            $item = [
                'title' => $title,
                'url' => $this->absoluteUrl($link, $baseUrl),
                'summary' => $this->cleanText($desc),
                'content' => $content !== '' ? $content : $desc,
                'published_at' => $this->parseDate($pub),
                'image_url' => $this->imageFromEntry($entry),
                'source' => $feedTitle !== '' ? $feedTitle : (parse_url($link, PHP_URL_HOST) ?? ''),
                'scraped_at' => now(),
            ];

            if ($item['title'] === '' && $item['url'] === '') {
                continue;
            }

            $items[] = array_filter($item, static fn ($v) => $v !== null && $v !== '');
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    protected function parseAtom(\SimpleXMLElement $xml, string $baseUrl = ''): array
    {
        if (! isset($xml->entry)) {
            return [];
        }

        $feedTitle = $this->cleanText((string) ($xml->title ?? ''));
        $items = [];

        foreach ($xml->entry as $entry) {
            $title = $this->cleanText((string) ($entry->title ?? ''));

            $link = '';
            if (isset($entry->link)) {
                foreach ($entry->link as $lnk) {
                    $href = trim((string) ($lnk['href'] ?? ''));
                    if ($href === '') {
                        $href = trim((string) $lnk);
                    }
                    if ($href === '') {
                        continue;
                    }
                    $rel = strtolower(trim((string) ($lnk['rel'] ?? 'alternate')));
                    if ($rel === 'alternate' || $rel === '') {
                        $link = $href;
                        break;
                    }
                    if ($link === '') {
                        $link = $href;
                    }
                }
            }
            if ($link === '') {
                $link = trim((string) ($entry->id ?? ''));
            }

            $desc = (string) ($entry->summary ?? '');
            $content = (string) ($entry->content ?? '');
            if ($desc === '') {
                $desc = $content;
            }

            $pub = trim((string) ($entry->published ?? ''));
            if ($pub === '') {
                $pub = trim((string) ($entry->updated ?? ''));
            }

            $item = [
                'title' => $title,
                'url' => $this->absoluteUrl($link, $baseUrl),
                'summary' => $this->cleanText($desc),
                'content' => $content !== '' ? $content : $desc,
                'published_at' => $this->parseDate($pub),
                'image_url' => $this->imageFromEntry($entry),
                'source' => $feedTitle !== '' ? $feedTitle : (parse_url($link, PHP_URL_HOST) ?? ''),
                'scraped_at' => now(),
            ];

            if ($item['title'] === '' && $item['url'] === '') {
                continue;
            }

            $items[] = array_filter($item, static fn ($v) => $v !== null && $v !== '');
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    protected function parseNewsSitemap(string $body): array
    {
        $xml = $this->toXml($body);
        if ($xml === null || ! isset($xml->url)) {
            return [];
        }

        $newsNs = 'http://www.google.com/schemas/sitemap-news/0.9';
        $imageNs = 'http://www.google.com/schemas/sitemap-image/1.1';

        $items = [];
        foreach ($xml->url as $node) {
            $loc = trim((string) ($node->loc ?? ''));
            if ($loc === '') {
                continue;
            }

            $title = '';
            $pub = '';
            $keywords = '';

            try {
                $news = $node->children($newsNs)->news ?? null;
                if ($news !== null) {
                    // <news:title>, <news:publication_date> et <news:keywords>
                    // sont des FRÈRES de <news:publication>, pas ses enfants.
                    $title = $this->cleanText((string) ($news->title ?? ''));
                    $pub = trim((string) ($news->publication_date ?? ''));
                    $keywords = trim((string) ($news->keywords ?? ''));
                }
            } catch (\Throwable) {
            }

            $image = '';
            try {
                $img = $node->children($imageNs)->image ?? null;
                if ($img !== null) {
                    $image = trim((string) ($img->children($imageNs)->loc ?? ''));
                }
            } catch (\Throwable) {
            }

            if ($title === '') {
                $title = $this->cleanText(basename((string) (parse_url($loc, PHP_URL_PATH) ?? '')));
            }

            $item = [
                'title' => $title,
                'url' => $this->absoluteUrl($loc, $loc),
                'summary' => $keywords,
                'content' => $keywords,
                'published_at' => $this->parseDate($pub),
                'image_url' => $image,
                'source' => parse_url($loc, PHP_URL_HOST) ?? '',
                'scraped_at' => now(),
            ];

            if ($item['title'] === '' && $item['url'] === '') {
                continue;
            }

            $items[] = array_filter($item, static fn ($v) => $v !== null && $v !== '');
        }

        return $items;
    }

    // =====================================================================
    //  HTML
    // =====================================================================

    /** @return array<int, array<string, mixed>> */
    protected function scrapeHtml(string $url): array
    {
        $body = $this->fetch($url);
        if ($body === null) {
            return [];
        }

        return $this->parseHtmlBody($body, $url);
    }

    /** Page d'article : renvoie exactement un item (ou vide). */
    protected function scrapeArticle(string $url): array
    {
        $body = $this->fetch($url);
        if ($body === null) {
            return [];
        }

        $item = $this->pageAsItem($body, $url);

        return $item === null ? [] : [$item];
    }

    /**
     * Analyse une page HTML (méthode pure, sans réseau → testable).
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseHtmlBody(string $body, string $url): array
    {
        try {
            $crawler = new Crawler($body, $url);
        } catch (\Throwable $e) {
            Log::warning("[Scraper] Parse HTML impossible pour {$url}: {$e->getMessage()}");

            return [];
        }

        // 1. Données structurées schema.org
        $items = $this->parseJsonLd($body, $url);
        if (count($items) > 0) {
            return $this->limit($items);
        }

        // 2. Payload applicatif embarqué (SPA / Next.js)
        $items = $this->parseSpaPayload($body, $url);
        if (count($items) > 0) {
            return $this->limit($items);
        }

        // 3. Scoring heuristique des liens de la page
        $items = $this->extractLinks($crawler, $url);
        if (count($items) > 0) {
            return $this->limit($items);
        }

        // 4. Page d'article isolée → un seul item
        $single = $this->pageAsItem($body, $url);

        return $single === null ? [] : [$single];
    }

    // ---------------------------------------------------------------------
    //  1. JSON-LD
    // ---------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    protected function parseJsonLd(string $body, string $url): array
    {
        if (! preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $body, $m)) {
            return [];
        }

        $out = [];
        foreach ($m[1] as $raw) {
            $json = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $data = json_decode($json, true);
            if (! is_array($data)) {
                continue;
            }

            foreach ($this->flattenJsonLd($data) as $node) {
                $types = array_map('strtolower', (array) ($node['@type'] ?? $node['type'] ?? []));

                if (array_intersect($types, self::JSONLD_ARTICLE_TYPES)) {
                    $item = $this->jsonLdToItem($node, $url);
                    if ($item !== null) {
                        $out[$item['url']] = $item;
                    }
                }

                if (array_intersect($types, self::JSONLD_LIST_TYPES)) {
                    foreach ((array) ($node['itemListElement'] ?? []) as $element) {
                        $sub = is_array($element) ? ($element['item'] ?? $element) : null;
                        if (is_array($sub)) {
                            $item = $this->jsonLdToItem($sub, $url);
                            if ($item !== null) {
                                $out[$item['url']] = $item;
                            }
                        }
                    }
                }
            }
        }

        return array_values($out);
    }

    /** Aplatit les graphes JSON-LD (@graph, tableaux, imbrications). */
    protected function flattenJsonLd(array $data): array
    {
        $nodes = [];

        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $sub) {
                if (is_array($sub)) {
                    $nodes = array_merge($nodes, $this->flattenJsonLd($sub));
                }
            }

            return $nodes;
        }

        if (array_is_list($data)) {
            foreach ($data as $sub) {
                if (is_array($sub)) {
                    $nodes = array_merge($nodes, $this->flattenJsonLd($sub));
                }
            }

            return $nodes;
        }

        $nodes[] = $data;

        return $nodes;
    }

    protected function jsonLdToItem(array $node, string $baseUrl): ?array
    {
        $title = $this->cleanText((string) ($node['headline'] ?? $node['name'] ?? $node['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $link = (string) ($node['url'] ?? $node['@id'] ?? '');
        if ($link === '' && isset($node['mainEntityOfPage']) && is_array($node['mainEntityOfPage'])) {
            $link = (string) ($node['mainEntityOfPage']['@id'] ?? $node['mainEntityOfPage']['url'] ?? '');
        }

        $abs = $this->absoluteUrl($link, $baseUrl);
        if ($abs === '') {
            return null;
        }

        $image = '';
        $img = $node['image'] ?? $node['thumbnailUrl'] ?? null;
        if (is_string($img)) {
            $image = $img;
        } elseif (is_array($img)) {
            $image = (string) ($img['url'] ?? (is_array($img[0] ?? null) ? ($img[0]['url'] ?? '') : ($img[0] ?? '')));
        }

        $desc = $this->cleanText((string) ($node['description'] ?? ''));

        return array_filter([
            'title' => $title,
            'url' => $abs,
            'summary' => $desc,
            'content' => $desc,
            'published_at' => $this->parseDate((string) ($node['datePublished'] ?? $node['dateCreated'] ?? '')),
            'image_url' => $this->absoluteUrl($image, $baseUrl),
            'source' => (string) ($node['publisher']['name'] ?? '') ?: (parse_url($abs, PHP_URL_HOST) ?? ''),
            'scraped_at' => now(),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    // ---------------------------------------------------------------------
    //  2. Payload SPA embarqué
    // ---------------------------------------------------------------------

    /**
     * Les sites Next.js (mode pages) embarquent `__NEXT_DATA__` : on y trouve
     * directement les objets article. Sinon on laisse le scoring heuristique.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseSpaPayload(string $body, string $url): array
    {
        if (! preg_match('/<script\b[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/is', $body, $m)) {
            return [];
        }

        $data = json_decode(trim($m[1]), true);
        if (! is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($this->walkForArticles($data) as $node) {
            $item = $this->jsonLdToItem($node, $url);
            if ($item !== null) {
                $out[$item['url']] = $item;
            }
        }

        return array_values($out);
    }

    /** Recherche récursive de tableaux « à l'article » dans un JSON. */
    protected function walkForArticles(array $data, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        $found = [];

        $title = (string) ($data['title'] ?? $data['headline'] ?? $data['name'] ?? '');
        $link = (string) ($data['url'] ?? $data['href'] ?? $data['slug'] ?? $data['link'] ?? '');
        if ($title !== '' && $link !== '') {
            $found[] = [
                'title' => $title,
                'url' => str_starts_with($link, 'http') || str_starts_with($link, '/') ? $link : '/'.$link,
                'description' => (string) ($data['description'] ?? $data['summary'] ?? $data['excerpt'] ?? ''),
                'datePublished' => (string) ($data['publishedAt'] ?? $data['datePublished'] ?? $data['createdAt'] ?? ''),
                'image' => is_string($data['image'] ?? null) ? $data['image'] : ($data['imageUrl'] ?? ''),
            ];
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = array_merge($found, $this->walkForArticles($value, $depth + 1));
            }
        }

        return $found;
    }

    // ---------------------------------------------------------------------
    //  3. Scoring heuristique des liens
    // ---------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractLinks(Crawler $crawler, string $baseUrl): array
    {
        $baseHost = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?? ''));

        // Bonus : ce que les anciens sélecteurs savaient repérer.
        $boost = [];
        foreach (self::KNOWN_SELECTORS as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) use (&$boost, $baseUrl): void {
                    $abs = $this->absoluteUrl((string) ($node->attr('href') ?? ''), $baseUrl);
                    if ($abs !== '') {
                        $boost[$abs] = true;
                    }
                });
            } catch (\Throwable) {
            }
        }

        $scored = [];

        $crawler->filter('a[href]')->each(function (Crawler $a) use (&$scored, $baseUrl, $baseHost, $boost): void {
            try {
                $href = trim((string) ($a->attr('href') ?? ''));
                if ($href === '' || $href[0] === '#' || preg_match('/^(javascript|mailto|tel|data|about):/i', $href)) {
                    return;
                }

                $abs = $this->absoluteUrl($href, $baseUrl);
                if ($abs === '') {
                    return;
                }

                $host = strtolower((string) (parse_url($abs, PHP_URL_HOST) ?? ''));
                if ($host !== $baseHost && 'www.'.$host !== 'www.'.$baseHost) {
                    return;
                }

                $text = $this->cleanText((string) $a->text());
                $len = mb_strlen($text);
                if ($len < 12 || $len > 300) {
                    return;
                }
                if (in_array(mb_strtolower($text), self::GENERIC_ANCHORS, true)) {
                    return;
                }

                // Hors navigation, menus, pied de page…
                foreach (['nav', 'header', 'footer', 'aside', 'form'] as $tag) {
                    try {
                        if ($a->closest($tag)->count() > 0) {
                            return;
                        }
                    } catch (\Throwable) {
                    }
                }
                foreach ([
                    '[role="navigation"]', '[role="banner"]', '[role="search"]',
                    '[class*="menu"]', '[class*="footer"]', '[class*="sidebar"]',
                    '[class*="pagination"]', '[class*="breadcrumb"]', '[class*="fil-ariane"]',
                    '[class*="social"]', '[class*="share"]', '[class*="comment"]', '[class*="pub"]',
                ] as $selector) {
                    try {
                        if ($a->closest($selector)->count() > 0) {
                            return;
                        }
                    } catch (\Throwable) {
                    }
                }

                $path = (string) (parse_url($abs, PHP_URL_PATH) ?? '');
                if ($path === '' || $path === '/') {
                    return;
                }
                if (preg_match(self::NON_ARTICLE_PATH, $path)) {
                    return;
                }

                $score = 0;

                if (isset($boost[$abs])) {
                    $score += 3;
                }

                try {
                    if ($a->closest('article')->count() > 0) {
                        $score += 2;
                    }
                } catch (\Throwable) {
                }

                foreach (['[class*="teaser"]', '[class*="story"]', '[class*="card"]', '[class*="item"]', '[class*="post"]', '[class*="news"]', '[class*="article"]', 'li'] as $selector) {
                    try {
                        if ($a->closest($selector)->count() > 0) {
                            $score += 2;
                            break;
                        }
                    } catch (\Throwable) {
                    }
                }

                try {
                    if ($a->closest('article, li, div')->filter('time')->count() > 0) {
                        $score += 2;
                    }
                } catch (\Throwable) {
                }

                try {
                    if (preg_match('/^h[1-4]$/i', (string) $a->getNode(0)?->nodeName)) {
                        $score += 2;
                    }
                } catch (\Throwable) {
                }

                $segments = count(array_filter(explode('/', $path)));
                if ($segments >= 2) {
                    $score += 1;
                }
                if (preg_match('~/20\d{2}[-/]\d{2}~', $path)) {
                    $score += 2;
                }
                if (parse_url($abs, PHP_URL_QUERY) !== null) {
                    $score -= 1;
                }
                if ($segments > 6) {
                    $score -= 1;
                }

                if ($score < 3) {
                    return;
                }

                if (! isset($scored[$abs]) || $scored[$abs]['_score'] < $score) {
                    $scored[$abs] = [
                        '_score' => $score,
                        'title' => $text,
                        'url' => $abs,
                    ];
                }
            } catch (\Throwable) {
            }
        });

        if (count($scored) === 0) {
            return [];
        }

        uasort($scored, static fn ($a, $b) => ($b['_score'] <=> $a['_score']));

        $items = [];
        foreach ($scored as $abs => $entry) {
            $items[] = array_filter([
                'title' => $entry['title'],
                'url' => $abs,
                'summary' => '',
                'content' => '',
                'source' => parse_url($abs, PHP_URL_HOST) ?? '',
                'scraped_at' => now(),
            ], static fn ($v) => $v !== null && $v !== '');
        }

        return $items;
    }

    // ---------------------------------------------------------------------
    //  4. Page d'article isolée
    // ---------------------------------------------------------------------

    protected function pageAsItem(string $body, string $url): ?array
    {
        try {
            $crawler = new Crawler($body, $url);
        } catch (\Throwable) {
            return null;
        }

        $meta = static function (Crawler $crawler, string $selector): string {
            try {
                return $crawler->filter($selector)->count() > 0
                    ? trim((string) $crawler->filter($selector)->first()->attr('content'))
                    : '';
            } catch (\Throwable) {
                return '';
            }
        };

        $ogTitle = $meta($crawler, 'meta[property="og:title"]');
        $ogDescription = $meta($crawler, 'meta[property="og:description"]');
        $ogImage = $meta($crawler, 'meta[property="og:image"]');
        $ogSiteName = $meta($crawler, 'meta[property="og:site_name"]');
        $ogType = $meta($crawler, 'meta[property="og:type"]');
        $metaDescription = $meta($crawler, 'meta[name="description"]');

        $pageTitle = '';
        try {
            if ($crawler->filter('title')->count() > 0) {
                $pageTitle = $this->cleanText((string) $crawler->filter('title')->text());
            }
        } catch (\Throwable) {
        }

        $h1 = '';
        try {
            if ($crawler->filter('h1')->count() > 0) {
                $h1 = $this->cleanText((string) $crawler->filter('h1')->first()->text());
            }
        } catch (\Throwable) {
        }

        $articleBody = '';
        foreach (['article', '[role="main"]', 'main', '[itemprop="articleBody"]'] as $selector) {
            try {
                if ($crawler->filter($selector)->count() > 0) {
                    $articleBody = $this->cleanText((string) $crawler->filter($selector)->first()->text());
                    if (mb_strlen($articleBody) > 200) {
                        break;
                    }
                }
            } catch (\Throwable) {
            }
        }

        $summary = $ogDescription !== '' ? $ogDescription : ($metaDescription !== '' ? $metaDescription : '');
        if ($summary === '') {
            try {
                if ($crawler->filter('article p, main p, p')->count() > 0) {
                    $summary = $this->cleanText((string) $crawler->filter('article p, main p, p')->first()->text());
                }
            } catch (\Throwable) {
            }
        }

        $image = $ogImage;
        if ($image === '') {
            try {
                if ($crawler->filter('article img, main img, img')->count() > 0) {
                    $image = (string) ($crawler->filter('article img, main img, img')->first()->attr('src') ?? '');
                }
            } catch (\Throwable) {
            }
        }

        $published = null;
        try {
            if ($crawler->filter('time[datetime]')->count() > 0) {
                $published = $this->parseDate((string) $crawler->filter('time[datetime]')->first()->attr('datetime'));
            }
        } catch (\Throwable) {
        }
        if ($published === null) {
            foreach ($this->parseJsonLd($body, $url) as $jsonItem) {
                if (($jsonItem['published_at'] ?? null) !== null) {
                    $published = $jsonItem['published_at'];
                    break;
                }
            }
        }

        $title = $h1 !== '' ? $h1 : ($ogTitle !== '' ? $ogTitle : $pageTitle);
        if ($title === '') {
            return null;
        }

        // Une page de rubrique ne doit pas devenir un « article ».
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($articleBody === '' && $ogType !== 'article' && preg_match(self::NON_ARTICLE_PATH, $path)) {
            return null;
        }

        return array_filter([
            'title' => $title,
            'url' => $url,
            'summary' => $summary,
            'content' => $articleBody !== '' ? $articleBody : $summary,
            'published_at' => $published,
            'image_url' => $this->absoluteUrl($image, $url),
            'source' => $ogSiteName !== '' ? $ogSiteName : (parse_url($url, PHP_URL_HOST) ?? ''),
            'scraped_at' => now(),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    // =====================================================================
    //  UTILITAIRES
    // =====================================================================

    /** Résout une URL relative (/, //, ./, ../) par rapport à une base. */
    public function absoluteUrl(string $href, string $base): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $href)) {
            return $this->normalizeUrl($href);
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $this->normalizeUrl($scheme.':'.$href);
        }

        if (preg_match('/^(mailto|tel|javascript|data|about):/i', $href) || $href[0] === '#') {
            return '';
        }

        $parts = parse_url($base);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'https';
        $auth = '';
        if (isset($parts['user'])) {
            $auth = $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@';
        }
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        if ($href[0] === '/') {
            $path = $href;
        } else {
            $basePath = (string) ($parts['path'] ?? '/');
            $dir = substr($basePath, 0, (int) strrpos($basePath, '/')).'/';
            $path = $dir.$href;
        }

        return $this->normalizeUrl($scheme.'://'.$auth.$parts['host'].$port.$this->normalizePath($path));
    }

    /** Résout les segments « . » et « .. » d'un chemin. */
    protected function normalizePath(string $path): string
    {
        $fragment = '';
        if (($pos = strpos($path, '#')) !== false) {
            $fragment = substr($path, $pos);
            $path = substr($path, 0, $pos);
        }

        $query = '';
        if (($pos = strpos($path, '?')) !== false) {
            $query = substr($path, $pos);
            $path = substr($path, 0, $pos);
        }

        $out = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);

                continue;
            }
            $out[] = $segment;
        }

        $clean = '/'.implode('/', $out);
        if (str_ends_with($path, '/') && $clean !== '/') {
            $clean .= '/';
        }

        return $clean.$query.$fragment;
    }

    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);

        // Retire uniquement le fragment : ne jamais modifier l'hôte,
        // certains sites servent un contenu différent sur l'apex et sur www.
        if (($pos = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $pos);
        }

        return $url;
    }

    protected function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$parts['host'].$port;
    }

    protected function attrOf(string $tag, string $name): ?string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'=["\']([^"\']*)["\']/i', $tag, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    protected function namespacedText(\SimpleXMLElement $element, string $namespace, string $name): string
    {
        try {
            $children = $element->children($namespace);
            if (isset($children->{$name})) {
                return (string) $children->{$name};
            }
        } catch (\Throwable) {
        }

        return '';
    }

    /** Image d'une entrée de flux : enclosure, media:*, itunes, puis <img>. */
    protected function imageFromEntry(\SimpleXMLElement $entry): string
    {
        try {
            if (isset($entry->enclosure['url'])) {
                $url = trim((string) $entry->enclosure['url']);
                if ($url !== '') {
                    return $url;
                }
            }
        } catch (\Throwable) {
        }

        $probes = [
            ['http://search.yahoo.com/mrss/', './/media:thumbnail/@url'],
            ['http://search.yahoo.com/mrss/', './/media:content/@url'],
            ['http://www.itunes.com/dtds/podcast-1.0.dtd', './/itunes:image/@href'],
        ];

        foreach ($probes as [$namespace, $xpath]) {
            try {
                $entry->registerXPathNamespace('probe', $namespace);
                $found = $entry->xpath($xpath);
                if (is_array($found) && count($found) > 0) {
                    $url = trim((string) $found[0]);
                    if ($url !== '') {
                        return $url;
                    }
                }
            } catch (\Throwable) {
            }
        }

        // Dernier recours : la première image du contenu HTML.
        foreach (['description', 'content', 'encoded'] as $field) {
            try {
                $html = (string) ($entry->{$field} ?? '');
                if ($html !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                    return trim($m[1]);
                }
            } catch (\Throwable) {
            }
        }

        return '';
    }

    protected function cleanText(?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        $txt = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = strip_tags($txt);
        $txt = preg_replace('/\s+/u', ' ', $txt) ?? $txt;

        return trim($txt);
    }

    protected function parseDate(?string $raw): ?Carbon
    {
        $raw = $raw !== null ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    protected function limit(array $items): array
    {
        return array_slice($items, 0, self::MAX_ITEMS);
    }
}
