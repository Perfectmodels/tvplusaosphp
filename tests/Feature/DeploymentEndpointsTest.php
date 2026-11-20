<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\VeilleAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeploymentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostics_et_collecte_sont_refuses_sans_secret(): void
    {
        config(['services.deployment.secret' => 'deployment-test-secret']);
        $this->getJson(route('deployment.readiness'))->assertUnauthorized();
        $this->withToken('incorrect')->getJson(route('deployment.veille'))->assertUnauthorized();
        $this->assertNull(Cache::get(VeilleAutomationService::HEARTBEAT_KEY));
    }

    public function test_une_configuration_sans_secret_ne_permet_pas_un_acces_vide(): void
    {
        config(['services.deployment.secret' => null]);
        $this->getJson(route('deployment.readiness'))->assertUnauthorized();
        $this->getJson(route('deployment.veille'))->assertUnauthorized();
    }

    public function test_diagnostic_verifie_la_base_et_les_migrations_sans_modifier_les_donnees(): void
    {
        config(['services.deployment.secret' => 'deployment-test-secret']);
        $this->withToken('deployment-test-secret')->getJson(route('deployment.readiness'))
            ->assertOk()->assertExactJson(['ready' => true, 'database' => 'connected', 'migrations_pending' => 0]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_diagnostic_signale_les_migrations_en_attente(): void
    {
        config(['services.deployment.secret' => 'deployment-test-secret']);
        DB::table('migrations')->where('migration', '2026_11_13_221327_create_employee_number_sequences_table')->delete();
        $this->withToken('deployment-test-secret')->getJson(route('deployment.readiness'))
            ->assertStatus(503)->assertJsonPath('migrations_pending', 1)->assertJsonPath('ready', false);
    }

    public function test_collecte_authentifiee_sans_sources_rafraichit_le_heartbeat_sans_creer_d_articles(): void
    {
        Http::preventStrayRequests();
        config(['services.deployment.secret' => 'deployment-test-secret']);
        $this->withToken('deployment-test-secret')->getJson(route('deployment.veille'))
            ->assertOk()->assertJsonPath('status', 'completed');
        $this->assertNotNull(Cache::get(VeilleAutomationService::HEARTBEAT_KEY));
        $this->assertDatabaseCount('veille_subjects', 0);
        Http::assertNothingSent();
    }

    public function test_une_collecte_deja_active_n_est_pas_lancee_une_seconde_fois(): void
    {
        config(['services.deployment.secret' => 'deployment-test-secret']);
        $lock = Cache::lock('veille.deployment-cycle', 300);
        $lock->get();
        try {
            $this->withToken('deployment-test-secret')->getJson(route('deployment.veille'))
                ->assertStatus(202)->assertJsonPath('status', 'already_running');
        } finally {
            $lock->release();
        }
        $this->assertNull(Cache::get(VeilleAutomationService::HEARTBEAT_KEY));
    }

    public function test_proxy_configurable_preserve_le_schema_https(): void
    {
        TrustProxies::at('*');
        try {
            $request = Request::create('http://localhost/up');
            $request->server->set('REMOTE_ADDR', '10.0.0.1');
            $request->headers->set('X-Forwarded-Proto', 'https');
            (new TrustProxies)->handle($request, function ($request) {
                $this->assertTrue($request->isSecure());

                return response('ok');
            });
        } finally {
            TrustProxies::flushState();
            Request::setTrustedProxies([], -1);
        }
    }
}
