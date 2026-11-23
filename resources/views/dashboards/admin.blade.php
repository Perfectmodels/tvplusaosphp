@extends('layouts.app')

@push('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/workspace.css', 'resources/css/dashboard-admin.css'])
@endpush

@php
    $pageTitle = 'Tableau de bord';
    $pageSubtitle = $roleLabel.' · Supervision des équipes, de la production et des publications';
    $headerActions = null;
@endphp

@section('content')
<div class="ed-dashboard-content tvos-admin-dashboard">
    @include('_partials.workspace-toolbar')

    @if(!isset($adminAnalytics))
        <section class="ed-card">
            <div class="ed-card-heading"><h2>Accès aux indicateurs</h2></div>
            <p>Les autorisations de lecture nécessaires à la supervision ne sont pas toutes activées pour votre compte.</p>
        </section>
    @else
        <div class="tvos-admin-heading">
            <div>
                <p class="tvos-admin-eyebrow">Centre de supervision</p>
                <h2>Vue d’ensemble</h2>
                <p>Suivez les indicateurs clés, les activités et les décisions à prendre.</p>
            </div>
            <div class="tvos-admin-heading-meta">
                <span class="tvos-admin-period"><span class="material-symbols-outlined" aria-hidden="true">date_range</span>{{ $adminAnalytics['periodLabel'] }}</span>
                <a href="{{ request()->fullUrl() }}" class="tvos-admin-refresh" aria-label="Actualiser les indicateurs"><span class="material-symbols-outlined" aria-hidden="true">refresh</span>Actualiser</a>
            </div>
        </div>

        <section class="tvos-admin-metrics" aria-label="Indicateurs de supervision">
            @foreach($adminAnalytics['cards'] as $card)
                <a class="tvos-admin-metric {{ $card['label'] === 'Retards' && $card['value'] > 0 ? 'tvos-admin-metric--warning' : '' }}" href="{{ $card['href'] }}">
                    <span class="tvos-admin-metric-icon"><span class="material-symbols-outlined" aria-hidden="true">{{ $card['icon'] }}</span></span>
                    <span class="tvos-admin-metric-label">{{ $card['label'] }}</span>
                    <strong>{{ number_format($card['value'], 0, ',', ' ') }}</strong>
                    <span class="tvos-admin-metric-footer">
                        <span>{{ $card['subtitle'] }}</span>
                        @if($card['delta'] !== null)
                            <span class="tvos-admin-delta" title="Comparaison avec la période précédente de même durée">
                                {{ $card['delta'] > 0 ? '+' : '' }}{{ $card['delta'] }} vs précédent
                            </span>
                        @endif
                    </span>
                </a>
            @endforeach
        </section>

        @if($adminAnalytics['sourcesErrors'] > 0)
            <div class="tvos-admin-warning" role="status">
                <span class="material-symbols-outlined" aria-hidden="true">error_outline</span>
                <span>{{ $adminAnalytics['sourcesErrors'] }} source(s) de veille active(s) signalent une erreur. Ce compteur est global, indépendamment des filtres.</span>
                <a href="{{ route('veille.sources') }}">Examiner les sources</a>
            </div>
        @endif

        @include('_partials.dashboard-conference-pointage')
        @include('_partials.shared-workspace-summary')

        <div class="tvos-admin-charts">
            <section class="ed-card tvos-admin-panel tvos-admin-panel--wide" aria-labelledby="tvos-trends-title" x-data="{ series: 'both', selected: '' }">
                <div class="tvos-admin-panel-header">
                    <div>
                        <h3 id="tvos-trends-title">Évolution de l’activité</h3>
                        <p>{{ $adminAnalytics['trendLabel'] }} · Comptages quotidiens</p>
                    </div>
                    <div class="tvos-admin-legend" aria-hidden="true">
                        <span><i class="tvos-admin-dot tvos-admin-dot--blue"></i>Conducteurs</span>
                        <span><i class="tvos-admin-dot tvos-admin-dot--green"></i>Publications</span>
                    </div>
                </div>
                <label class="tvos-admin-chart-note" style="display:block;margin-bottom:.75rem">Séries affichées
                    <select x-model="series" class="rounded-lg border px-2 py-1">
                        <option value="both">Conducteurs et publications</option>
                        <option value="production">Conducteurs uniquement</option>
                        <option value="published">Publications uniquement</option>
                    </select>
                </label>
                <div class="tvos-admin-chart-scroll" role="region" aria-label="Histogramme de l’activité quotidienne" tabindex="0">
                    <div class="tvos-admin-trend-chart">
                        @foreach($adminAnalytics['trend'] as $day)
                            @php
                                $pHeight = $adminAnalytics['trendMax'] ? 100 * $day['production'] / $adminAnalytics['trendMax'] : 0;
                                $dHeight = $adminAnalytics['trendMax'] ? 100 * $day['published'] / $adminAnalytics['trendMax'] : 0;
                            @endphp
                            <div class="tvos-admin-trend-day" style="--day-index: {{ $loop->index }}">
                                <div class="tvos-admin-trend-bars">
                                    <div x-show="series !== 'published'" class="tvos-admin-trend-bar tvos-admin-trend-bar--blue" style="height: {{ $pHeight }}%" title="{{ $day['production'] }} conducteurs le {{ $day['date'] }}"><span class="sr-only">{{ $day['production'] }} conducteurs</span></div>
                                    <div x-show="series !== 'production'" class="tvos-admin-trend-bar tvos-admin-trend-bar--green" style="height: {{ $dHeight }}%" title="{{ $day['published'] }} publications le {{ $day['date'] }}"><span class="sr-only">{{ $day['published'] }} publications</span></div>
                                </div>
                                <button type="button" class="tvos-admin-trend-label" style="min-height:44px"
                                    aria-label="Détails du {{ $day['label'] }}"
                                    :aria-pressed="selected === '{{ $day['date'] }}'"
                                    :style="{ fontWeight: selected === '{{ $day['date'] }}' ? 'bold' : '', textDecoration: selected === '{{ $day['date'] }}' ? 'underline' : '' }"
                                    @click="selected = '{{ $day['date'] }}'">{{ $day['label'] }}</button>
                                <span class="sr-only">{{ $day['date'] }} : {{ $day['production'] }} conducteurs, {{ $day['published'] }} publications.</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div aria-live="polite" aria-atomic="true">
                    @foreach($adminAnalytics['trend'] as $day)
                        <p x-cloak x-show="selected === '{{ $day['date'] }}'" class="tvos-admin-chart-note">
                            <strong>{{ $day['label'] }}</strong>
                            <span x-show="series !== 'published'"> · Conducteurs : {{ $day['production'] }}</span>
                            <span x-show="series !== 'production'"> · Publications : {{ $day['published'] }}</span>
                        </p>
                    @endforeach
                </div>
                @php
                    $conducteursAffiches = array_sum(array_column($adminAnalytics['trend'], 'production'));
                    $publicationsAffichees = array_sum(array_column($adminAnalytics['trend'], 'published'));
                @endphp
                @if($conducteursAffiches + $publicationsAffichees === 0)
                    <p class="tvos-admin-chart-note">Aucun conducteur ni publication sur les jours affichés.</p>
                @else
                    <p class="tvos-admin-chart-note">Sur les jours affichés, <strong>{{ $conducteursAffiches }}</strong> conducteur(s) et <strong>{{ $publicationsAffichees }}</strong> publication(s). Les publications sont comptées à leur date de publication.</p>
                @endif
                <details class="tvos-admin-chart-data">
                    <summary>Voir les chiffres par jour</summary>
                    <table>
                        <thead><tr><th scope="col">Jour</th><th scope="col">Conducteurs</th><th scope="col">Publications</th></tr></thead>
                        <tbody>
                            @foreach($adminAnalytics['trend'] as $day)
                                <tr><th scope="row"><time datetime="{{ $day['date'] }}">{{ $day['label'] }}</time></th><td>{{ $day['production'] }}</td><td>{{ $day['published'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </section>

            @php
                $totalStatuts = $adminAnalytics['statutsTotal'];
                $positionStatuts = 0;
                $segmentsStatuts = [];
                foreach ($adminAnalytics['statuts'] as $statut) {
                    if ($statut['total'] < 1) continue;
                    $debut = $totalStatuts ? $positionStatuts * 100 / $totalStatuts : 0;
                    $positionStatuts += $statut['total'];
                    $fin = $totalStatuts ? $positionStatuts * 100 / $totalStatuts : 0;
                    $segmentsStatuts[] = $statut['color'].' '.$debut.'% '.$fin.'%';
                }
            @endphp
            <section class="ed-card tvos-admin-panel" aria-labelledby="tvos-status-title">
                <div class="tvos-admin-panel-header">
                    <div>
                        <h3 id="tvos-status-title">Statuts des contenus</h3>
                        <p>Contenus numériques créés dans la période</p>
                    </div>
                </div>
                @if($totalStatuts > 0)
                    <div class="tvos-admin-status-summary">
                        <div class="tvos-admin-donut" role="img" aria-label="{{ $totalStatuts }} contenus répartis par statut" style="background: conic-gradient({{ implode(', ', $segmentsStatuts) }})">
                            <div><strong>{{ $totalStatuts }}</strong><small>Contenus</small></div>
                        </div>
                        <div class="tvos-admin-status-legend">
                            @foreach($adminAnalytics['statuts'] as $statut)
                                <div><span class="tvos-admin-dot" style="background: {{ $statut['color'] }}"></span><span>{{ $statut['label'] }}</span><strong>{{ $statut['total'] }} · {{ number_format(100 * $statut['total'] / $totalStatuts, 1, ',', ' ') }} %</strong></div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="tvos-admin-empty">Aucun contenu numérique créé sur cette période.</div>
                @endif
            </section>
        </div>

        <div class="tvos-admin-columns">
            <section class="ed-card tvos-admin-panel" aria-labelledby="tvos-emissions-title">
                <div class="tvos-admin-panel-header">
                    <div>
                        <h3 id="tvos-emissions-title">Productions par émission</h3>
                        <p>Classement par nombre de conducteurs</p>
                    </div>
                    <a href="{{ route('emissions.index') }}">Toutes les émissions</a>
                </div>
                @forelse($adminAnalytics['emissions'] as $emission)
                    <div class="tvos-admin-ranking-row">
                        <div class="tvos-admin-ranking-heading"><span>{{ $emission['name'] }}</span><strong>{{ $emission['total'] }}</strong></div>
                        <div class="tvos-admin-ranking-track"><div style="width: {{ 100 * $emission['total'] / $adminAnalytics['emissionMax'] }}%"></div></div>
                    </div>
                @empty
                    <div class="tvos-admin-empty">Aucun conducteur pour ces critères.</div>
                @endforelse
            </section>

            <section class="ed-card tvos-admin-panel" aria-labelledby="tvos-published-title">
                <div class="tvos-admin-panel-header">
                    <div>
                        <h3 id="tvos-published-title">Dernières publications</h3>
                        <p>Contenus publiés et auteurs identifiés</p>
                    </div>
                    <a href="{{ route('digital.index') }}">Ouvrir Digital</a>
                </div>
                @forelse($adminAnalytics['recentPublications'] as $publication)
                    <div class="tvos-admin-feed-row">
                        <span class="material-symbols-outlined tvos-admin-feed-icon" aria-hidden="true">article</span>
                        <div>
                            <strong>{{ $publication['title'] }}</strong>
                            <p>{{ $publication['author'] }} · {{ $publication['platform'] }}</p>
                        </div>
                        <time>{{ $publication['date'] }}</time>
                    </div>
                @empty
                    <div class="tvos-admin-empty">Aucune publication enregistrée sur cette période.</div>
                @endforelse
            </section>
        </div>

        <div class="tvos-admin-columns">
            <section class="ed-card tvos-admin-panel" aria-labelledby="tvos-activity-title">
                <div class="tvos-admin-panel-header">
                    <div>
                        <h3 id="tvos-activity-title">Activité de l’équipe</h3>
                        <p>Dernières opérations enregistrées</p>
                    </div>
                    <a href="{{ route('admin.activity-logs') }}">Journal complet</a>
                </div>
                @forelse($adminAnalytics['activity'] as $event)
                    <div class="tvos-admin-feed-row">
                        <span class="material-symbols-outlined tvos-admin-feed-icon" aria-hidden="true">person</span>
                        <div>
                            <strong>{{ $event['author'] }}</strong>
                            <p>{{ $event['event'] }}</p>
                        </div>
                        <time>{{ $event['date'] }}</time>
                    </div>
                @empty
                    <div class="tvos-admin-empty">Aucune opération enregistrée pour ces critères.</div>
                @endforelse
            </section>
            <div class="tvos-admin-actions-column">
                @include('_partials.workspace-list', ['listTitle' => 'Décisions et priorités', 'listId' => 'workspace-priorities', 'listItems' => $priorities, 'listEmpty' => 'Aucune priorité à traiter.'])
                @include('_partials.workspace-actions')
            </div>
        </div>

        @include('_partials.dashboard-role-extras')

        <details class="tvos-admin-more">
            <summary><span class="material-symbols-outlined" aria-hidden="true">view_module</span>Explorer les listes détaillées des modules<span class="material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
            <div class="tvos-admin-more-grid">
                @foreach($sections as $section)
                    @include('_partials.workspace-list', ['listTitle' => $section['title'], 'listItems' => $section['items'], 'listEmpty' => $section['empty'], 'listId' => null])
                @endforeach
            </div>
        </details>
        <p class="tvos-admin-chart-note">Comparaison des KPI avec la période précédente : {{ $adminAnalytics['periodComparison'] }}. Les compteurs de stock (veille et utilisateurs) ne sont pas filtrés par date. Les métriques et les listes détaillées conservent leurs définitions propres.</p>
    @endif
</div>
@endsection
