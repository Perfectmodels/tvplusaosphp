<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeVeilleSourcesJob;
use App\Services\GabonVeilleAgent;
use App\Services\VeilleAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeploymentController extends Controller
{
    public function readiness(Request $request): JsonResponse
    {
        $this->authorizeDeployment($request);

        try {
            DB::connection()->getPdo();
            if (! Schema::hasTable('migrations')) {
                return response()->json(['ready' => false, 'database' => 'connected', 'migrations' => 'required'], 503);
            }
            $appliquees = DB::table('migrations')->pluck('migration')->all();
            $requises = array_map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME), glob(database_path('migrations/*.php')) ?: []);
            $pending = count(array_diff($requises, $appliquees));

            return response()->json(['ready' => $pending === 0, 'database' => 'connected', 'migrations_pending' => $pending], $pending === 0 ? 200 : 503);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['ready' => false, 'database' => 'unreachable'], 503);
        }
    }

    public function veille(Request $request, GabonVeilleAgent $agent): JsonResponse
    {
        $this->authorizeDeployment($request);
        $result = Cache::lock('veille.deployment-cycle', 300)->get(function () use ($agent): bool {
            Cache::put(VeilleAutomationService::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDays(2));
            ScrapeVeilleSourcesJob::dispatchSync();
            $agent->run(1);

            return true;
        });

        return response()->json(['status' => $result ? 'completed' : 'already_running'], $result ? 200 : 202);
    }

    private function authorizeDeployment(Request $request): void
    {
        $secret = config('services.deployment.secret');
        abort_unless(is_string($secret) && $secret !== '' && is_string($request->bearerToken()) && hash_equals($secret, $request->bearerToken()), 401);
    }
}
