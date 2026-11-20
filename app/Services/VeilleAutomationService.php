<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SourceVeille;
use App\Models\VeilleSubject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class VeilleAutomationService
{
    public const HEARTBEAT_KEY = 'veille:scheduler:heartbeat';

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $sources = SourceVeille::query()->orderBy('id')->get();
        $active = $sources->where('is_active', true);
        $heartbeat = Cache::get(self::HEARTBEAT_KEY);
        $running = $heartbeat && Carbon::parse($heartbeat)->greaterThanOrEqualTo(now()->subMinutes(3));
        $errors = $active->where('status', 'error')->count();
        $state = match (true) {
            $sources->isEmpty() => 'no_sources',
            $active->isEmpty() => 'paused',
            ! $running => 'stopped',
            $errors > 0 => 'attention',
            default => 'running',
        };
        $messages = [
            'no_sources' => 'Ajoutez une source pour démarrer la collecte automatique.',
            'paused' => 'Toutes les sources sont désactivées. Les articles déjà collectés sont conservés.',
            'stopped' => 'Le planificateur ne répond pas. La collecte automatique attend son redémarrage.',
            'attention' => 'Collecte automatique active. Certaines sources seront réessayées après une erreur.',
            'running' => 'Collecte automatique active. Les nouveaux articles apparaissent sans recharger cette page.',
        ];
        $subjectStats = VeilleSubject::query()->selectRaw('COUNT(*) as total, MAX(id) as latest_id, MAX(updated_at) as updated, SUM(CASE WHEN watch_analyzed_at IS NOT NULL THEN 1 ELSE 0 END) as analyzed')->first();
        $statuses = VeilleSubject::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->all();
        $revision = hash('sha256', json_encode([
            $subjectStats?->getAttributes(), $statuses,
            $sources->map(fn (SourceVeille $source) => [$source->id, $source->updated_at?->toIso8601String(), $source->status, $source->last_items_count, $source->last_new_items_count])->all(),
        ]));
        $lastSuccess = $sources->whereNotNull('last_succeeded_at')->max('last_succeeded_at');
        $lastAttempt = $sources->whereNotNull('last_scraped_at')->max('last_scraped_at');
        $next = $active->map(fn (SourceVeille $source) => $source->prochainPassageAt() ?? now())->min();

        return [
            'state' => $state, 'message' => $messages[$state], 'revision' => $revision,
            'scheduler_alive' => (bool) $running, 'heartbeat_at' => $heartbeat,
            'sources_total' => $sources->count(), 'sources_active' => $active->count(),
            'sources_due' => $active->filter(fn (SourceVeille $source) => $source->estDue())->count(),
            'sources_errors' => $errors, 'articles_total' => (int) ($subjectStats?->total ?? 0),
            'last_success_at' => $lastSuccess?->toIso8601String(), 'last_attempt_at' => $lastAttempt?->toIso8601String(),
            'next_collection_at' => $next?->toIso8601String(),
            'ai_available' => filled(config('services.groq.key')),
            'gabon_articles' => VeilleSubject::query()->gabon()->count(),
            'gabon_analyzed' => VeilleSubject::query()->gabon()->whereNotNull('watch_analyzed_at')->count(),
            'gabon_ai_enabled' => (bool) config('veille.gabon_ai_enabled'),
            'gabon_ai_used' => (int) Cache::get(GabonVeilleAgent::budgetKey(), 0),
            'gabon_ai_limit' => (int) config('veille.gabon_ai_daily_limit'),
        ];
    }
}
