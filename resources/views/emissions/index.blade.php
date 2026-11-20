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
    <div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-on-surface-variant" aria-label="Résumé du catalogue">
        <span><strong class="text-primary">{{ $stats['total'] }}</strong> émissions au catalogue</span>
        <span><strong class="text-primary">{{ $stats['aujourdhui'] }}</strong> programmées {{ mb_strtolower($joursOptions[$jourActuel]) }}</span>
        @if($stats['sans_horaire'])<span>{{ $stats['sans_horaire'] }} horaires à renseigner</span>@endif
    </div>

    <form method="GET" action="{{ route('emissions.index') }}" class="mb-6 rounded-xl border border-outline-variant bg-white p-4 sm:p-5" aria-label="Filtrer les émissions">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2 xl:col-span-1 min-w-0">
                <label for="emission-search" class="mb-1 block text-xs font-semibold text-on-surface-variant">Rechercher un programme</label>
                <input id="emission-search" name="q" value="{{ $q }}" type="search" placeholder="Nom, description ou catégorie…" class="min-h-[44px] w-full rounded-lg border border-outline-variant px-3 text-sm">
            </div>
            <div class="min-w-0">
                <label for="emission-type" class="mb-1 block text-xs font-semibold text-on-surface-variant">Format</label>
                <select id="emission-type" name="type" class="min-h-[44px] w-full rounded-lg border border-outline-variant px-3 text-sm">
                    <option value="">Tous les formats</option>
                    @foreach($optionsTypes as $value => $label)<option value="{{ $value }}" @selected((string) $filtreType === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-0">
                <label for="emission-status" class="mb-1 block text-xs font-semibold text-on-surface-variant">Statut du programme</label>
                <select id="emission-status" name="statut" class="min-h-[44px] w-full rounded-lg border border-outline-variant px-3 text-sm">
                    <option value="">Tous les statuts</option>
                    @foreach($optionsStatuts as $value => $label)<option value="{{ $value }}" @selected((string) $filtreStatut === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-0">
                <label for="emission-day" class="mb-1 block text-xs font-semibold text-on-surface-variant">Jour de diffusion</label>
                <select id="emission-day" name="jour" class="min-h-[44px] w-full rounded-lg border border-outline-variant px-3 text-sm">
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
                <button type="submit" class="min-h-[44px] rounded-lg bg-primary px-5 text-sm font-semibold text-white">Filtrer</button>
            </div>
        </div>
    </form>

    @if($emissions->contains(fn ($item) => auth()->user()->can('delete', $item)))
    @include('_partials.bulk-delete-toolbar', ['resource' => 'emissions'])
@endif

    <div class="space-y-6" data-bulk-list="bulk-emissions">
        @forelse($emissionGroups as $format => $programmes)
            <section class="rounded-xl border border-outline-variant bg-white" aria-labelledby="format-{{ $loop->index }}">
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-xl border-b border-outline-variant bg-slate-50 px-4 py-4 sm:px-5">
                    <h2 id="format-{{ $loop->index }}" class="text-lg font-bold text-primary">{{ $formatLabels[$format] ?? 'Format à renseigner' }}</h2>
                    <span class="text-xs text-on-surface-variant">{{ $programmes->count() }} programme(s) sur cette page</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($programmes as $emission)
                        <article class="grid grid-cols-1 gap-4 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-4" aria-label="{{ $emission->nom }}">
                            <div class="md:col-span-2 xl:col-span-4">@include('_partials.bulk-delete-checkbox', ['resource' => 'emissions', 'record' => $emission, 'label' => $emission->nom])</div>
                            <div class="min-w-0">
                                <a href="{{ route('emissions.show', $emission) }}" class="font-bold leading-snug text-primary hover:underline">{{ $emission->nom }}</a>
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
                                        <a href="{{ route('conducteurs.emission.create', ['emission_id' => $emission->id, 'date' => today()->toDateString()]) }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-primary px-3 text-sm font-semibold text-white">Préparer un conducteur</a>
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
            <div class="rounded-xl border border-outline-variant bg-white p-8 text-center">
                <p class="font-semibold text-primary">Aucune émission ne correspond à ces filtres.</p>
                <a href="{{ route('emissions.index') }}" class="mt-3 inline-flex min-h-[44px] items-center text-sm text-primary underline">Afficher tout le catalogue</a>
            </div>
        @endforelse
    </div>
    @if($emissions->hasPages())
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3" aria-label="Pagination du catalogue">
            <span class="text-sm text-on-surface-variant">Page {{ $emissions->currentPage() }} sur {{ $emissions->lastPage() }}</span>
            {{ $emissions->onEachSide(1)->links() }}
        </div>
    @endif
@endsection
