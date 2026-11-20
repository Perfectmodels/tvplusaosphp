<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Rules\UrlPublique;
use App\Services\MediaValidation;
use App\Services\SecurityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\MediaLibrary\MediaCollections\File;
use Tests\TestCase;

/**
 * Sécurité applicative : en-têtes HTTP, API authentifiée, anti-SSRF.
 */
class SecuriteApplicationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================
    //  EN-TÊTES DE SÉCURITÉ
    // =========================================================

    public function test_les_reponses_portent_les_entetes_de_securite(): void
    {
        $response = $this->get('/login')->assertOk();

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_la_protection_clickjacking_est_active(): void
    {
        $csp = (string) $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_la_csp_autorise_le_worker_blob_du_scanner_qr(): void
    {
        $csp = (string) $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString(
            "worker-src 'self' blob:",
            $csp,
            'Sans worker-src blob:, le décodeur qr-scanner ne peut pas démarrer.',
        );
    }

    // =========================================================
    //  API
    // =========================================================

    public function test_l_api_v1_refuse_les_requetes_non_authentifiees(): void
    {
        $this->postJson('/api/v1/veille/scrape-url', ['url' => 'https://exemple.test/feed'])
            ->assertUnauthorized();
    }

    public function test_la_connexion_est_limitee_en_debit(): void
    {
        // 6 tentatives échouées → la suivante est bloquée par le throttle (5 max).
        for ($i = 0; $i < 6; $i++) {
            $this->from('/login')->post('/login', [
                'email' => 'brute@force.test',
                'password' => 'mauvais',
            ]);
        }

        $this->from('/login')
            ->post('/login', [
                'email' => 'brute@force.test',
                'password' => 'mauvais',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }

    // =========================================================
    //  ANTI-SSRF
    // =========================================================

    /** @return array<int, string> */
    private function urlsInterdites(): array
    {
        return [
            'http://localhost/admin',
            'http://127.0.0.1/secret',
            'http://10.0.0.5/internal',
            'http://192.168.1.1/router',
            'http://172.16.0.1/',
            'http://169.254.169.254/latest/meta-data/',   // métadonnées cloud
            'http://[::1]/',
            'file:///etc/passwd',
            'ftp://exemple.test/fichier',
            'gopher://exemple.test/',
            'http://metadata.google.internal/',
            'http://mon-service.local/',
            'http://0x7f000001/',
        ];
    }

    public function test_les_urls_internes_sont_refusees(): void
    {
        foreach ($this->urlsInterdites() as $url) {
            $validator = Validator::make(
                ['url' => $url],
                ['url' => ['required', 'url', new UrlPublique]]
            );

            $this->assertTrue(
                $validator->fails(),
                "L'URL interne « {$url} » aurait dû être refusée."
            );
        }
    }

    public function test_les_urls_publiques_sont_acceptees(): void
    {
        foreach ([
            'https://www.rfi.fr/fr/afrique/rss',
            'https://ecomatin.net/feed.xml',
            'http://www.agenceecofin.com/feed',
        ] as $url) {
            $validator = Validator::make(
                ['url' => $url],
                ['url' => ['required', 'url', new UrlPublique]]
            );

            $this->assertTrue(
                $validator->passes(),
                "L'URL publique « {$url} » aurait dû être acceptée."
            );
        }
    }

    // =========================================================
    //  VALIDATION DES FICHIERS (Media Library)
    // =========================================================

    private function fichier(string $nom, int $octets, string $mime): File
    {
        return new File($nom, $octets, $mime);
    }

    public function test_les_images_valides_sont_acceptees(): void
    {
        foreach (MediaValidation::TYPES_IMAGE as $mime) {
            $this->assertTrue(
                MediaValidation::imagePortrait($this->fichier('photo.jpg', 1024, $mime)),
                "Le type {$mime} aurait dû être accepté."
            );
        }
    }

    public function test_les_fichiers_dangereux_sont_refuses(): void
    {
        $dangereux = [
            $this->fichier('malware.exe', 1024, 'application/x-msdownload'),
            $this->fichier('script.php', 1024, 'application/x-php'),
            $this->fichier('page.html', 1024, 'text/html'),
            $this->fichier('vector.svg', 1024, 'image/svg+xml'),
            $this->fichier('vector.svg', 1024, 'image/jpeg'),   // extension SVG
        ];

        foreach ($dangereux as $fichier) {
            $this->assertFalse(
                MediaValidation::imagePortrait($fichier),
                "Le fichier {$fichier->name} ({$fichier->mimeType}) aurait dû être refusé."
            );
            $this->assertFalse(MediaValidation::document($fichier));
        }
    }

    public function test_les_fichiers_volumineux_sont_refuses(): void
    {
        $tropLourd = $this->fichier('photo.jpg', MediaValidation::MAX_IMAGE_OCTETS + 1, 'image/jpeg');

        $this->assertFalse(MediaValidation::imagePortrait($tropLourd));

        $documentTropLourd = $this->fichier('rapport.pdf', MediaValidation::MAX_DOCUMENT_OCTETS + 1, 'application/pdf');
        $this->assertFalse(MediaValidation::document($documentTropLourd));
    }

    // =========================================================
    //  VERROUILLAGE DE COMPTE
    // =========================================================

    public function test_une_serie_d_echecs_verrouille_le_compte(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->from('/login')->post('/login', [
                'email' => 'verrouille@tvplusos.tv',
                'password' => 'mauvais',
            ]);
        }

        $this->from('/login')
            ->post('/login', [
                'email' => 'verrouille@tvplusos.tv',
                'password' => 'mauvais',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }

    // =========================================================
    //  JOURNALISATION
    // =========================================================

    public function test_les_connexions_echouees_sont_journalisees(): void
    {
        $fichiersAvant = glob(storage_path('logs/security*.log')) ?: [];

        $this->from('/login')->post('/login', [
            'email' => 'tracee@tvplusos.tv',
            'password' => 'mauvais',
        ]);

        $fichiersApres = glob(storage_path('logs/security*.log')) ?: [];
        $this->assertNotEmpty($fichiersApres, 'Le journal de sécurité n\'a pas été créé.');

        $contenu = '';
        foreach (array_unique(array_merge($fichiersAvant, $fichiersApres)) as $fichier) {
            $contenu .= (string) file_get_contents($fichier);
        }

        $this->assertStringContainsString('tracee@tvplusos.tv', $contenu);
        $this->assertStringContainsString(SecurityLogger::CONNEXION_ECHOUEE, $contenu);
    }

    // =========================================================
    //  AUDIT DE MISE EN PRODUCTION
    // =========================================================

    public function test_la_commande_d_audit_fonctionne(): void
    {
        $this->artisan('app:audit-deploiement')
            ->expectsOutputToContain('APP_ENV = production')
            ->expectsOutputToContain('APP_DEBUG = false')
            ->expectsOutputToContain('En-têtes de sécurité')
            ->expectsOutputToContain('Protection brute-force');
    }

    public function test_l_audit_signale_les_bloquants_de_l_environnement_courant(): void
    {
        // En test : APP_ENV=testing → l'audit doit remonter au moins une erreur.
        $this->artisan('app:audit-deploiement')->assertExitCode(1);
    }

    public function test_l_audit_est_exportable_en_json(): void
    {
        $this->artisan('app:audit-deploiement --json')
            ->expectsOutputToContain('"libelle"');
    }
}
