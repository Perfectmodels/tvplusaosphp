@extends('layouts.app')
@php
$pageTitle    = request('country') === 'GA' ? 'Veille Gabon' : 'Veille éditoriale';
$pageSubtitle = 'Articles collectés, priorités et orientation vers la rédaction.';
$showGlobalSearch = false;
$headerActions = [
    ['label' => 'Gérer les sources', 'icon' => 'fa-solid fa-rss',
     'href'  => route('veille.sources'), 'variant' => 'secondary'],
];
if (auth()->user()?->can('create', \App\Models\VeilleSubject::class)) {
    $headerActions[] = [
        'label' => 'Importer un article',
        'icon' => 'fa-solid fa-cloud-arrow-down',
        'type' => 'button',
        'variant' => 'primary',
        'attrs' => 'data-modal-open="scrapeModal"',
    ];
}
// Liste déroulante de rubriques : uniquement les rubriques réellement utilisées + « Non classés »
// Les champs cachés conservent les autres filtres actifs (?status=, ?q=, ?source_id=).
$currentCat = (string) request()->query('category', '');
$currentStream = (string) ($stream ?? request()->query('stream', ''));
$catPills   = [];
foreach (\App\Enums\PoleRubriqueEnum::cases() as $case) {
    $n = (int) ($categoryCounts->get($case->value, 0) ?? 0);
    if ($n > 0) {
        $catPills[] = ['value' => $case->value, 'label' => $case->shortLabel(), 'count' => $n];
    }
}
if ($nonClasses > 0) {
    $catPills[] = ['value' => 'none', 'label' => 'Non classés', 'count' => $nonClasses];
}
$autresFiltres = array_filter([
    'status'    => request()->query('status'),
    'stream'    => $currentStream,
]);
$rechercheVeille = (string) request()->query('q', '');
$sourceSelectionnee = (string) request()->query('source_id', '');
$filtresActifs = array_filter([
    'country' => request()->query('country'),
    'category' => $currentCat,
    'q' => $rechercheVeille,
    'source_id' => $sourceSelectionnee,
    'stream' => $currentStream,
]);
$streamCounts = $streamCounts ?? ['gabon' => 0, 'articles' => 0, 'alertes' => 0, 'a_verifier' => 0];
$statusSplash = [
    'approved' => 'bg-tertiary-container',
    'pending' => 'bg-secondary-fixed',
    'draft' => 'bg-secondary-fixed',
    'archived' => 'bg-surface-variant',
    'rejected' => 'bg-error-container',
];
@endphp
@section('content')
<section class="mb-8" data-veille-live data-status-url="{{ route('veille.automation') }}" data-revision="{{ $automation['revision'] }}">
    @include('_partials.veille-automation')
    <div class="mb-6 flex flex-col gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-on-surface sm:text-3xl">Articles de veille</h2>
            <p class="text-[16px] leading-6 text-on-surface-variant mt-1 font-normal">Collecte et classement automatiques des sources configurées.</p>
        </div>
        <div class="w-full">
            {{-- Recherche + filtres éditoriaux --}}
            <form method="GET" action="{{ route('veille.sujets.index') }}"
                  class="grid grid-cols-1 gap-3 rounded-2xl border border-outline-variant bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-[minmax(0,1.2fr)_repeat(4,minmax(0,1fr))_auto]" aria-label="Filtrer les articles de veille">
                <select name="country" aria-label="Périmètre de la veille" class="min-h-[44px] rounded-lg border border-outline-variant px-3 text-sm" onchange="this.form.submit()">
                    <option value="">Toutes les actualités</option>
                    <option value="GA" @selected(request('country') === 'GA')>Actualité gabonaise</option>
                </select>
                <select name="stream" aria-label="Flux de veille" class="min-h-[44px] rounded-lg border border-outline-variant px-3 text-sm" onchange="this.form.submit()">
                    <option value="">Tous les flux</option>
                    <option value="gabon" @selected($currentStream === 'gabon')>Gabon · tout</option>
                    <option value="articles" @selected($currentStream === 'articles')>Gabon · articles</option>
                    <option value="alertes" @selected($currentStream === 'alertes')>Gabon · alertes & réseaux</option>
                    <option value="a_verifier" @selected($currentStream === 'a_verifier')>À vérifier (hors Gabon)</option>
                    <option value="all" @selected($currentStream === 'all')>Tout (non filtré)</option>
                </select>
                @foreach(array_filter(['status' => request()->query('status')]) as $filtre => $valeur)
                    <input type="hidden" name="{{ $filtre }}" value="{{ $valeur }}">
                @endforeach

                <div class="relative min-w-0 sm:col-span-2 xl:col-span-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm"></i>
                    <input type="search" name="q" value="{{ $rechercheVeille }}" aria-label="Rechercher dans les articles de veille"
                           placeholder="Titre, mot-clé, résumé…" class="ui-field w-full pl-9">
                </div>

                <select name="category" data-veille-refresh-options="categories" onchange="this.form.submit()"
                        aria-label="Filtrer par rubrique"
                        class="min-h-[44px] px-4 py-2.5 rounded-lg border border-outline-variant bg-surface-container-lowest text-sm tracking-[0.05em] text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 cursor-pointer">
                    <option value="">Toutes les rubriques</option>
                    @foreach($catPills as $pill)
                        <option value="{{ $pill['value'] }}" @selected($currentCat === $pill['value'])>{{ $pill['label'] }} ({{ $pill['count'] }})</option>
                    @endforeach
                </select>

                <select name="source_id" data-veille-refresh-options="sources" aria-label="Filtrer par source"
                        class="min-h-[44px] px-4 py-2.5 rounded-lg border border-outline-variant bg-surface-container-lowest text-sm text-on-surface outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
                    <option value="">Toutes les sources</option>
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}" @selected($sourceSelectionnee === (string) $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="ui-action ui-action--primary">Filtrer</button>

                @if($currentCat !== '' || $rechercheVeille !== '' || $sourceSelectionnee !== '')
                    <a href="{{ route('veille.sujets.index') }}" class="ui-action ui-action--outline">Réinitialiser</a>
                @endif
            </form>
        </div>
    </div>

    @if($subjects->contains(fn ($item) => auth()->user()->can('delete', $item)))
@include('_partials.bulk-delete-toolbar', ['resource' => 'sujets-veille', 'explanation' => 'Les sujets cochés seront archivés. Leur historique sera conservé.'])
@endif

    <div id="veille-subject-results" data-veille-refresh-region data-bulk-list="bulk-sujets-veille">
    <div class="mb-5 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-3 md:p-4">
        <p class="mb-3 text-xs font-bold uppercase tracking-[.14em] text-on-surface-variant">Flux Gabon & alertes</p>
        <div class="flex items-center gap-2.5 flex-wrap">
            @php $sBase = array_filter(['country' => request('country'), 'category' => $currentCat ?: null, 'q' => $rechercheVeille ?: null, 'source_id' => $sourceSelectionnee ?: null, 'status' => request()->query('status')]); @endphp
            <a href="{{ route('veille.sujets.index', array_merge($sBase, ['stream' => 'gabon'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-full border text-xs font-semibold uppercase tracking-wider {{ $currentStream === 'gabon' ? 'bg-primary text-on-primary border-primary' : 'border-outline-variant bg-white text-on-surface-variant hover:text-primary' }}"><span class="font-bold">{{ $streamCounts['gabon'] ?? 0 }}</span><span>Gabon · tout</span></a>
            <a href="{{ route('veille.sujets.index', array_merge($sBase, ['stream' => 'articles'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-full border text-xs font-semibold uppercase tracking-wider {{ $currentStream === 'articles' ? 'bg-primary text-on-primary border-primary' : 'border-outline-variant bg-white text-on-surface-variant hover:text-primary' }}"><span class="font-bold">{{ $streamCounts['articles'] ?? 0 }}</span><span>Articles Gabon</span></a>
            <a href="{{ route('veille.sujets.index', array_merge($sBase, ['stream' => 'alertes'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-full border text-xs font-semibold uppercase tracking-wider {{ $currentStream === 'alertes' ? 'bg-secondary text-on-secondary border-secondary' : 'border-outline-variant bg-white text-on-surface-variant hover:text-primary' }}"><span class="font-bold">{{ $streamCounts['alertes'] ?? 0 }}</span><span>Alertes & réseaux</span></a>
            <a href="{{ route('veille.sujets.index', array_merge($sBase, ['stream' => 'a_verifier'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-full border text-xs font-semibold uppercase tracking-wider {{ $currentStream === 'a_verifier' ? 'bg-amber-600 text-white border-amber-600' : 'border-outline-variant bg-white text-on-surface-variant hover:text-primary' }}"><span class="font-bold">{{ $streamCounts['a_verifier'] ?? 0 }}</span><span>À vérifier / hors Gabon</span></a>
            <a href="{{ route('veille.sujets.index', array_merge($sBase, ['stream' => 'all'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-full border text-xs font-semibold uppercase tracking-wider {{ $currentStream === 'all' ? 'bg-surface-variant text-on-surface border-surface-variant' : 'border-outline-variant bg-white text-on-surface-variant hover:text-primary' }}"><span>Tous (non filtré)</span></a>
        </div>
        <p class="mt-2 text-xs text-on-surface-variant">Les flux Gabon sont filtrés pour éviter le mélange : articles de fond séparés des alertes/réseaux sociaux, éléments hors Gabon en « À vérifier ».</p>
    </div>

    <div class="flex items-center gap-3 mb-6 flex-wrap">
        <a href="{{ route('veille.sujets.index', array_filter(array_merge($filtresActifs, ['status' => null]))) }}" class="inline-flex items-center gap-1.5 px-3 py-2.5 min-h-[44px] rounded-full border border-outline-variant bg-surface-container-lowest text-xs tracking-wider font-semibold uppercase text-on-surface-variant hover:text-primary">
            <span class="font-bold">{{ $counts['all'] }}</span>
            <span>Tous</span>
        </a>
        <a href="{{ route('veille.sujets.index', array_merge($filtresActifs, ['status'=>'pending'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2.5 min-h-[44px] rounded-full border border-outline-variant bg-secondary-fixed/30 text-xs tracking-wider font-semibold uppercase text-on-secondary-fixed-variant hover:bg-secondary-fixed">
            <span class="font-bold">{{ $counts['pending'] }}</span>
            <span>En attente</span>
        </a>
        <a href="{{ route('veille.sujets.index', array_merge($filtresActifs, ['status'=>'approved'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2.5 min-h-[44px] rounded-full border border-outline-variant bg-tertiary-container text-xs tracking-wider font-semibold uppercase text-on-tertiary-container hover:bg-tertiary-container/80">
            <span class="font-bold">{{ $counts['approved'] }}</span>
            <span>Retenus</span>
        </a>
        <a href="{{ route('veille.sujets.index', array_merge($filtresActifs, ['status'=>'archived'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2.5 min-h-[44px] rounded-full border border-outline-variant bg-surface-variant text-xs tracking-wider font-semibold uppercase text-on-surface-variant hover:bg-surface-container">
            <span class="font-bold">{{ $counts['archived'] }}</span>
            <span>Archivés</span>
        </a>
    </div>

    @if($subjects->count() === 0)
    <div class="rounded-2xl border border-outline-variant bg-white px-5 py-10 text-center shadow-sm sm:p-12">
        <i class="fa-solid fa-list-tree text-[#001a41] text-6xl mb-6 opacity-70"></i>
        <h3 class="text-[24px] font-bold leading-8 text-on-background mb-3">{{ $rechercheVeille !== '' || $currentCat !== '' || $sourceSelectionnee !== '' || request()->filled('status') ? 'Aucun résultat pour ces filtres' : 'Aucun sujet importé' }}</h3>
        <p class="text-[16px] text-on-surface-variant mb-8 max-w-xl mx-auto">
            Les sources configurées alimentent cette liste automatiquement. Les responsables éditoriaux peuvent aussi importer ponctuellement une URL.
        </p>
        <div class="flex items-center justify-center gap-4 flex-wrap">
            @can('create', \App\Models\VeilleSubject::class)
                <button type="button" data-modal-open="scrapeModal" class="ui-action ui-action--primary">
                    <i class="fa-solid fa-cloud-arrow-down"></i> Importer un article
                </button>
            @endcan
            <a href="{{ route('veille.sources') }}" class="ui-action ui-action--outline">
                <i class="fa-solid fa-rss"></i> Voir les sources
            </a>
        </div>
    </div>
    @else
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
        @foreach($subjects as $subject)
        @php
            $splash = $statusSplash[$subject->status] ?? $statusSplash['pending'];
            $sourceCount = $subject->source_id ? 1 : 0;
            $mentionsCount = $subject->remixes_count ?? 0;
            $lastMaj = $subject->published_at?->diffForHumans() ?? $subject->scraped_at?->diffForHumans() ?? '—';
            $opacityArchived = in_array($subject->status,['archived','rejected']) ? 'opacity-75 grayscale' : '';
        @endphp
        <div class="relative">
            <div class="absolute left-5 top-2 z-20">@include('_partials.bulk-delete-checkbox', ['resource' => 'sujets-veille', 'record' => $subject, 'label' => $subject->title])</div>
        <a href="{{ route('veille.sujets.show', $subject) }}"
           class="bg-surface-container-lowest rounded-2xl p-6 shadow-[0_10px_30px_-10px_rgba(0,26,65,0.04)] border border-outline-variant hover:shadow-md transition-shadow relative overflow-hidden group block {{ $opacityArchived }} {{ auth()->user()->can('delete', $subject) ? 'pt-16' : '' }}">
            <div class="absolute top-0 right-0 w-24 h-24 {{ $splash }} rounded-bl-full -mr-4 -mt-4 opacity-40 group-hover:scale-110 transition-transform duration-300"></div>

            @php
                $kind = $subject->veilleKind();
                $kindLabel = match($kind) { 'social' => 'Réseau social', 'alerte' => 'Alerte', default => 'Article' };
                $kindClass = match($kind) { 'social' => 'bg-fuchsia-100 text-fuchsia-800 border-fuchsia-200', 'alerte' => 'bg-amber-100 text-amber-800 border-amber-200', default => 'bg-sky-100 text-sky-800 border-sky-200' };
                $isGabon = $subject->isGabonRelevant();
            @endphp
            <div class="flex justify-between items-start mb-4 relative z-10 gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex px-2.5 py-1 rounded-full border text-xs font-semibold {{ $kindClass }}">{{ $kindLabel }}</span>
                    @if($isGabon)
                        <span class="inline-flex px-2.5 py-1 rounded-full border text-xs font-semibold bg-emerald-100 text-emerald-800 border-emerald-200">Gabon</span>
                    @else
                        <span class="inline-flex px-2.5 py-1 rounded-full border text-xs font-semibold bg-orange-100 text-orange-800 border-orange-200">À vérifier</span>
                    @endif
                    <x-veille-status-badge :status="$subject->status" />
                    @if($subject->watch_analyzed_at)<span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">Veille IA préparée</span>@endif
                    <span class="inline-flex px-2.5 py-1 rounded-full border text-xs font-semibold {{ $subject->category?->badgeClass() ?? 'bg-surface-variant text-on-surface-variant border-outline-variant' }}">
                        {{ $subject->category?->shortLabel() ?? 'Non classé' }}
                    </span>
                </div>
                <span class="shrink-0 px-2 py-1 rounded-lg bg-surface-container text-xs font-bold text-on-surface-variant" title="Priorité de veille">P{{ $subject->priority }}/10</span>
            </div>

            <div class="relative z-10 mb-2 text-xs font-semibold text-primary-container truncate">
                {{ $subject->source?->name ?? 'Saisie manuelle' }}
            </div>
            <h3 class="text-[24px] font-bold leading-8 mb-2 relative z-10 text-on-background">
                {{ $subject->title }}
            </h3>
            <p class="text-sm leading-5 text-on-surface-variant mb-6 line-clamp-2 relative z-10">
                {{ $subject->summary ?? ($subject->content ? Str::limit(strip_tags($subject->content), 160) : 'Résumé non disponible.') }}
            </p>

            <div class="flex items-center justify-between mt-auto pt-4 border-t border-surface-variant relative z-10">
                <div class="flex items-center gap-4">
                    <div class="flex flex-col">
                        <span class="text-xs leading-4 text-outline font-medium">Sources</span>
                        <span class="text-sm font-bold text-on-surface">{{ $sourceCount }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs leading-4 text-outline font-medium">Remixes</span>
                        <span class="text-sm font-bold text-on-surface">{{ $mentionsCount }}</span>
                    </div>
                </div>
                <div class="text-right flex flex-col">
                    <span class="text-xs leading-4 text-outline font-medium">{{ $subject->published_at ? 'Publié' : 'Collecté' }}</span>
                    <span class="text-xs leading-4 text-on-surface">{{ $lastMaj }}</span>
                </div>
            </div>
        </a>
        </div>
        @endforeach
    </div>

    @if(method_exists($subjects,'hasPages') && $subjects->hasPages())
    <div class="mt-10 flex justify-center">
        {{ $subjects->onEachSide(1)->links() }}
    </div>
    @endif
    @endif
    </div>
</section>

@can('create', \App\Models\VeilleSubject::class)
<div id="scrapeModal" data-modal-backdrop role="dialog" aria-modal="true"
     aria-labelledby="scrape-modal-title" tabindex="-1"
     class="fixed inset-0 z-[70] hidden focus:outline-none">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" data-modal-close></div>
    <div class="relative z-10 flex items-start sm:items-center justify-center min-h-screen p-4 overflow-y-auto">
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-outline-variant overflow-hidden max-h-[92dvh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-surface-variant flex justify-between items-center bg-surface-container-low/40">
                <div>
                    <h3 id="scrape-modal-title" class="text-xl font-bold text-on-surface">Importer un article</h3>
                    <p class="text-sm text-on-surface-variant mt-1">Collez un lien d\'actualité (site web ou flux RSS).</p>
                </div>
                <button type="button" data-modal-close aria-label="Fermer" class="w-11 h-11 rounded-full hover:bg-surface-variant text-on-surface-variant transition flex items-center justify-center">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form id="scrapeForm" class="p-6 space-y-5">
                @csrf
                <div class="space-y-2">
                    <label for="scrape_url" class="block text-sm font-semibold tracking-[0.05em] text-on-surface">URL de l\'article ou flux</label>
                    <div class="relative">
                        <i class="fa-solid fa-link absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-sm"></i>
                        <input id="scrape_url" name="url" type="url" required placeholder="https://lemonde.fr/..." 
                               class="w-full pl-10 pr-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base text-on-background focus:outline-none focus:ring-2 focus:ring-primary-fixed focus:border-primary-fixed">
                    </div>
                    @error('url')<p class="text-sm text-error mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="scrape-source" class="block text-sm font-semibold tracking-[0.05em] text-on-surface mb-2">Source associée (optionnel)</label>
                    <select id="scrape-source" name="source_id" class="w-full px-4 py-3 bg-surface-container-lowest border border-outline-variant rounded-xl text-base text-on-background">
                        <option value="">Aucune</option>
                        @foreach($sources as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ strtoupper($s->type) }})</option>
                        @endforeach
                    </select>
                </div>

                <div id="scrapePreview" class="hidden space-y-3" role="status" aria-live="polite">
                    <div class="h-px bg-surface-variant my-2"></div>
                    <div class="flex items-center gap-2 text-sm font-semibold tracking-wider uppercase text-on-surface-variant">
                        <i class="fa-solid fa-sparkles text-secondary"></i>
                        <span id="scrapePreviewTitle">Prévisualisation</span>
                    </div>
                    <div id="scrapePreviewList" class="space-y-3 max-h-80 overflow-y-auto pr-2"></div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                    <button type="button" data-modal-close class="w-full sm:w-auto px-5 py-3 rounded-xl border border-outline-variant text-on-surface-variant font-semibold text-sm tracking-[0.05em] hover:text-primary hover:border-primary transition">
                        Annuler
                    </button>
                    <button id="scrapePreviewBtn" type="button"
                            class="w-full sm:w-auto inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-secondary-container text-on-secondary-container font-semibold text-sm tracking-[0.05em] hover:opacity-90 active:scale-95 transition">
                        <i class="fa-solid fa-magnifying-glass"></i> Prévisualiser scraping
                    </button>
                    <button id="scrapeSaveBtn" type="button" disabled
                            class="w-full sm:w-auto inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-primary-container text-on-primary-container font-semibold text-sm tracking-[0.05em] hover:opacity-90 active:scale-95 transition disabled:opacity-60 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<script>
(function(){
    const modal = document.getElementById('scrapeModal');
    if (!modal) return;

    const closeEls = modal.querySelectorAll('[data-modal-close]');
    let focusBeforeOpen = null;
    let overflowBeforeOpen = '';
    function openScrapeModal(trigger) {
        if (!modal.classList.contains('hidden')) return;
        focusBeforeOpen = trigger;
        overflowBeforeOpen = document.body.style.overflow;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        (modal.querySelector('#scrape_url') || modal).focus({ preventScroll: true });
    }
    function closeScrapeModal() {
        if (modal.classList.contains('hidden')) return;
        modal.classList.add('hidden');
        document.body.style.overflow = overflowBeforeOpen;
        if (focusBeforeOpen?.isConnected) focusBeforeOpen.focus({ preventScroll: true });
        focusBeforeOpen = null;
    }
    document.addEventListener('click', e => {
        const trigger = e.target.closest('[data-modal-open="scrapeModal"]');
        if (!trigger) return;
        e.preventDefault();
        openScrapeModal(trigger);
    });
    closeEls.forEach(button => button.addEventListener('click', e => {
        e.preventDefault();
        closeScrapeModal();
    }));
    modal.addEventListener('keydown', e => {
        if (e.key === 'Escape') { e.preventDefault(); closeScrapeModal(); return; }
        if (e.key !== 'Tab') return;
        const focusables = Array.from(modal.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(el => el.getClientRects().length);
        if (!focusables.length) { e.preventDefault(); modal.focus(); return; }
        const first = focusables[0], last = focusables[focusables.length - 1];
        if (e.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) {
            e.preventDefault(); first.focus();
        }
    });

    const csrf  = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const urlIn = document.getElementById('scrape_url');
    const srcIn = modal.querySelector('[name="source_id"]');
    const previewBox   = document.getElementById('scrapePreview');
    const previewTitle = document.getElementById('scrapePreviewTitle');
    const previewList  = document.getElementById('scrapePreviewList');
    const saveBtn      = document.getElementById('scrapeSaveBtn');
    const previewBtn   = document.getElementById('scrapePreviewBtn');
    let lastItems = [];
    let token = null;
    if (window.Laravel && window.Laravel.apiToken) token = window.Laravel.apiToken;

    function setLoading(state){
        if (state) {
            previewBtn.disabled = true;
            previewBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Chargement...';
        } else {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Prévisualiser scraping';
        }
    }

    function safeWebUrl(value){
        try {
            const url = new URL(String(value || ''), window.location.origin);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch (_) {
            return null;
        }
    }

    function renderItems(items, message){
        previewBox.classList.remove('hidden');
        previewList.replaceChildren();
        if (message){
            previewTitle.textContent = message;
        } else if (items && items.length){
            previewTitle.textContent = items.length + ' article(s) détecté(s) — cochez ceux à importer';
            items.slice(0,20).forEach((it, idx) => {
                const row = document.createElement('label');
                row.className = 'flex items-start gap-3 p-3 rounded-xl border border-outline-variant bg-surface-container-lowest hover:border-primary-fixed cursor-pointer transition';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'mt-1 w-5 h-5 rounded text-primary focus:ring-primary-fixed';
                checkbox.dataset.scrapeIdx = String(idx);
                checkbox.checked = idx === 0;

                const content = document.createElement('div');
                content.className = 'flex-1 min-w-0';
                const header = document.createElement('div');
                header.className = 'flex items-center gap-2 mb-1';

                const imageUrl = safeWebUrl(it.image_url);
                if (imageUrl) {
                    const image = document.createElement('img');
                    image.src = imageUrl;
                    image.alt = '';
                    image.className = 'w-12 h-12 rounded-lg object-cover border border-outline-variant flex-shrink-0';
                    image.addEventListener('error', () => image.remove(), { once: true });
                    header.appendChild(image);
                }

                const textBlock = document.createElement('div');
                textBlock.className = 'min-w-0';
                const title = document.createElement('div');
                title.className = 'font-semibold text-on-background line-clamp-2 text-[15px] leading-6';
                title.textContent = String(it.title || '(Sans titre)');
                const meta = document.createElement('div');
                meta.className = 'text-xs text-on-surface-variant mt-0.5';
                const date = it.published_at ? new Date(it.published_at) : null;
                const dateLabel = date && !Number.isNaN(date.getTime()) ? date.toLocaleString('fr-FR') : '';
                meta.textContent = [String(it.source || ''), dateLabel].filter(Boolean).join(' · ');
                textBlock.append(title, meta);
                header.appendChild(textBlock);
                content.appendChild(header);

                if (it.summary) {
                    const summary = document.createElement('div');
                    summary.className = 'text-sm text-on-surface-variant line-clamp-2 leading-5 mt-2';
                    summary.textContent = String(it.summary).slice(0, 220);
                    content.appendChild(summary);
                }

                const articleUrl = safeWebUrl(it.url);
                if (articleUrl) {
                    const link = document.createElement('a');
                    link.href = articleUrl;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.className = 'text-xs text-primary hover:underline mt-2 inline-flex items-center gap-1';
                    link.addEventListener('click', (event) => event.stopPropagation());
                    const icon = document.createElement('i');
                    icon.className = 'fa-solid fa-arrow-up-right-from-square text-xs';
                    link.append(icon, document.createTextNode(' Ouvrir l’original'));
                    content.appendChild(link);
                }

                row.append(checkbox, content);
                previewList.appendChild(row);
            });
        }
        saveBtn.disabled = !(items && items.length);
    }

    previewBtn.addEventListener('click', async () => {
        const url = urlIn.value.trim();
        if (!url){ urlIn.focus(); urlIn.classList.add('ring-2','ring-error'); setTimeout(()=>urlIn.classList.remove('ring-2','ring-error'),1500); return; }
        setLoading(true);
        previewBox.classList.add('hidden');
        try {
            const body = new FormData();
            body.append('url', url);
            if (srcIn.value) body.append('source_id', srcIn.value);
            const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            if (csrf) headers['X-CSRF-TOKEN'] = csrf;
            if (token) headers['Authorization'] = 'Bearer ' + token;
            const r = await fetch('/api/v1/veille/scrape-url', { method:'POST', headers, credentials:'same-origin', body });
            const data = await r.json();
            lastItems = data.items || [];
            renderItems(lastItems, data.success ? null : (data.message || 'Erreur inconnue.'));
        } catch (err){
            previewBox.classList.remove('hidden');
            previewTitle.textContent = 'Erreur réseau';
            const errorBox = document.createElement('div');
            errorBox.className = 'p-4 rounded-xl bg-error-container text-on-error-container text-sm';
            errorBox.textContent = err.message || 'Impossible de joindre le serveur.';
            previewList.replaceChildren(errorBox);
        } finally {
            setLoading(false);
        }
    });

    saveBtn.addEventListener('click', async () => {
        const url = urlIn.value.trim();
        if (!url || lastItems.length === 0) return;
        const indices = Array.from(previewList.querySelectorAll('[data-scrape-idx]:checked')).map(el => parseInt(el.dataset.scrapeIdx, 10));
        if (indices.length === 0){ alert('Cochez au moins un article à importer.'); return; }
        saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Import en cours...';

        const selected = indices.map(i => lastItems[i]).filter(Boolean);
        let ok = 0; let skipped = 0; let failed = 0;
        for (const it of selected){
            try {
                const body = new FormData();
                body.append('_token', csrf || '');
                body.append('title', (it.title || ('Article ' + Date.now())).toString().slice(0,255));
                if (it.url) body.append('url', it.url);
                if (it.summary) body.append('summary', it.summary);
                if (it.content) body.append('content', it.content);
                if (it.image_url) body.append('image_url', it.image_url);
                if (srcIn.value) body.append('source_id', srcIn.value);
                if (it.published_at) {
                    const d = new Date(it.published_at);
                    if (!isNaN(d.getTime())) body.append('published_at', d.toISOString().slice(0,10));
                }
                body.append('status', 'draft');
                const storeHeaders = { 'Accept':'application/json,text/html' };
                if (csrf) storeHeaders['X-CSRF-TOKEN'] = csrf;
                storeHeaders['X-Requested-With'] = 'XMLHttpRequest';
                const r = await fetch('{{ route('veille.sujets.store') }}', { method:'POST', headers: storeHeaders, credentials:'same-origin', redirect:'manual', body });
                let isOk = false; let isSkipped = false;
                const ct = r.headers.get('content-type') || '';
                let json = null;
                try {
                    if (ct.includes('application/json')) json = await r.json();
                } catch(_){}
                if (json && (json.success === true || json.skipped === true)) {
                    isOk = true;
                    if (json.skipped === true) isSkipped = true;
                } else if (r.ok || r.status === 200 || r.status === 302) {
                    isOk = true;
                }
                if (isOk){ ok++; if (isSkipped) skipped++; }
                else failed++;
            } catch(e){
                failed++;
                console.warn('Import item failed:', (e && e.message) ? e.message : e, it);
            }
        }
        const imported = ok - skipped;
        if (imported > 0 || skipped > 0 || failed === 0){
            saveBtn.innerHTML = '<i class="fa-solid fa-check"></i> Traité : '+ok+' / '+selected.length+' (importés: '+imported+', déjà existants: '+skipped+')';
        } else {
            saveBtn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Échecs : '+failed+' / '+selected.length;
        }
        setTimeout(() => location.reload(), 900);
    });
})();
</script>
@endsection
