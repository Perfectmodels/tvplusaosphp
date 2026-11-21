@extends('layouts.app')
@php
    $pageTitle = 'Conducteurs · Rédaction';
    $pageSubtitle = 'Préparation des sujets et suivi de production';
    $createRedactionUrl = route('conducteurs.redaction.create', ['date' => $dateEdition?->toDateString() ?? today()->toDateString()]);
@endphp
@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-2xl text-sm text-on-surface-variant">
            Organisez les sujets de la rédaction par date d’édition et suivez leur production, du terrain à la diffusion.
        </p>
        @can('create', \App\Models\Conducteur::class)
            <a href="{{ $createRedactionUrl }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-container">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Nouveau conducteur de rédaction
            </a>
        @endcan
    </div>
    @include('_partials.conducteur-edition-selector')
    @php
        $listeConducteurs = ($allDates ?? false) ? $conducteurs->getCollection() : $conducteurs;
        $sujetsSurPage = $listeConducteurs->sum(fn ($item) => $item->sequences->count());
        $livresSurPage = $listeConducteurs->sum(fn ($item) => $item->sequences->whereNotNull('livre_at')->count());
        $progressionMoyenne = $listeConducteurs->count() ? (int) round($listeConducteurs->avg(fn ($item) => $item->progression_percent ?? 0)) : 0;
    @endphp
    <div class="mx-auto max-w-[1600px] space-y-5">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Indicateurs des conducteurs de rédaction">
            <div class="ui-panel border-l-4 border-l-primary p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ ($allDates ?? false) ? 'Conducteurs trouvés' : 'Conducteurs de la date' }}</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ ($allDates ?? false) ? $conducteurs->total() : $conducteurs->count() }}</p>
            </div>
            <div class="ui-panel border-l-4 border-l-secondary p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Sujets affichés</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-on-surface">{{ $sujetsSurPage }}</p>
            </div>
            <div class="ui-panel border-l-4 border-l-success p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Sujets livrés</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-success">{{ $livresSurPage }}</p>
            </div>
            <div class="ui-panel border-l-4 border-l-secondary p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Progression moyenne affichée</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-on-surface">{{ $progressionMoyenne }} %</p>
            </div>
        </div>

        <div class="rounded-2xl border border-outline-variant bg-white p-3 sm:p-5">
        @if($conducteurs->contains(fn ($item) => auth()->user()->can('delete', $item)))
            @include('_partials.bulk-delete-toolbar', ['resource' => 'conducteurs-redaction'])
        @endif

        @if($conducteurs->count() === 0)
            <x-ui.empty-state icon="fa-newspaper"
                title="{{ ($allDates ?? false) ? 'Aucun conducteur ne correspond à votre recherche' : 'Aucun conducteur de rédaction pour cette date.' }}"
                description="{{ ($allDates ?? false) ? 'Essayez un autre nom ou réinitialisez les filtres.' : 'Sélectionnez une autre date pour retrouver les sujets et leur suivi de production.' }}">
                @can('create', \App\Models\Conducteur::class)
                    <x-slot:actions>
                        <a href="{{ $createRedactionUrl }}" class="ui-action ui-action--primary">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Créer un conducteur de rédaction
                        </a>
                    </x-slot:actions>
                @endcan
            </x-ui.empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3" data-bulk-list="bulk-conducteurs-redaction">
                @foreach($conducteurs as $c)
                    @continue((string)$c->type !== 'redaction')
                    @php
                        $totalSeq = $c->sequences->count();
                        $livresCount = $c->sequences->whereNotNull('livre_at')->count();
                        $progression = (int) ($c->progression_percent ?? 0);
                        $titreConducteur = trim($c->nom ?? '') !== ''
                            ? $c->nom
                            : (optional($c->emission)->nom ?? 'Conducteur #'.$c->id);
                    @endphp
                    <article class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-outline-variant bg-white shadow-sm transition-colors hover:border-secondary/40 focus-within:border-secondary"
                             data-conducteur-id="{{ $c->id }}"
                             data-conducteur-titre="{{ $titreConducteur }}"
                             data-conducteur-date="{{ optional($c->date_edition)->format('d/m/Y') }}"
                             data-conducteur-nb-sujets="{{ $totalSeq }}"
                             data-url-delete="{{ route('conducteurs.redaction.destroy', $c) }}">
                        <div class="p-5 border-b border-outline/10">
                            @include('_partials.bulk-delete-checkbox', ['resource' => 'conducteurs-redaction', 'record' => $c, 'label' => $titreConducteur])
                            <div class="flex justify-between items-start mb-3 gap-3">
                                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                    <x-ui.status-badge variant="info">
                                        <i class="fa-solid fa-fingerprint text-xs" aria-hidden="true"></i>{{ $c->code_conducteur_affichage }}
                                    </x-ui.status-badge>
                                    {!! $c->type_badge_html !!}
                                    {!! $c->statut_badge_html !!}
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="text-xs text-on-surface-variant">
                                        {{ optional($c->date_edition)->format('d/m/Y') ?? 'Date à renseigner' }}
                                    </span>
                                    @can('delete', $c)
                                        <button type="button"
                                                class="btn-delete-conducteur inline-flex h-11 w-11 items-center justify-center rounded-lg border border-error/20 text-error transition-colors hover:bg-error hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-error"
                                                title="Supprimer ce conducteur et tous ses sujets"
                                                aria-label="Supprimer">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    @endcan
                                </div>
                            </div>
                            <h3 class="mb-1 line-clamp-2 min-h-12 text-lg font-bold leading-snug text-on-surface">
                                {{ $titreConducteur }}
                            </h3>
                            <div class="flex items-center gap-2 text-sm text-on-surface-variant">
                                <i class="fa-regular fa-clipboard-list text-xs"></i> Suivi rédaction
                            </div>
                        </div>
                        <div class="px-5 py-4 space-y-3 bg-surface-container-lowest">
                            <div class="grid grid-cols-3 gap-2 text-xs text-center">
                                <div class="p-2 rounded-md bg-surface-container">
                                    <div class="text-xs sm:text-xs uppercase tracking-wide text-on-surface-variant">Sujets</div>
                                    <div class="text-lg font-bold text-on-surface mt-1">{{ $totalSeq }}</div>
                                </div>
                                <div class="p-2 rounded-md bg-surface-container">
                                    <div class="text-xs sm:text-xs uppercase tracking-wide text-on-surface-variant">Livrés</div>
                                    <div class="text-lg font-bold text-emerald-600 mt-1">{{ $livresCount }}</div>
                                </div>
                                <div class="p-2 rounded-md bg-surface-container">
                                    <div class="text-xs sm:text-xs uppercase tracking-wide text-on-surface-variant">Workflow</div>
                                    <div class="text-lg font-bold text-primary mt-1">{{ $progression }}%</div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between items-end mb-1.5 text-xs sm:text-xs">
                                    <span class="uppercase tracking-wider text-on-surface-variant">Progression globale</span>
                                    <span class="font-bold text-blue-700">{{ $progression }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high"
                                     role="progressbar" aria-label="Progression des sujets de {{ $titreConducteur }}"
                                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ max(0, min(100, $progression)) }}">
                                    <div class="h-full rounded-full bg-primary transition-[width] duration-300"
                                         style="width:{{ max(0, min(100, $progression)) }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-auto flex items-center justify-between gap-2 border-t border-outline-variant/60 p-4">
                            <a href="{{ route('conducteurs.redaction.show', $c) }}"
                               class="ui-action ui-action--primary w-full sm:w-auto">
                                <i class="fa-solid fa-list-check text-xs"></i> Ouvrir le conducteur
                            </a>

                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-6">
                @if($allDates ?? false)
                    @include('_partials.conducteur-overview-pagination')
                @else
                    @include('_partials.conducteur-edition-pagination')
                @endif
            </div>
        @endif
        </div>
    </div>
@if($conducteurs->isEmpty() && !($allDates ?? false))
    <div class="px-4 pb-6">@include('_partials.conducteur-edition-pagination')</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function getCardFromBtn(btn) {
        return btn.closest('article[data-conducteur-id]');
    }

    async function supprimerConducteur(card) {
        if (!card) return;
        const id = card.getAttribute('data-conducteur-id');
        const titre = card.getAttribute('data-conducteur-titre') || ('Conducteur #'+id);
        const date = card.getAttribute('data-conducteur-date') || '';
        const nbSujets = parseInt(card.getAttribute('data-conducteur-nb-sujets') || '0', 10);
        const urlDelete = card.getAttribute('data-url-delete');
        if (!urlDelete) return;

        const nbSujetsTxt = nbSujets > 0
            ? `<strong class="text-error">${nbSujets} sujet${nbSujets > 1 ? 's' : ''}</strong> actuellement rattaché${nbSujets > 1 ? 's' : ''} à ce conducteur`
            : `Ce conducteur ne contient aucun sujet`;

        const result = await window.TvAlert.confirmDeleteConducteur(
            `Supprimer le conducteur « ${titre} » ?`,
            `
            <div class="text-left space-y-3">
                <p>Date d'édition : <strong>${date || '—'}</strong></p>
                <p class="bg-error/5 border border-error/15 rounded-lg p-3">
                    ⚠️ ${nbSujetsTxt}.<br>
                    <strong class="text-error">Ces sujets seront définitivement supprimés en même temps que le conducteur.</strong>
                </p>
                <p class="italic text-on-surface-variant/80">
                    Cette action est <strong class="text-error">irréversible</strong> et ne peut pas être annulée.
                </p>
            </div>
            `
        );

        if (!result || !result.isConfirmed) return;

        try {
            const resp = await fetch(urlDelete, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken()
                },
                credentials: 'same-origin'
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data.ok) {
                throw new Error(data.message || `Erreur ${resp.status}`);
            }
            await window.TvAlert.success(data.message || 'Conducteur supprimé.', {
                title: 'Conducteur supprimé ✅',
                timer: 1800
            });
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.reload();
            }
        } catch (err) {
            console.error('Erreur suppression conducteur:', err);
            window.TvAlert.error(err && err.message ? err.message : 'Une erreur est survenue lors de la suppression.');
        }
    }

    function bindAll() {
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('button.btn-delete-conducteur');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            const card = getCardFromBtn(btn);
            supprimerConducteur(card);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindAll);
    } else {
        bindAll();
    }
})();
</script>
@endpush
