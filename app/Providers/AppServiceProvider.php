<?php

namespace App\Providers;

use App\Contracts\AiProviderInterface;
use App\Models\AlerteInfo;
use App\Models\Conducteur;
use App\Models\DigitalPublication;
use App\Models\EditorialStory;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use App\Observers\SequenceObserver;
use App\Observers\WorkspaceAccountObserver;
use App\Observers\WorkspaceConducteurObserver;
use App\Observers\WorkspacePublicationObserver;
use App\Observers\WorkspaceSequenceObserver;
use App\Observers\WorkspaceStoryObserver;
use App\Services\Ai\GroqProvider;
use App\Services\SharedWorkspaceService;
use App\View\Composers\WorkspaceHeaderComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, function ($app) {
            $provider = env('AI_PROVIDER', config('services.default_ai_provider', 'groq'));

            return match ($provider) {
                'groq' => new GroqProvider,
                default => new GroqProvider,
            };
        });
    }

    public function boot(): void
    {
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(10)
            ->by('assistant:'.$request->user()->getAuthIdentifier())
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Vous avez envoyé plusieurs questions. Réessayez dans une minute.',
            ], 429, $headers)));

        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        User::observe(WorkspaceAccountObserver::class);
        Journaliste::observe(WorkspaceAccountObserver::class);
        Conducteur::observe(WorkspaceConducteurObserver::class);
        Sequence::observe(SequenceObserver::class);
        Sequence::observe(WorkspaceSequenceObserver::class);
        EditorialStory::observe(WorkspaceStoryObserver::class);
        DigitalPublication::observe(WorkspacePublicationObserver::class);
        View::composer(['dashboard', 'dashboard_role'], function ($view): void {
            $view->with('sharedWorkspace', auth()->check() ? app(SharedWorkspaceService::class)->dashboard(auth()->user(), $view->getData()['activeDashboardRole'] ?? null) : null);
        });
        View::composer('_partials.page-header', WorkspaceHeaderComposer::class);
        View::composer('layouts.app', function ($view): void {
            $alertes = collect();
            if (auth()->check()) {
                try {
                    $alertes = AlerteInfo::query()->actives()->limit(3)->get();
                } catch (\Throwable) {
                    // La table peut ne pas encore exister lors d'un déploiement
                    // progressif : le site reste utilisable sans alertes.
                    $alertes = collect();
                }
            }
            $view->with('alertesGlobalesActives', $alertes);
        });

        // 🔒 En production : HTTPS forcé, cookies de session stricts.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            config([
                'session.secure' => true,
                'session.http_only' => true,
                'session.same_site' => 'lax',
            ]);
        }
    }
}
