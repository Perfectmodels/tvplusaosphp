@extends('layouts.app')
@push('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/workspace.css'])
@endpush
@php
    $pageTitle = 'Tableau de bord';
    $pageSubtitle = (($isAdminDashboard ?? false) ? 'Production' : 'Chef d’édition').' · Vue d’ensemble de la production éditoriale';
    $headerActions = null;
    $totalEtat = collect($etatProduction)->sum('total');
    $cumulEtat = 0;
    $segmentsEtat = [];
    foreach ($etatProduction as $etape) {
        $debut = $totalEtat > 0 ? $cumulEtat / $totalEtat * 100 : 0;
        $cumulEtat += $etape['total'];
        $fin = $totalEtat > 0 ? $cumulEtat / $totalEtat * 100 : 0;
        if ($etape['total'] > 0) { $segmentsEtat[] = "{$etape['hex']} {$debut}% {$fin}%"; }
    }
    $gradientEtat = $totalEtat > 0 ? 'conic-gradient('.implode(', ', $segmentsEtat).')' : '#eef0f3';
@endphp
@section('content')
    <div class="ed-dashboard-content">
    @include('_partials.workspace-toolbar')
    <section class="ed-kpis" aria-label="Indicateurs de la production du jour">
        @foreach([
            ['Sujets prévus', $nbSujetsPrevus, $nbSujetsEnCours.' en cours', 'description', 'blue', 100],
            ['Sujets prêts', $nbSujetsPrets, $nbSujetsEnMontage.' en montage', 'check_circle', 'green', $nbSujetsPrevus > 0 ? round($nbSujetsPrets / $nbSujetsPrevus * 100) : 0],
            ['Invités confirmés', $nbInvitesConfirmes.' / '.$nbInvitesTotal, ($nbInvitesTotal - $nbInvitesConfirmes).' en attente', 'group', 'purple', $nbInvitesTotal > 0 ? round($nbInvitesConfirmes / $nbInvitesTotal * 100) : 0],
            ['Duplex prévus', $nbDuplexPrevus, $nbDuplexAPreparer.' à préparer', 'cell_tower', 'blue', $nbDuplexPrevus > 0 ? round(($nbDuplexPrevus - $nbDuplexAPreparer) / $nbDuplexPrevus * 100) : 0],
            ['Échéances aujourd’hui', $nbEcheances, 'Voir les conducteurs', 'schedule', 'red', 0],
        ] as [$label, $value, $detail, $icon, $tone, $pct])
            <article class="ed-kpi">
                <span class="ed-kpi-icon ed-tone-{{ $tone }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span></span>
                <div class="flex-1 min-w-0">
                    <h2>{{ $label }}</h2>
                    <strong>{{ $value }}</strong>
                    @if($pct > 0)
                    <div class="mt-1 h-1.5 w-full bg-surface-container-high rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 bg-{{ $tone === 'green' ? 'emerald-500' : ($tone === 'purple' ? 'purple-500' : 'primary') }}"
                             :style="'width: {{ $pct }}%'" style="width: {{ $pct }}%"></div>
                    </div>
                    <small class="text-[10px] text-on-surface-variant">{{ $pct }}%</small>
                    @else
                    @if($icon === 'schedule')<a href="{{ route('conducteurs.emission.index') }}">{{ $detail }}</a>@else<small>{{ $detail }}</small>@endif
                    @endif
                </div>
            </article>
        @endforeach
    </section>

    @include('_partials.dashboard-conference-pointage')
    @include('_partials.shared-workspace-summary')

    <div class="ed-grid">
        <div class="ed-column">
            @include('_partials.dashboard-planning')
            <section class="ed-card ed-state" aria-labelledby="ed-production-title">
                <div class="ed-card-heading"><h2 id="ed-production-title">État de la production</h2></div>
                <div class="ed-production">
                    <div class="ed-donut" style="background: {{ $gradientEtat }}" role="img" aria-label="{{ $totalEtat }} sujets en production"><div><strong>{{ $totalEtat }}</strong><small>Total</small></div></div>
                    <ul class="ed-legend">@foreach($etatProduction as $etape)<li><span class="ed-dot" style="background:{{ $etape['hex'] }}"></span><span>{{ $etape['libelle'] }}</span><b>{{ $etape['total'] }}</b><span class="text-xs text-on-surface-variant ml-auto">{{ $totalEtat > 0 ? round($etape['total'] / $totalEtat * 100) : 0 }}%</span></li>@endforeach</ul>
                </div>
                <a class="ed-text-link" href="{{ route('conducteurs.redaction.index') }}">Voir le détail <span aria-hidden="true">›</span></a>
            </section>
            <section class="ed-card ed-deadlines"><div class="ed-card-heading"><h2>Prochaines échéances</h2><a href="{{ route('conducteurs.emission.index') }}">Voir tout</a></div>
                @forelse($echeances as $echeance)
                    <div class="ed-deadline"><time>{{ $echeance['heure'] }}</time><div><strong>{{ $echeance['titre'] }}</strong><small>Dans {{ $echeance['date']->diffForHumans(null, true) }} · {{ $echeance['detail'] }}</small></div></div>
                @empty<div class="ed-empty"><span class="material-symbols-outlined" aria-hidden="true">event_available</span><p>Aucune échéance à venir aujourd’hui.</p></div>@endforelse
            </section>
        </div>

        <div class="ed-column">
        @include('_partials.dashboard-guests')
        <section class="ed-card ed-stories"><div class="ed-card-heading"><h2>Sujets en production</h2><a href="{{ route('conducteurs.redaction.index') }}">Voir tout</a></div>
            <div class="ed-stories-list">@forelse($sujetsEnProduction as $sujet)
                <article class="ed-story"><span class="ed-story-icon material-symbols-outlined" aria-hidden="true">{{ $sujet['duplex'] ? 'cell_tower' : 'article' }}</span><div class="ed-story-body"><div class="ed-story-title"><strong>{{ $sujet['titre'] }}</strong><time>{{ $sujet['heure'] }}</time></div><div class="ed-story-meta"><small>Affecté à : {{ $sujet['auteur'] }}</small><span class="ed-tag">{{ $sujet['type'] }}</span></div><div class="ed-story-progress"><span>{{ $sujet['etape'] }} {{ $sujet['avancement'] }}%<progress value="{{ $sujet['avancement'] }}" max="100" aria-label="Avancement de {{ $sujet['titre'] }}"></progress></span><small>{{ $sujet['emission'] }}</small></div></div></article>
            @empty<div class="ed-empty ed-empty-production"><span class="material-symbols-outlined" aria-hidden="true">article</span><h3>Aucun sujet en production aujourd’hui.</h3><p>Les sujets de vos conducteurs du jour apparaîtront ici avec leur avancement.</p></div>@endforelse</div>
            @can('create', \App\Models\Conducteur::class)<a class="ed-add" href="{{ route('conducteurs.redaction.index') }}"><span class="material-symbols-outlined" aria-hidden="true">add</span>Créer un nouveau sujet</a>@endcan
        </section>
        </div>

        <div class="ed-column">
            @include('_partials.workspace-list', ['listTitle' => 'Mes priorités', 'listId' => 'workspace-priorities', 'listItems' => $priorities, 'listEmpty' => 'Aucune urgence, échéance proche ou validation attendue dans ce périmètre.'])
            <section class="ed-card ed-alerts"><div class="ed-card-heading"><h2>Veille &amp; Alertes</h2><a href="{{ route('veille.sujets.index') }}">Voir toute la veille</a></div>
                @foreach($alertes['alertesInfo'] as $alerte)<article class="ed-alert"><span class="ed-dot ed-dot-red"></span><div><div class="ed-alert-top"><span>Alerte info</span><time>{{ $alerte['heure'] }}</time></div><strong>{{ $alerte['titre'] }}</strong><p>{{ $alerte['detail'] }}</p><span class="ed-tag">Urgent</span></div></article>@endforeach
                @forelse($alertes['veille'] as $item)<a class="ed-alert" href="{{ route('veille.sujets.show', $item['id']) }}"><span class="ed-dot {{ $item['point'] }}"></span><div><div class="ed-alert-top"><span>{{ $item['niveau'] }}</span><time>{{ $item['heure'] }}</time></div><strong>{{ $item['titre'] }}</strong><p>{{ $item['detail'] }}</p><span class="ed-tag {{ $item['badgeClasse'] }}">{{ $item['badge'] }}</span></div></a>
                @empty @if($alertes['alertesInfo']->isEmpty())<div class="ed-empty"><span class="material-symbols-outlined" aria-hidden="true">notifications_none</span><p>Aucun sujet de veille pour le moment.</p><a class="ed-text-link" href="{{ route('veille.sources') }}">Gérer les sources</a></div>@endif @endforelse
            </section>
            <section class="ed-card ed-jri" aria-labelledby="ed-jri-title"><div class="ed-card-heading"><h2 id="ed-jri-title">Sujets des journalistes / JRI</h2><a href="{{ route('conducteurs.redaction.index', ['date' => today()->toDateString()]) }}">Voir les sujets</a></div>
                @forelse($journalistesTerrain as $jri)
                    <a class="ed-journalist" href="{{ $jri['href'] }}" aria-label="Ouvrir le conducteur du sujet {{ $jri['titre'] }}">
                        <span class="ed-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::substr($jri['nom'], 0, 1) }}</span>
                        <div><strong>{{ $jri['nom'] }}</strong><small>{{ $jri['titre'] }}</small><small>{{ $jri['emission'] }}</small></div>
                        <span class="{{ $jri['couleur'] }}">Terrain : {{ $jri['statut'] }}</span>
                    </a>
                @empty<div class="ed-empty"><span class="material-symbols-outlined" aria-hidden="true">groups</span><p>Aucun sujet affecté à un journaliste / JRI aujourd’hui.</p></div>@endforelse
            </section>
        </div>
    </div>

    <section class="ed-card ed-emissions"><div class="ed-card-heading"><h2>Aperçu des émissions</h2><a href="{{ route('conducteurs.emission.index') }}">Voir les conducteurs</a></div>
        <div class="ed-emissions-grid">@forelse($emissionsDuJour as $ligne)<article class="ed-emission"><div><strong>{{ $ligne['emission']->nom }}</strong><small>{{ $ligne['horaire'] }}</small><span class="{{ $ligne['couleur'] }}">{{ $ligne['statut'] }}</span></div><div class="ed-mini-donut" style="background:conic-gradient(#3173ea {{ $ligne['progression'] * 3.6 }}deg,#eef0f3 0deg)" role="img" aria-label="Progression {{ $ligne['progression'] }} pour cent"><span>{{ $ligne['progression'] }}%</span></div></article>
        @empty<div class="ed-emissions-empty"><p>Aucun conducteur pour les émissions du jour.</p><a href="{{ route('conducteurs.emission.index') }}">Préparer les émissions du jour <span aria-hidden="true">›</span></a></div>@endforelse</div>
    </section>
    <details class="ed-operations"><summary>Indicateurs détaillés</summary><div>@include('_partials.dashboard-operations')</div></details>
    @if($isAdminDashboard ?? false)<div class="ed-admin-note"><span>Accès administrateur global</span><a href="{{ route('admin.activity-logs') }}">Consulter le journal complet</a><a href="{{ route('users.index') }}">Gestion utilisateurs</a></div>@endif
    <details class="ed-role-notices"><summary>Activité récente et actions rapides</summary><div class="ed-role-secondary">@include('_partials.workspace-list', ['listTitle' => 'Activité récente', 'listItems' => $recentActivity, 'listEmpty' => 'Aucun changement enregistré dans ce périmètre et cette période.', 'listId' => null])@include('_partials.workspace-actions')</div></details>
</div>
@endsection
