@extends('layouts.app')
@php
    $pageTitle = 'Conducteurs · Émission';
    $pageSubtitle = $selectedEmission ? 'Programme : '.$selectedEmission->nom : 'Préparation du déroulé et suivi de l’antenne';
    $createEmissionUrl = route('conducteurs.emission.create', array_merge($editionFilters, ['date' => $dateEdition?->toDateString() ?? today()->toDateString()]));
@endphp
@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-2xl text-sm text-on-surface-variant">
            @if($selectedEmission)
                Conducteurs du programme <strong>{{ $selectedEmission->nom }}</strong>, classés par date d’édition.
                <a href="{{ route('conducteurs.emission.index') }}" class="ml-2 text-primary underline">Tous les conducteurs d’émission</a>
            @else
                Préparez les séquences, les horaires et les intervenants de chaque émission, par date d’édition.
            @endif
        </p>
        @can('create', \App\Models\Conducteur::class)
            <a href="{{ $createEmissionUrl }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-container">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Nouveau conducteur d'émission
            </a>
        @endcan
    </div>
    @include('_partials.conducteur-edition-selector')

    <div class="p-4 md:p-6 max-w-[1400px] mx-auto">
        @if($conducteurs->count() === 0)
            <div class="p-16 text-center bg-white border border-outline/10 rounded-xl shadow-sm">
                <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-tv"></i>
                </div>
                <p class="text-lg italic text-secondary mb-3">Aucun conducteur d'émission pour cette date.</p>
                <p class="text-sm text-on-surface-variant max-w-md mx-auto">
                    Choisissez une autre date pour retrouver vos conducteurs.
                    @can('create', \App\Models\Conducteur::class)
                        Utilisez « Nouveau conducteur d'émission » pour préparer cette édition.
                    @endcan
                </p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-outline/10 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b border-outline/10 bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                                <th class="px-5 py-3 font-semibold">Conducteur</th>
                                <th class="px-5 py-3 font-semibold">Date</th>
                                <th class="px-5 py-3 font-semibold">Présentateur</th>
                                <th class="px-5 py-3 font-semibold">Statut</th>
                                <th class="px-5 py-3 text-right font-semibold">Séquences</th>
                                <th class="px-5 py-3 font-semibold">Durée</th>
                                <th class="px-5 py-3 font-semibold">Progression</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline/10">
                            @foreach($conducteurs as $c)
                                @continue((string) $c->type !== 'emission')
                                @php
                                    $totalSeq = $c->sequences->count();
                                    $progression = (int) ($c->progression_percent ?? 0);
                                    $titreConducteur = trim((string) ($c->nom ?? '')) !== ''
                                        ? $c->nom
                                        : (optional($c->emission)->nom ?? 'Conducteur '.$c->code_conducteur_affichage);
                                    $codeC = (string) $c->code_conducteur_affichage;
                                @endphp
                                <tr class="group transition-colors hover:bg-surface-container-low/60"
                                    data-conducteur-id="{{ $c->id }}"
                                    data-conducteur-code="{{ e($codeC) }}"
                                    data-conducteur-titre="{{ e($titreConducteur) }}"
                                    data-conducteur-date="{{ optional($c->date_edition)->format('d/m/Y') }}"
                                    data-conducteur-nb-sujets="{{ $totalSeq }}"
                                    data-url-delete="{{ route('conducteurs.emission.destroy', $c) }}">
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center gap-1 rounded-md border border-primary/20 bg-primary/10 px-2 py-1 text-xs font-bold tracking-wider text-primary">
                                                <i class="fa-solid fa-fingerprint" aria-hidden="true"></i>{{ $codeC }}
                                            </span>
                                            {!! $c->type_badge_html !!}
                                        </div>
                                        <div class="mt-1.5 font-bold text-on-surface">{{ $titreConducteur }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-on-surface-variant">{{ optional($c->date_edition)->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-5 py-4 text-on-surface-variant">
                                        <span class="inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-microphone-lines text-xs text-primary" aria-hidden="true"></i>
                                            <span class="truncate">{{ optional($c->presentateur)->nom_complet ?? 'Non assigné' }}</span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">{!! $c->statut_badge_html !!}</td>
                                    <td class="px-5 py-4 text-right font-mono font-bold tabular-nums text-on-surface">{{ $totalSeq }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-mono tabular-nums text-on-surface-variant">{{ $c->total_duration_hm }}</td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-24 overflow-hidden rounded-full bg-surface-container-high">
                                                <div class="h-full rounded-full bg-primary" style="width:{{ $progression }}%"></div>
                                            </div>
                                            <span class="font-mono text-xs font-bold text-blue-700">{{ $progression }} %</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('conducteurs.emission.show', $c) }}"
                                               class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-primary-container hover:text-primary">
                                                <i class="fa-solid fa-list-check" aria-hidden="true"></i> Ouvrir
                                            </a>
                                            @can('delete', $c)
                                                <button type="button"
                                                        class="btn-delete-conducteur inline-flex h-10 w-10 items-center justify-center rounded-lg border border-error/20 text-error transition-colors hover:bg-error hover:text-white"
                                                        title="Supprimer ce conducteur et ses séquences" aria-label="Supprimer">
                                                    <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-8">
                @include('_partials.conducteur-edition-pagination')
            </div>
        @endif
    </div>
@if($conducteurs->isEmpty())<div class="px-4 pb-6">@include('_partials.conducteur-edition-pagination')</div>@endif
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
        return btn.closest('[data-conducteur-id]');
    }

    async function supprimerConducteur(card) {
        if (!card) return;
        const id = card.getAttribute('data-conducteur-id');
        const code = card.getAttribute('data-conducteur-code') || ('Conducteur #' + id);
        const titre = card.getAttribute('data-conducteur-titre') || '';
        const date = card.getAttribute('data-conducteur-date') || '';
        const nbSeq = parseInt(card.getAttribute('data-conducteur-nb-sujets') || '0', 10);
        const urlDelete = card.getAttribute('data-url-delete');
        if (!urlDelete) return;

        const nbSeqTxt = nbSeq > 0
            ? `<strong class="text-error">${nbSeq} séquence${nbSeq > 1 ? 's' : ''}</strong> actuellement rattachée${nbSeq > 1 ? 's' : ''} à ce conducteur`
            : `Ce conducteur ne contient aucune séquence`;

        const result = await window.TvAlert.confirmDeleteConducteur(
            `Supprimer le conducteur ${code} « ${titre} » ?`,
            `
            <div class="text-left space-y-3">
                <p>Date d'édition : <strong>${date || '—'}</strong></p>
                <p class="bg-error/5 border border-error/15 rounded-lg p-3">
                    ⚠️ ${nbSeqTxt}.<br>
                    <strong class="text-error">Ces séquences seront définitivement supprimées en même temps que le conducteur.</strong>
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
            supprimerConducteur(getCardFromBtn(btn));
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
