@extends('layouts.app')
@php
    $pageTitle = 'Émissions';
    $pageSubtitle = 'Catalogue des programmes, classés par format';
    $showGlobalSearch = false;
    $headerActions = auth()->user()?->can('create', \App\Models\Emission::class)
        ? [['label' => 'Nouvelle émission', 'icon' => 'fa-solid fa-plus', 'href' => route('emissions.create'), 'variant' => 'primary']]
        : [];
@endphp
@section('content')
    <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4"
             aria-label="Indicateurs du catalogue des émissions">
        <div class="ui-panel border-l-4 border-l-primary p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Au catalogue</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ $stats['total'] }}</p>
        </div>
        <div class="ui-panel border-l-4 border-l-success p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Programmes actifs</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-success">{{ $stats['actives'] }}</p>
        </div>
        <div class="ui-panel border-l-4 border-l-secondary p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                Programmées {{ mb_strtolower($joursOptions[$jourActuel]) }}
            </p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ $stats['aujourdhui'] }}</p>
        </div>
        <div class="ui-panel border-l-4 border-l-warning p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Horaires à renseigner</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-on-surface">{{ $stats['sans_horaire'] }}</p>
        </div>
    </section>

    <form method="GET" action="{{ route('emissions.index') }}" class="mb-6 rounded-2xl border border-outline-variant bg-white p-4 shadow-sm sm:p-5" aria-label="Filtrer les émissions">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2 xl:col-span-1 min-w-0">
                <label for="emission-search" class="mb-1 block text-xs font-semibold text-on-surface-variant">Rechercher un programme</label>
                <input id="emission-search" name="q" value="{{ $q }}" type="search" placeholder="Nom, description ou catégorie…" class="ui-field">
            </div>
            <div class="min-w-0">
                <label for="emission-type" class="mb-1 block text-xs font-semibold text-on-surface-variant">Format</label>
                <select id="emission-type" name="type" class="ui-field">
                    <option value="">Tous les formats</option>
                    @foreach($optionsTypes as $value => $label)<option value="{{ $value }}" @selected((string) $filtreType === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-0">
                <label for="emission-status" class="mb-1 block text-xs font-semibold text-on-surface-variant">Statut du programme</label>
                <select id="emission-status" name="statut" class="ui-field">
                    <option value="">Tous les statuts</option>
                    @foreach($optionsStatuts as $value => $label)<option value="{{ $value }}" @selected((string) $filtreStatut === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-0">
                <label for="emission-day" class="mb-1 block text-xs font-semibold text-on-surface-variant">Jour de diffusion</label>
                <select id="emission-day" name="jour" class="ui-field">
                    <option value="">Tous les jours</option>
                    @foreach($joursOptions as $value => $label)<option value="{{ $value }}" @selected((string) $filtreJour === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-on-surface-variant">{{ $emissions->firstItem() ?? 0 }}–{{ $emissions->lastItem() ?? 0 }} sur {{ $emissions->total() }} résultat(s) · classement par format, horaire puis nom</p>
            <div class="flex flex-wrap items-center gap-2">
                @if((string) $q !== '' || (string) $filtreType !== '' || (string) $filtreStatut !== '' || (string) $filtreJour !== '')
                    <a href="{{ route('emissions.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-outline-variant px-4 text-sm font-semibold text-primary">Réinitialiser</a>
                @endif
                <button type="submit" class="ui-action ui-action--primary">Filtrer</button>
            </div>
        </div>
    </form>

    @if($emissions->contains(fn ($item) => auth()->user()->can('delete', $item)))
    @include('_partials.bulk-delete-toolbar', ['resource' => 'emissions'])
@endif

    <div class="space-y-6" data-bulk-list="bulk-emissions">
        @forelse($emissionGroups as $format => $programmes)
            <section class="overflow-hidden rounded-2xl border border-outline-variant bg-white shadow-sm" aria-labelledby="format-{{ $loop->index }}">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-outline-variant bg-surface-container-low px-4 py-3 sm:px-5">
                    <h2 id="format-{{ $loop->index }}" class="text-lg font-bold text-primary">{{ $formatLabels[$format] ?? 'Format à renseigner' }}</h2>
                    <span class="text-xs text-on-surface-variant">{{ $programmes->count() }} programme(s) sur cette page</span>
                </div>
                <div class="divide-y divide-outline-variant/60">
                    @foreach($programmes as $emission)
                        <article class="grid grid-cols-1 gap-3 p-3 sm:p-4 md:grid-cols-2 xl:grid-cols-4" aria-label="{{ $emission->nom }}">
                            <div class="md:col-span-2 xl:col-span-4">@include('_partials.bulk-delete-checkbox', ['resource' => 'emissions', 'record' => $emission, 'label' => $emission->nom])</div>
                            <div class="min-w-0">
                                <a href="{{ route('emissions.show', $emission) }}" class="inline-flex min-h-11 items-center break-words text-base font-bold leading-snug text-primary hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary">{{ $emission->nom }}</a>
                                @if($emission->sous_titre)<p class="mt-1 text-sm text-on-surface-variant">{{ $emission->sous_titre }}</p>@endif
                                <div class="mt-2">{!! $emission->statut?->badge() ?? '' !!}</div>
                            </div>
                            <div class="min-w-0 text-sm">
                                <p class="mb-1 text-xs font-semibold text-on-surface-variant">Diffusion</p>
                                <p class="font-semibold text-primary">{{ $emission->heure_debut ? $emission->horaireLabel : 'Horaire à renseigner' }}</p>
                                <p class="mt-1 text-xs text-on-surface-variant">{{ $emission->jours_diffusion ? implode(' · ', array_map(fn ($jour) => $joursOptions[$jour] ?? $jour, $emission->jours_diffusion)) : 'Jours à renseigner' }}</p>
                                @if($emission->duree_minutes > 0)<p class="mt-1 text-xs text-on-surface-variant">{{ $emission->duree_minutes }} min</p>@endif
                            </div>
                            <div class="min-w-0 text-sm">
                                <p class="mb-1 text-xs font-semibold text-on-surface-variant">Présentation</p>
                                <p>{{ $emission->presentateurPrincipal?->nom_complet ?? 'Présentateur non affecté' }}</p>
                                @can('viewAny', \App\Models\Conducteur::class)
                                    <a href="{{ route('conducteurs.emission.index', ['emission_id' => $emission->id]) }}" class="mt-2 inline-flex min-h-[44px] items-center font-semibold text-primary hover:underline">Conducteurs ({{ $emission->conducteurs_count }})</a>
                                @endcan
                            </div>
                            <div class="flex flex-wrap items-start gap-2 xl:justify-end">
                                @can('create', \App\Models\Conducteur::class)
                                    @if($emission->is_active)
                                        <a href="{{ route('conducteurs.emission.create', ['emission_id' => $emission->id, 'date' => today()->toDateString()]) }}" class="ui-action ui-action--primary">Préparer un conducteur</a>
                                    @endif
                                @endcan
                                @if(auth()->user()->can('update', $emission) || auth()->user()->can('delete', $emission))
                                    <details class="relative">
                                        <summary class="flex min-h-[44px] cursor-pointer list-none items-center rounded-lg border border-outline-variant px-2 text-sm text-primary" aria-label="Gérer {{ $emission->nom }}">Gérer</summary>
                                        <div class="absolute right-0 top-full z-20 mt-2 w-48 rounded-lg border border-outline-variant bg-white p-2 shadow-lg">
                                            @can('update', $emission)
                                                <a href="{{ route('emissions.edit', $emission) }}" class="flex min-h-[44px] items-center rounded px-3 text-sm text-primary hover:bg-slate-50">Modifier le programme</a>
                                            @endcan
                                            @can('delete', $emission)
                                                <form method="POST" action="{{ route('emissions.destroy', $emission) }}" data-confirm-delete="Supprimer l’émission {{ $emission->nom }} ?" data-confirm-delete-text="Cette action est irréversible.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="flex min-h-[44px] w-full items-center rounded px-3 text-sm text-error hover:bg-slate-50">Supprimer</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <x-ui.empty-state title="Aucune émission ne correspond à ces filtres"
                description="Modifiez votre recherche ou affichez l'ensemble des programmes." icon="fa-tv">
                <x-slot:actions>
                    <a href="{{ route('emissions.index') }}" class="ui-action ui-action--outline">Afficher tout le catalogue</a>
                </x-slot:actions>
            </x-ui.empty-state>
        @endforelse
    </div>
    @if($emissions->hasPages())
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Pagination du catalogue">
            <span class="text-sm text-on-surface-variant">Page {{ $emissions->currentPage() }} sur {{ $emissions->lastPage() }}</span>
            {{ $emissions->onEachSide(1)->links() }}
        </div>
    @endif
@endsection
