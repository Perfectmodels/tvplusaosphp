<?php

use App\Services\VeilleAutomationService;
use App\Models\Conducteur;
use App\Services\ConducteurAntenneService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Surveillance serveur des directs.
 * Même si tous les navigateurs sont fermés, chaque minute l'horloge de
 * l'émission est réconciliée depuis les secondes réellement écoulées.
 * Utilise le cron Laravel schedule:run déjà prévu pour la veille.
 */
Schedule::call(static function (): void {
    Conducteur::query()
        ->where('type', 'emission')
        ->whereNotNull('antenne_started_at')
        ->whereNull('antenne_ended_at')
        ->chunkById(50, static function ($conducteurs): void {
            foreach ($conducteurs as $conducteur) {
                app(ConducteurAntenneService::class)->reconcilier($conducteur);
            }
        });
})->name('conducteurs.antenne.reconcile')->everyMinute()->withoutOverlapping(5);

/*
 |--------------------------------------------------------------------------
 | Veille automatique
 |--------------------------------------------------------------------------
 |
 | `veille:scrape` exécute ScrapeVeilleSourcesJob en SYNCHRONE (dispatchSync) :
 | aucun worker de queue n'est nécessaire — indispensable en hébergement
 | mutualisé, où aucun processus permanent ne peut tourner.
 |
 | `withoutOverlapping(60)` : si un passage dure plus longtemps que prévu
 | (premier import volumineux), les ticks suivants sont ignorés au lieu de
 | lancer un scraping concurrent. Le verrou expire au bout de 60 minutes.
 |
 | Déclenchement : `php artisan schedule:run` toutes les minutes via le cron
 | du panneau d'hébergement (voir DEPLOIEMENT.md §5).
 */
Schedule::call(fn () => Cache::put(VeilleAutomationService::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDays(2)))
    ->name('veille.heartbeat')->everyMinute();

Schedule::command('veille:scrape')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/veille-scheduler-'.now()->format('Y-m-d').'.log'))
    ->onFailure(function () {
        Log::channel('tvplus_veille')->error(
            '[Veille] Le scraping planifié a échoué — voir storage/logs/veille-scheduler.log'
        );
    });

Schedule::command('veille:gabon-analyse')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/veille-gabon-agent.log'));
