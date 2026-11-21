<?php

namespace App\Services;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Models\ActivityLog;
use App\Models\Conducteur;
use App\Models\DigitalPublication;
use App\Models\Emission;
use App\Models\Sequence;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AdminDashboardAnalyticsService
{
    /**
     * Aggregations are deliberately read-only and computed from persisted records.
     * 'published' is counted only when a publication has a published_at date.
     *
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $periodStart = $from->toDateString();
        $periodEnd = $to->toDateString();
        $publicationStart = $from->toDateTimeString();
        $publicationEnd = $to->toDateTimeString();

        $conducteurs = $this->conducteurs($filters);
        $conducteurs->whereBetween('date_edition', [$periodStart, $periodEnd]);

        $publications = $this->publications($filters);
        $published = (clone $publications)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$publicationStart, $publicationEnd]);

        $sequences = Sequence::query()
            ->where('masque', false)
            ->whereIn('conducteur_id', (clone $conducteurs)->select('conducteurs.id'));
        $retards = (clone $sequences)
            ->where('heure_livraison_prevue', '<', now())
            ->where('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine);

        $dayCount = (int) $from->diffInDays($to) + 1;
        $previousEnd = $from->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays($dayCount - 1)->startOfDay();
        $previousConducteurs = $this->conducteurs($filters)
            ->whereBetween('date_edition', [$previousStart->toDateString(), $previousEnd->toDateString()]);
        $previousPublications = $this->publications($filters)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$previousStart->toDateTimeString(), $previousEnd->toDateTimeString()]);

        $productionsCount = (clone $conducteurs)->count();
        $publishedCount = (clone $published)->count();
        $retardsCount = (clone $retards)->count();
        $veilleEnAttente = VeilleSubject::query()->whereIn('status', ['draft', 'pending'])->count();
        $actifs = User::query()->where('is_active', true)->count();

        $cards = [
            ['label' => 'Conducteurs', 'value' => $productionsCount, 'icon' => 'view_list', 'href' => route('conducteurs.emission.index'), 'subtitle' => 'Dans la période', 'delta' => $productionsCount - (clone $previousConducteurs)->count()],
            ['label' => 'Séquences', 'value' => (clone $sequences)->count(), 'icon' => 'movie', 'href' => route('conducteurs.redaction.index'), 'subtitle' => 'Non masquées · période', 'delta' => null],
            ['label' => 'Publications', 'value' => $publishedCount, 'icon' => 'public', 'href' => route('digital.index'), 'subtitle' => 'Publiées · période', 'delta' => $publishedCount - (clone $previousPublications)->count()],
            ['label' => 'Retards', 'value' => $retardsCount, 'icon' => 'schedule', 'href' => route('conducteurs.redaction.index'), 'subtitle' => 'Livraisons en dépassement', 'delta' => null],
            ['label' => 'Veille à examiner', 'value' => $veilleEnAttente, 'icon' => 'travel_explore', 'href' => route('veille.sujets.index'), 'subtitle' => 'Stock total · non filtré', 'delta' => null],
            ['label' => 'Comptes actifs', 'value' => $actifs, 'icon' => 'groups', 'href' => route('users.index'), 'subtitle' => 'Total · non filtré', 'delta' => null],
        ];

        // For a single date, show a clearly labelled 7-day rolling trend, not a misleading one-point chart.
        $trendStart = $from->isSameDay($to) ? $from->copy()->subDays(6) : $from->copy();
        if ($trendStart->diffInDays($to) > 13) {
            $trendStart = $to->copy()->subDays(13)->startOfDay();
        }
        $trendConducteurs = $this->conducteurs($filters)
            ->whereBetween('date_edition', [$trendStart->toDateString(), $periodEnd])
            ->selectRaw('DATE(date_edition) as chart_day, COUNT(*) as total')
            ->groupByRaw('DATE(date_edition)')
            ->pluck('total', 'chart_day')
            ->all();
        $trendPublications = $this->publications($filters)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$trendStart->toDateTimeString(), $publicationEnd])
            ->selectRaw('DATE(published_at) as chart_day, COUNT(*) as total')
            ->groupByRaw('DATE(published_at)')
            ->pluck('total', 'chart_day')
            ->all();

        $trend = [];
        for ($date = $trendStart->copy(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();
            $trend[] = [
                'date' => $key,
                'label' => $date->format('d/m'),
                'production' => (int) ($trendConducteurs[$key] ?? 0),
                'published' => (int) ($trendPublications[$key] ?? 0),
            ];
        }

        $emissionCounts = (clone $conducteurs)
            ->whereNotNull('emission_id')
            ->selectRaw('emission_id, COUNT(*) as total')
            ->groupBy('emission_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get();
        $emissionNames = Emission::query()
            ->whereIn('id', $emissionCounts->pluck('emission_id'))
            ->pluck('nom', 'id');
        $emissions = $emissionCounts->map(fn ($row) => [
            'name' => $emissionNames[$row->emission_id] ?? 'Émission #'.$row->emission_id,
            'total' => (int) $row->total,
        ])->all();

        // Status breakdown is based on creation date, unlike "published in period" KPI.
        $statusCounts = (clone $publications)
            ->whereBetween('created_at', [$publicationStart, $publicationEnd])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $statusLabels = config('workspaces.digital_statuses', []);
        $palette = ['#1e40af', '#f59e0b', '#ef4444', '#0891b2', '#8b5cf6', '#16a34a'];
        $statuts = [];
        foreach ($statusLabels as $status => $label) {
            $statuts[] = [
                'label' => $label,
                'total' => (int) ($statusCounts[$status] ?? 0),
                'color' => $palette[count($statuts) % count($palette)],
            ];
        }

        $recentPublications = (clone $published)->with(['auteur', 'emission'])
            ->orderByDesc('published_at')->limit(6)->get()
            ->map(fn (DigitalPublication $publication) => [
                'title' => $publication->title,
                'author' => $publication->auteur?->name ?? 'Auteur non renseigné',
                'platform' => config('workspaces.digital_platforms.'.$publication->platform, $publication->platform ?: 'Plateforme non définie'),
                'date' => $publication->published_at?->format('d/m/Y H:i'),
            ])->all();

        $activity = ActivityLog::query()
            ->whereBetween('created_at', [$publicationStart, $publicationEnd])
            ->whereNotNull('actor_id')
            ->whereIn('http_method', ['POST', 'PUT', 'PATCH', 'DELETE'])
            ->whereBetween('http_status', [200, 399])
            ->when(filled($filters['department'] ?? null), fn ($q) => $q->whereHas('actor', fn ($a) => $a->where('department', $filters['department'])))
            ->when(filled($filters['q'] ?? null), fn ($q) => $q->where('actor_name', 'like', '%'.trim($filters['q']).'%'))
            ->orderByDesc('created_at')
            ->limit(7)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'author' => $log->actor_name ?: 'Utilisateur',
                'event' => $log->event ?: ($log->route_name ?: 'Action enregistrée'),
                'date' => $log->created_at?->format('d/m H:i'),
            ])->all();

        $trendMax = max([1, ...array_map(fn ($d) => max($d['production'], $d['published']), $trend)]);
        $emissionMax = max([1, ...array_column($emissions, 'total')]);

        return [
            'cards' => $cards,
            'trend' => $trend,
            'trendMax' => $trendMax,
            'trendLabel' => $from->isSameDay($to) ? '7 derniers jours (tendance)' : ($trendStart->gt($from) ? '14 derniers jours de la sélection' : 'Période sélectionnée'),
            'emissions' => $emissions,
            'emissionMax' => $emissionMax,
            'statuts' => $statuts,
            'statutsTotal' => array_sum(array_column($statuts, 'total')),
            'recentPublications' => $recentPublications,
            'activity' => $activity,
            'sourcesErrors' => SourceVeille::query()->where('is_active', true)->where('status', 'error')->count(),
            'periodLabel' => $from->format('d/m/Y').' — '.$to->format('d/m/Y'),
            'periodComparison' => $previousStart->format('d/m').' — '.$previousEnd->format('d/m'),
            'hasDepartmentFilter' => filled($filters['department'] ?? null),
        ];
    }

    private function conducteurs(array $filters): Builder
    {
        return Conducteur::query()
            ->when(filled($filters['emission_id'] ?? null), fn ($q) => $q->where('emission_id', (int) $filters['emission_id']))
            ->when(filled($filters['q'] ?? null), fn ($q) => $q->where(fn ($search) => $search
                ->where('nom', 'like', '%'.trim($filters['q']).'%')
                ->orWhereHas('emission', fn ($emission) => $emission->where('nom', 'like', '%'.trim($filters['q']).'%'))))
            ->when(filled($filters['department'] ?? null), fn ($q) => $q->where(fn ($scope) => $scope
                ->whereHas('owner', fn ($user) => $user->where('department', $filters['department']))
                ->orWhereHas('sequences.journalisteResponsable.user', fn ($user) => $user->where('department', $filters['department']))));
    }

    private function publications(array $filters): Builder
    {
        return DigitalPublication::query()
            ->when(filled($filters['emission_id'] ?? null), fn ($q) => $q->where('emission_id', (int) $filters['emission_id']))
            ->when(filled($filters['q'] ?? null), fn ($q) => $q->where('title', 'like', '%'.trim($filters['q']).'%'))
            ->when(filled($filters['department'] ?? null), fn ($q) => $q->whereHas('auteur', fn ($user) => $user->where('department', $filters['department'])));
    }
}
