<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SourceVeille;
use App\Services\VeilleAutomationService;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;

/**
 * État de la veille automatique.
 *
 *   php artisan veille:statut
 *   php artisan veille:statut --json
 *
 * Utile en hébergement mutualisé (pas de tableau de bord superviseur) pour
 * vérifier que la planification tourne vraiment. Code de sortie 1 si la veille
 * semble arrêtée (jamais passée ou dernier passage > 24 h).
 */
class VeilleStatut extends Command
{
    protected $signature = 'veille:statut {--json : Sortie JSON}';

    protected $description = 'Affiche l\'état de la veille automatique (dernier passage, sources dues, planification)';

    public function handle(VeilleAutomationService $automation): int
    {
        $health = $automation->snapshot();
        $sources = SourceVeille::query()
            ->withCount('subjects')
            ->orderBy('id')
            ->get();

        $actives = $sources->where('is_active', true);
        $dues = $actives->filter(fn (SourceVeille $s) => $s->estDue())->count();
        $dernier = SourceVeille::query()
            ->whereNotNull('last_scraped_at')
            ->orderByDesc('last_scraped_at')
            ->first()
            ?->last_scraped_at;
        $ancien = $dernier
            ? (int) abs(now()->diffInMinutes($dernier))
            : null;

        $enRetard = $actives->filter(fn (SourceVeille $s) => $s->retardMinutes() > 0)->count();

        $planification = $this->planification();
        $arret = ! $health['scheduler_alive'] || $dernier === null || ($ancien !== null && $ancien > 24 * 60);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'sources_total' => $sources->count(),
                'sources_actives' => $actives->count(),
                'sources_dues' => $dues,
                'sources_en_retard' => $enRetard,
                'dernier_passage' => $dernier?->toIso8601String(),
                'minutes_depuis_passage' => $ancien,
                'planification' => $planification,
                'veille_arretee' => $arret,
                'planificateur_actif' => $health['scheduler_alive'],
                'sources_en_erreur' => $health['sources_errors'],
                'sources' => $sources->map(fn (SourceVeille $s) => [
                    'id' => $s->id,
                    'nom' => $s->name,
                    'active' => (bool) $s->is_active,
                    'statut' => $s->status,
                    'frequence' => $s->frequency,
                    'dernier_passage' => $s->last_scraped_at?->toIso8601String(),
                    'prochain_du' => $s->prochainPassageAt()?->toIso8601String(),
                    'due' => $s->estDue(),
                    'sujets' => $s->subjects_count,
                    'flux' => $s->rss_feed,
                ])->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $arret ? self::FAILURE : self::SUCCESS;
        }

        $this->info('🛰️  Veille automatique');
        $this->newLine();

        if ($sources->isEmpty()) {
            $this->warn('Aucune source de veille enregistrée.');
        } else {
            $this->table(
                ['#', 'Source', 'Active', 'Statut', 'Dernier passage', 'Prochain dû', 'Retard', 'Sujets'],
                $sources->map(fn (SourceVeille $s) => [
                    $s->id,
                    mb_strimwidth($s->name, 0, 34, '…'),
                    $s->is_active ? '✅' : '—',
                    $s->status ?? '—',
                    $s->last_scraped_at?->format('d/m H:i') ?? 'jamais',
                    $s->prochainPassageAt()?->format('d/m H:i') ?? 'maintenant',
                    $s->retardMinutes() > 0 ? $s->retardMinutes().' min' : '—',
                    $s->subjects_count,
                ])->all()
            );
        }

        $this->line('Sources actives : <info>'.$actives->count().'</info> · dues maintenant : <info>'.$dues.'</info>');
        $this->line('Dernier passage : <info>'.($dernier?->format('d/m/Y H:i') ?? 'jamais').'</info>'
            .($ancien !== null ? ' (il y a '.$ancien.' min)' : ''));

        if ($planification) {
            $this->line('Planification : <info>'.$planification['expression'].'</info> · prochain réveil : <info>'
                .($planification['prochain'] ?? '—').'</info>');
        } else {
            $this->warn('Aucune tâche « veille:scrape » planifiée dans routes/console.php.');
        }

        $this->newLine();

        if ($arret) {
            $this->error('⚠️ La veille semble ARRÊTÉE (aucun passage récent).');
            $this->line('Vérifier que le cron appelle bien <comment>php artisan schedule:run</comment> chaque minute (DEPLOIEMENT.md §5).');

            return self::FAILURE;
        }

        if ($health['sources_errors'] > 0) {
            $this->warn($health['sources_errors'].' source(s) en erreur — nouvelle tentative automatique.');
        } elseif ($enRetard > 0) {
            $this->warn("{$enRetard} source(s) en retard — le prochain tick du planificateur les traitera.");
        } else {
            $this->info('✅ Veille opérationnelle.');
        }

        return self::SUCCESS;
    }

    /**
     * Détail de la tâche planifiée (expression cron + prochaine exécution).
     *
     * @return array{expression:string, prochain:?string}|null
     */
    protected function planification(): ?array
    {
        try {
            $evenement = collect(app(Schedule::class)->events())
                ->first(fn ($e) => str_contains((string) $e->command, 'veille:scrape'));

            if (! $evenement) {
                return null;
            }

            return [
                'expression' => $evenement->getExpression(),
                'prochain' => $evenement->nextRunDate()->format('d/m/Y H:i'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
