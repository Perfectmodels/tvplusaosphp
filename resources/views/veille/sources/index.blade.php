@extends('layouts.app')
@php
    $pageTitle    = 'Gestion des Sources de Veille';
    $pageSubtitle = 'Configurez un nouveau flux de données automatisé pour le réseau de surveillance.';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Voir les sujets', 'icon' => 'fa-solid fa-newspaper',
         'href'  => route('veille.sujets.index'), 'variant' => 'secondary'],
    ];
    if (auth()->user()?->can('scrape', \App\Models\SourceVeille::class)) {
        $headerActions[] = [
            'label' => 'Demander une collecte',
            'icon' => 'fa-solid fa-bolt',
            'type' => 'button',
            'button_type' => 'submit',
            'form' => 'form-trigger-scrape',
            'variant' => 'primary',
        ];
    }

    $method = $editing?->exists ? 'PUT' : 'POST';
    $route  = $editing?->exists
        ? route('veille.sources.update', $editing)
        : route('veille.sources.store');

    $frequencyLabels = [
        '5min'    => '5 min',
        '15min'   => '15 min',
        '30min'   => '30 min',
        '1h'      => '1 h',
        '2daily'  => '2x/jour',
        'daily'   => '1x/jour',
    ];

    $oldName      = old('name',      $editing?->name      ?? '');
    $oldUrl       = old('url',       $editing?->url       ?? '');
    $oldType      = old('type',      $editing?->type      ?? '');
    $oldFrequency = old('frequency', $editing?->frequency ?? '15min');
    $oldStatus    = old('status',    $editing?->status    ?? 'active');
    $oldCategory  = old('category',  $editing?->category?->value ?? '');
    $oldCountry   = old('country', $editing?->country ?? '');
    $oldLanguage  = old('language', $editing?->language ?? 'fr');
    $oldCountry = is_string($oldCountry) ? $oldCountry : '';
    $oldLanguage = is_string($oldLanguage) ? $oldLanguage : '';
    $oldPriority  = old('priority', $editing?->priority ?? 5);
@endphp
@section('content')
    <form id="form-trigger-scrape" action="{{ route('veille.sources.trigger') }}" method="POST" style="display:none;">
        @csrf
    </form>

    <div class="flex flex-col gap-8" data-veille-live data-status-url="{{ route('veille.automation') }}" data-revision="{{ $automation['revision'] }}">
        @include('_partials.veille-automation')

        <section id="veille-source-stats" data-veille-refresh-region aria-label="État de la veille" class="grid grid-cols-2 xl:grid-cols-5 gap-3">
            @foreach([
                ['label' => 'Sources référencées', 'value' => $stats['total'], 'icon' => 'fa-rss', 'tone' => 'text-primary-container bg-primary-container/10'],
                ['label' => 'Actives', 'value' => $stats['active'], 'icon' => 'fa-circle-check', 'tone' => 'text-emerald-700 bg-emerald-50'],
                ['label' => 'À analyser', 'value' => $stats['due'], 'icon' => 'fa-clock', 'tone' => 'text-amber-700 bg-amber-50'],
                ['label' => 'En erreur', 'value' => $stats['errors'], 'icon' => 'fa-triangle-exclamation', 'tone' => 'text-rose-700 bg-rose-50'],
                ['label' => 'Désactivées', 'value' => $stats['disabled'], 'icon' => 'fa-circle-pause', 'tone' => 'text-slate-600 bg-slate-100'],
            ] as $stat)
                <article class="group rounded-2xl border border-outline-variant/70 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-xs font-semibold text-on-surface-variant">{{ $stat['label'] }}</span>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl {{ $stat['tone'] }} transition-transform group-hover:scale-105">
                            <i class="fa-solid {{ $stat['icon'] }}"></i>
                        </span>
                    </div>
                    <div class="mt-3 text-2xl font-black text-on-surface">{{ $stat['value'] }}</div>
                </article>
            @endforeach
        </section>

        @can('create', \App\Models\SourceVeille::class)
            <!-- FORMULAIRE D'AJOUT / MODIFICATION -->
            <div class="w-full">
                <div class="bg-surface rounded-xl p-6 border border-outline-variant shadow-[0_10px_30px_-10px_rgba(0,26,65,0.04)]">
                    <h2 class="text-sm font-bold tracking-[0.05em] text-primary mb-4">
                        {{ $editing?->exists ? 'Modifier la source' : "Formulaire d'ajout" }}
                    </h2>

                    <form action="{{ $route }}" method="POST" novalidate>
                        @csrf
                        @if ($method === 'PUT')
                            @method('PUT')
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div class="flex flex-col gap-1.5 w-full">
                                <label class="text-xs text-on-surface-variant font-medium">Nom de la source</label>
                                <input
                                    type="text"
                                    name="name"
                                    value="{{ $oldName }}"
                                    maxlength="200"
                                    placeholder="ex: Reuters Technology"
                                    class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px] focus:ring-1 focus:ring-[#001a41] focus:border-[#001a41] transition-all"
                                    required />
                                @error('name')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex flex-col gap-1.5 w-full">
                                <label class="text-xs text-on-surface-variant font-medium">Adresse du site ou du flux</label>
                                <input
                                    type="url"
                                    name="url"
                                    value="{{ $oldUrl }}"
                                    maxlength="1000"
                                    placeholder="https://..."
                                    class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px] focus:ring-1 focus:ring-[#001a41] focus:border-[#001a41] transition-all"
                                    required />
                                @error('url')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 pt-4 border-t border-outline-variant">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs text-on-surface-variant font-medium">Type de source</label>
                                <select name="type" class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px]">
                                    <option value="" @selected($oldType === '')>Détection automatique</option>
                                    <option value="rss"  @selected($oldType === 'rss')>Flux RSS</option>
                                    <option value="web"  @selected($oldType === 'web')>Site web (scraping)</option>
                                    <option value="single_url" @selected($oldType === 'single_url')>Page unique</option>
                                    <option value="listing"    @selected($oldType === 'listing')>Catégorie / listing</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs text-on-surface-variant font-medium">Rubrique (héritée par les sujets importés)</label>
                                <select name="category" class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px]">
                                    <option value="">— Non classée —</option>
                                    @foreach (\App\Enums\PoleRubriqueEnum::selectOptions() as $v => $l)
                                        <option value="{{ $v }}" @selected($oldCategory === $v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs text-on-surface-variant font-medium">Fréquence de récupération</label>
                                <select name="frequency" class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px]">
                                    @foreach ($frequencyLabels as $key => $label)
                                        <option value="{{ $key }}" @selected($oldFrequency === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs text-on-surface-variant font-medium">Priorité éditoriale (1–10)</label>
                                <input type="number" name="priority" min="1" max="10" value="{{ $oldPriority }}"
                                       class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px] focus:ring-2 focus:ring-primary/15 focus:border-primary">
                                @error('priority')<p class="text-xs text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="source-country" class="text-xs text-on-surface-variant font-medium">Pays / territoire</label>
                                <select id="source-country" name="country" class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px]">
                                    <option value="">Non renseigné</option>
                                    @if($oldCountry !== '' && !isset($optionsPays[$oldCountry]))<option value="{{ $oldCountry }}" selected>{{ $oldCountry }}</option>@endif
                                    @foreach($optionsPays as $code => $nomPays)<option value="{{ $code }}" @selected($oldCountry === $code)>{{ $nomPays }}</option>@endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs text-on-surface-variant font-medium">Langue</label>
                                <input type="text" name="language" list="source-language-options" maxlength="5" value="{{ $oldLanguage }}" placeholder="Sélectionner ou saisir une langue"
                                       class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px] focus:ring-2 focus:ring-primary/15 focus:border-primary">
                                <datalist id="source-language-options">@foreach(\App\Enums\LangueEnum::cases() as $langue)<option value="{{ $langue->value }}" label="{{ $langue->label() }}"></option>@endforeach</datalist>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs text-on-surface-variant font-medium">Statut</label>
                                <select name="status" class="w-full px-4 py-3 bg-surface border border-outline-variant rounded-lg text-[15px]">
                                    <option value="active"   @selected($oldStatus === 'active')>🟢 Actif</option>
                                    <option value="error"    @selected($oldStatus === 'error')>🔴 Erreur</option>
                                    <option value="disabled" @selected($oldStatus === 'disabled')>⚪ Désactivé</option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5 justify-end">
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <button
                                        type="submit"
                                        class="w-full bg-[#001a41] text-white px-6 py-3 rounded-lg text-[15px] font-semibold flex items-center justify-center gap-2 hover:bg-[#001029] transition-colors">
                                        @if ($editing?->exists)
                                            <i class="fa-solid fa-save"></i> Enregistrer
                                        @else
                                            <i class="fa-solid fa-plus"></i> Ajouter la source
                                        @endif
                                    </button>
                                    @if ($editing?->exists)
                                        <a
                                            href="{{ route('veille.sources') }}"
                                            class="w-full border border-outline-variant text-on-surface-variant px-6 py-3 rounded-lg text-[15px] font-semibold flex items-center justify-center gap-2 hover:bg-surface-container transition-colors">
                                            Annuler
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        <!-- LISTE DES SOURCES -->
        <div class="w-full">
            <div class="bg-surface rounded-xl border border-outline-variant overflow-hidden shadow-[0_10px_30px_-10px_rgba(0,26,65,0.04)]">
                <div class="p-4 md:p-5 border-b border-outline-variant flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 bg-surface-container-lowest">
                    <div>
                        <h3 class="text-lg font-bold tracking-tight text-primary">Sources de veille</h3>
                        <p class="mt-1 text-xs text-on-surface-variant">{{ $sources->firstItem() ?? 0 }}–{{ $sources->lastItem() ?? 0 }} sur {{ $sources->total() }} sources · {{ $online }} actives</p>
                    </div>
                    <form method="GET" action="{{ route('veille.sources') }}" class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                        <div class="relative flex-1 sm:min-w-[260px]">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm"></i>
                            <input type="search" name="q" value="{{ $search }}" placeholder="Rechercher par nom ou URL…"
                                   class="w-full rounded-xl border border-outline-variant bg-white py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
                        </div>
                        <select name="status" class="rounded-xl border border-outline-variant bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
                            <option value="">Tous les statuts</option>
                            <option value="active" @selected($statusFilter === 'active')>Actives</option>
                            <option value="error" @selected($statusFilter === 'error')>En erreur</option>
                            <option value="disabled" @selected($statusFilter === 'disabled')>Désactivées</option>
                        </select>
                        <button class="rounded-xl bg-primary-container px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-110">Filtrer</button>
                        @if($search !== '' || $statusFilter !== '')
                            <a href="{{ route('veille.sources') }}" class="rounded-xl border border-outline-variant px-4 py-2.5 text-center text-sm font-semibold text-on-surface-variant transition hover:bg-surface-container">Effacer</a>
                        @endif
                    </form>
                </div>

                @if($sources->contains(fn ($item) => auth()->user()->can('delete', $item)))
@include('_partials.bulk-delete-toolbar', ['resource' => 'sources-veille', 'explanation' => 'La collecte des sources cochées sera arrêtée. Leurs articles seront conservés.'])
@endif

<div class="overflow-x-auto">
                    <table data-mobile-cards class="w-full text-left border-collapse min-w-max">
                        <thead>
                            <tr class="bg-surface-container-low border-b border-outline-variant">
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">Nom</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">URL</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">Type</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">Rubrique</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">Fréquence</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">Articles</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">Dernière collecte</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant">Statut</th>
                                <th class="p-4 text-sm font-semibold text-on-surface-variant text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody data-bulk-list="bulk-sources-veille" id="veille-source-rows" data-veille-refresh-region class="text-sm text-on-surface">
                            @forelse ($sources as $s)
                                @php
                                    $typeBadgeClass = $s->type === 'rss'
                                        ? 'bg-[#dae2ff] text-[#001847]'
                                        : 'bg-[#ffdbcd] text-[#360f00]';
                                    $typeLabel = match ($s->type) {
                                        'rss'   => 'RSS',
                                        'web'   => 'Web',
                                        'single_url' => 'URL',
                                        'listing'    => 'Listing',
                                        default => 'RSS',
                                    };

                                    $freqLabel = $frequencyLabels[$s->frequency] ?? ($s->frequency ?? '—');
                                @endphp
                                <tr class="group border-b border-outline-variant transition-colors hover:bg-white">
                                    <td class="p-4 font-semibold text-on-surface">
@include('_partials.bulk-delete-checkbox', ['resource' => 'sources-veille', 'record' => $s, 'label' => $s->name])
                                        <div>{{ $s->name }}</div>
                                        <div class="mt-1 text-xs font-normal text-on-surface-variant">{{ $s->country ?: 'Pays —' }} · {{ $s->language ? strtoupper($s->language) : 'Langue —' }} · priorité {{ $s->priority }}</div>
                                    </td>
                                    <td class="p-4 text-on-surface-variant min-w-[200px] max-w-[320px] break-all">
                                        <a href="{{ $s->url }}" target="_blank" rel="noopener noreferrer" class="transition hover:text-[#0054cd] hover:underline break-all">
                                            {{ $s->url }}
                                        </a>
                                    </td>
                                    <td class="p-4">
                                        <span class="{{ $typeBadgeClass }} px-2 py-1 rounded text-xs font-medium">
                                            {{ $typeLabel }}
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-1 rounded-full border text-xs font-semibold {{ $s->category?->badgeClass() ?? 'bg-surface-variant text-on-surface-variant border-outline-variant' }}">
                                            {{ $s->category?->shortLabel() ?? 'Non classée' }}
                                        </span>
                                    </td>
                                    <td class="p-4">{{ $freqLabel }}</td>
                                    <td class="p-4">
                                        <span class="font-semibold">{{ $s->subjects_count }}</span>
                                    </td>
                                    <td class="p-4 text-xs text-on-surface-variant">
                                        @if($s->last_scraped_at)
                                            <span title="{{ $s->last_scraped_at->format('d/m/Y H:i:s') }}">{{ $s->last_scraped_at->diffForHumans() }}</span>
                                        @else
                                            <span class="italic">Jamais collectée</span>
                                        @endif
                                    </td>
                                    <td class="p-4 max-w-xs whitespace-normal">
                                        <x-veille-status-badge kind="source" :status="$s->status ?? 'disabled'" />
                                        @if($s->last_error)<p class="mt-2 text-xs text-amber-700">{{ $s->last_error }}</p>@endif
                                        @if($s->collection_requested_at)<p class="mt-1 text-xs text-primary">Collecte demandée</p>
                                        @elseif($s->is_active && $s->prochainPassageAt())<p class="mt-1 text-xs text-on-surface-variant">Prochain passage : {{ $s->prochainPassageAt()->diffForHumans() }}</p>@endif
                                        @if($s->last_succeeded_at)<p class="mt-1 text-xs text-on-surface-variant">{{ $s->last_items_count }} article(s) lus · {{ $s->last_new_items_count }} nouveau(x)</p>@endif
                                    </td>
                                    <td class="p-4 text-right space-x-2 whitespace-nowrap">
                                        @can('update', $s)
                                            <a
                                                href="{{ route('veille.sources') }}?edit={{ $s->id }}"
                                                title="Modifier la source"
                                                class="text-on-surface-variant hover:text-[#0054cd] transition-colors inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-surface-container-low">
                                                <i class="fa-solid fa-pencil text-[16px]"></i>
                                            </a>
                                        @endcan

                                        @can('delete', $s)
                                            <form
                                                 method="POST"
                                                 action="{{ route('veille.sources.destroy', $s) }}"
                                                 data-confirm="Désactiver la source « {{ e($s->name) }} » ?"
                                                 data-confirm-text="La source et ses articles seront conservés ; seule la collecte automatique sera arrêtée."
                                                 data-confirm-label="Désactiver"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                     title="Désactiver la source"
                                                    class="text-on-surface-variant hover:text-[#ba1a1a] transition-colors inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-error-container/20">
                                                     <i class="fa-solid fa-pause text-[16px]"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="p-12 text-center text-on-surface-variant">
                                        <div class="w-16 h-16 rounded-full bg-surface-container mx-auto mb-4 flex items-center justify-center text-2xl opacity-60">
                                            <i class="fa-solid fa-rss"></i>
                                        </div>
                                        @if($search !== '' || $statusFilter !== '')
                                            <h4 class="font-semibold text-on-surface mb-1">Aucune source ne correspond à ces filtres</h4>
                                            <p class="text-sm">Essayez un autre nom ou un autre statut.</p>
                                        @else
                                            <h4 class="font-semibold text-on-surface mb-1">Aucune source configurée</h4>
                                            <p class="text-sm">Ajoutez votre première source de veille via le formulaire ci-dessus.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($sources->hasPages())
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-outline-variant bg-white p-4">
                        <p class="text-xs text-on-surface-variant">Page {{ $sources->currentPage() }} sur {{ $sources->lastPage() }}</p>
                        <div>{{ $sources->onEachSide(1)->links() }}</div>
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection
