@extends('layouts.app')
@section('content')

@php
    $nomConducteur = trim($conducteur->nom ?? '') !== ''
        ? $conducteur->nom
        : (optional($conducteur->emission)->nom ?? 'Conducteur #'.$conducteur->id);
    $dateEdition = $conducteur->date_edition ? $conducteur->date_edition->format('d/m/Y') : now()->format('d/m/Y');
    $statutBadge = $conducteur->statut_badge_html;
    $totalDuree = $conducteur->total_duration_hm ?? '00:00';
    $user = auth()->user();
    $defaultNextPos = count($sujets) + 1;
    $emissionOptions = $emissions->map(fn ($n, $id) => ['value' => (int)$id, 'label' => (string)$n])->values()->all();
    // ⚠️ $jrOptions est construit côté contrôleur : il ne contient QUE les
    //    journalistes PRÉSENTS à la réunion de rédaction (voir ConducteurPresence).
    //    Ne pas le recalculer ici à partir de $journalistes (trop permissif).
    if (! isset($jrOptions) || ! is_array($jrOptions)) { $jrOptions = []; }
    $typeOptions = collect($typeOptions)->map(fn ($label, $value) => ['value' => (string)$value, 'label' => (string)$label])->values()->all();
@endphp

<div x-data="conducteurRedactionVrai()"
     x-on:presence:changed.window="presenceTotal = ($event.detail && Number.isFinite(+$event.detail.total)) ? +$event.detail.total : presenceTotal; presenceTotalJournalistes = ($event.detail && Number.isFinite(+$event.detail.totalJournalistes)) ? +$event.detail.totalJournalistes : presenceTotalJournalistes"
     x-init='init({
        conducteurId: {{ $conducteur->id }},
        csrfToken: @json(csrf_token()),
        canCrudRedac: @json($canCrudRedac),
        defaultNextPos: {{ $defaultNextPos }},
        typeOptions: @json($typeOptions),
        jrOptions: @json($jrOptions),
        emissionOptions: @json($emissionOptions),
        progression: {{ $progression }},
        presenceTotal: {{ count($presences) }},
        presenceTotalJournalistes: {{ (int) $totalJournalistes }},
        routes: {
            index: @json(route('conducteurs.redaction.index')),
            chefEdition: @json(route('conducteurs.chef_edition', $conducteur->id)),
            presentateur: @json(optional($conducteur->id) ? route('conducteurs.presentateur.show', $conducteur->id) : route('conducteurs.presentateur.index')),
            workflowNextBase: @json(route('conducteurs.sequences.workflow_next', '_SEQ_')),
            storeSujet: @json(route('conducteurs.redaction.sujet.store', $conducteur)),
            updateSujetBase: @json(route('conducteurs.redaction.sujet.update', ['conducteur' => $conducteur, 'sequence' => '_SEQ_'])),
            destroySujetBase: @json(route('conducteurs.redaction.sujet.destroy', ['conducteur' => $conducteur, 'sequence' => '_SEQ_'])),
            reorder: @json(route('conducteurs.redaction.sujet.reorder', $conducteur)),
            liveState: @json(route('conducteurs.redaction.live-state', $conducteur)),
        },
        sujets: @json($sujets),
    })'
     @keydown.escape.window="closeModal()"
     class="flex flex-col gap-6 h-full">

    {{-- HEADER STATS BAR (progression + 3 stats) --}}
    <header class="bg-white border-b border-outline/10 px-5 py-5 shadow-sm">
        <div class="max-w-[1800px] mx-auto flex flex-col gap-5">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div>
                    <div class="flex items-center gap-3 text-xs text-on-surface-variant uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-newspaper text-primary"></i>
                        <span>Conducteurs · Rédaction</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold text-on-surface">
                        Suivi production — <span class="text-primary">{{ $nomConducteur }}</span>
                        <span class="text-on-surface-variant font-normal text-lg">· {{ $dateEdition }}</span>
                    </h1>
                    <p class="text-sm text-on-surface-variant mt-2 max-w-3xl">
                        Suivez chaque sujet de la production au diffusion : cliquez sur une étape
                        <span class="font-semibold text-primary">Terrain · Montage · Habillage · Livraison · Diffusion</span>
                        pour la faire progresser.
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('conducteurs.redaction.index') }}"
                       class="inline-flex items-center gap-2 px-3.5 py-2 text-sm border border-outline/30 rounded-lg hover:bg-surface-container transition-colors text-on-surface">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Tous conducteurs
                    </a>
                    <a href="{{ route('conducteurs.chef_edition', $conducteur) }}"
                       class="inline-flex items-center gap-2 px-3.5 py-2 text-sm border border-outline/30 rounded-lg hover:bg-surface-container transition-colors text-on-surface">
                        <i class="fa-solid fa-pen-to-square text-xs"></i> Timeline Chef Édition
                    </a>
                    <a href="{{ route('conducteurs.presentateur.show', $conducteur) }}"
                       class="inline-flex items-center gap-2 px-3.5 py-2 text-sm border border-outline/30 rounded-lg hover:bg-surface-container transition-colors text-on-surface">
                        <i class="fa-solid fa-microphone-lines text-xs"></i> Vue Présentateur
                    </a>
                    <button type="button"
                            @click="document.dispatchEvent(new CustomEvent('presence:reviser'))"
                            class="inline-flex items-center gap-2 px-3.5 py-2 text-sm border border-primary/30 text-primary rounded-lg hover:bg-primary/10 transition-colors">
                        <i class="fa-solid fa-users text-xs"></i>
                        Présences
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-primary text-white text-xs font-bold"
                              x-text="presenceTotal + ' / ' + presenceTotalJournalistes">0</span>
                        <span class="text-xs opacity-70 font-normal">présents · Réviser</span>
                    </button>
                    <button x-show="canCrudRedac" @click="openCreateSujetModal()"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary-container transition-colors shadow-sm">
                        <i class="fa-solid fa-plus text-xs"></i> Ajouter un sujet
                    </button>
                </div>
            </div>

            {{-- Stats bar --}}
            <div class="flex flex-wrap items-center gap-5">
                <div class="min-w-[120px]">
                    <p class="text-xs uppercase tracking-widest text-secondary mb-1">Sujets</p>
                    <p class="font-bold text-xl text-on-surface" x-text="sujets.length">{{ count($sujets) }}</p>
                </div>
                <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
                <div class="min-w-[120px]">
                    <p class="text-xs uppercase tracking-widest text-secondary mb-1">Livrés</p>
                    <p class="font-bold text-xl text-emerald-600"
                       x-text="sujets.filter(s => s.livre_at_hm).length">{{ $conducteur->sequences()->whereNotNull('livre_at')->count() }}</p>
                </div>
                <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
                <div class="min-w-[90px]">
                    <p class="text-xs uppercase tracking-widest text-secondary mb-1">Statut</p>
                    <div>{!! $statutBadge !!}</div>
                </div>
                <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
                <div class="flex-1 min-w-[260px]">
                    <div class="flex justify-between items-end mb-2">
                        <p class="text-xs uppercase tracking-widest text-secondary">Progression Workflow</p>
                        <p class="font-bold text-blue-700" x-text="progression + '%'">{{ $progression }}%</p>
                    </div>
                    <div class="h-2.5 w-full bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-600 rounded-full transition-all duration-500"
                             :style="{ width: progression + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- TABLEAU 15 COLONNES (#COND · #SEQ · Ordre · Sujet · Angle · Émission · Type · JRI · Terrain · Montage · Habillage · Livraison · Livré le · Diffusion · Actions) --}}
    <section class="max-w-[1900px] mx-auto w-full pb-8" data-bulk-list="bulk-sujets-redaction">
        @if($canCrudRedac)
        @include('_partials.bulk-delete-toolbar', ['resource' => 'sujets-redaction', 'parentId' => $conducteur->id])
        @endif
        <div class="bg-white rounded-2xl border border-outline/10 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm hidden min-[2200px]:table">
                    <thead>
                        <tr class="border-b border-outline-variant bg-surface-container sticky top-0 z-20">
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-14 text-center hidden lg:table-cell">Ordre</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant min-w-[180px] lg:min-w-[260px]">Sujet</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant min-w-[200px] lg:min-w-[300px] hidden lg:table-cell">Angle éditorial</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant min-w-[180px] hidden lg:table-cell">Émission</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-32">Type</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant min-w-[180px]">Journaliste · JRI</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-28 text-center">Terrain</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-28 text-center">Montage</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-28 text-center">Habillage</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-28 text-center">Livraison</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-primary w-32 text-center">🕒 Livré le</th>
                            <th class="p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-28 text-center">Diffusion</th>
                            <th class="sticky right-0 top-0 z-30 bg-surface-container p-3.5 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-20 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="redaction-tbody">

                        <template x-if="sujets.length === 0">
                            <tr>
                                <td colspan="13" class="p-16 text-center text-secondary italic bg-surface-container-lowest">
                                    Aucun sujet dans ce conducteur de rédaction.
                                    <span x-show="canCrudRedac">
                                        Cliquez sur <span class="font-semibold text-primary">« Ajouter un sujet »</span> pour commencer.
                                    </span>
                                </td>
                            </tr>
                        </template>

                        <template x-for="(s, i) in sujets" :key="s.id">
                            <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors group"
                                :data-sujet-id="s.id"
                                :data-conducteur-id="s.conducteur_id">

                                <td class="p-3 text-center font-mono font-semibold text-on-surface-variant hidden lg:table-cell"
                                    :title="'Conducteur '+s.code_conducteur+' · Ligne '+s.code_ligne"
                                    x-text="i+1">1</td>

                                <td class="p-3">
                                    <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 text-sm text-primary" @click.stop @mousedown.stop><input type="checkbox" data-bulk-item form="bulk-sujets-redaction" :value="s.id" :disabled="!(s.can_delete_redaction)" :aria-label="'Sélectionner '+ s.title" class="h-5 w-5 rounded border-outline-variant text-primary"><span class="sr-only">Sélectionner</span></label>
                                    <div class="flex items-start gap-2 max-w-[320px]">
                                        <i x-show="!s.angle_truncated && !s.angle_editorial" class="fa-solid fa-circle-exclamation text-xs text-amber-500 mt-1" title="Angle éditorial vide"></i>
                                        <span class="font-semibold text-on-surface"
                                              :class="s.title.length > 80 ? 'text-sm' : ''"
                                              :title="s.title"
                                              x-text="s.title_truncated || s.title">Titre sujet long…</span>
                                    </div><div x-show="s.shared_dossier_url"><a :href="s.shared_dossier_url" class="inline-flex min-h-11 items-center text-sm text-primary underline" @click.stop>Dossier partagé</a>
                                    </div>
                                </td>

                                <td class="p-3 hidden lg:table-cell">
                                    <p class="text-xs text-on-surface-variant italic leading-relaxed line-clamp-2"
                                       :title="s.angle_editorial || 'Aucun angle renseigné'"
                                       x-text="s.angle_truncated || s.angle_editorial || '—'">—</p>
                                </td>

                                <td class="p-3 text-xs font-medium text-on-surface hidden lg:table-cell">
                                    <template x-if="s.emission_nom">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-primary-fixed/10 text-primary text-xs font-semibold border border-primary/15">
                                            <i class="fa-solid fa-tv text-xs"></i>
                                            <span x-text="s.emission_nom">JT 20h</span>
                                        </span>
                                    </template>
                                    <template x-if="!s.emission_nom">
                                        <span class="italic text-on-surface-variant opacity-60 text-xs">— sans émission —</span>
                                    </template>
                                </td>

                                <td class="p-3" x-html="s.type_badge_html"></td>

                                <td class="p-3 text-sm text-on-surface" x-html="s.journaliste_nom || '—'">—</td>

                                @foreach (['statut_terrain','statut_montage','statut_habillage','statut_livraison'] as $wfcol)
                                <td class="p-3 text-center" @click.stop>
                                    <div @click="clicWorkflow(s, '{{ str_replace('statut_','',$wfcol) }}')"
                                         class="cursor-pointer inline-block select-none"
                                         :class="s.can_workflow.{{$wfcol}} ? '' : 'pointer-events-none'"
                                         x-html="s.{{$wfcol}}_btn"></div>
                                </td>
                                @endforeach

                                <td class="p-3 text-center">
                                    <template x-if="s.livre_at_hm">
                                        <span class="inline-block px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100 shadow-sm"
                                              x-text="s.livre_at_hm"
                                              :title="'Livré le '+s.livre_at_hm">11/09 16:42</span>
                                    </template>
                                    <template x-if="!s.livre_at_hm">
                                        <span class="text-xs italic text-on-surface-variant opacity-60">En attente</span>
                                    </template>
                                </td>

                                <td class="p-3 text-center" @click.stop>
                                    <div @click="clicWorkflow(s, 'diffusion')"
                                         class="cursor-pointer inline-block select-none"
                                         :class="s.can_workflow.statut_diffusion ? '' : 'pointer-events-none'"
                                         x-html="s.statut_diffusion_btn"></div>
                                </td>

                                <td class="sticky right-0 bg-white p-3 text-center">
                                    <div x-show="canCrudRedac" class="flex gap-2 justify-center opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                        <button @click="openEditSujetModal(s)"
                                                title="Modifier le sujet"
                                                class="text-secondary hover:text-primary transition-colors px-1">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </button>
                                        <button @click="confirmDeleteSujet(s)"
                                                title="Supprimer le sujet"
                                                class="text-secondary hover:text-error transition-colors px-1">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div id="redaction-cards" class="min-[2200px]:hidden p-3 sm:p-4 space-y-3">
                <p x-show="sujets.length === 0" class="py-8 text-center text-sm text-on-surface-variant">
                    Aucun sujet dans ce conducteur de rédaction.
                </p>
                <template x-for="(s, i) in sujets" :key="`redaction-card-${s.id}`">
                    <article class="rounded-xl border border-outline/20 bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-container text-sm font-bold text-on-surface-variant"
                                  x-text="i + 1"></span>
                            <div class="min-w-0 flex-1">
                                <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 text-sm text-primary" @click.stop @mousedown.stop><input type="checkbox" data-bulk-item form="bulk-sujets-redaction" :value="s.id" :disabled="!(s.can_delete_redaction)" :aria-label="'Sélectionner '+ s.title" class="h-5 w-5 rounded border-outline-variant text-primary"><span class="sr-only">Sélectionner</span></label>
                                <h3 class="font-semibold leading-snug text-on-surface" x-text="s.title_truncated || s.title"></h3>
                                <p class="mt-1 line-clamp-3 text-xs leading-relaxed text-on-surface-variant"
                                   x-text="s.angle_truncated || s.angle_editorial || 'Aucun angle renseigné'"></p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span x-html="s.type_badge_html"></span>
                                    <span x-show="s.emission_nom" class="text-xs font-medium text-primary" x-text="s.emission_nom"></span>
                                </div>
                            </div>
                            <div x-show="canCrudRedac" class="flex shrink-0 items-center gap-1">
                                <button type="button" @click="openEditSujetModal(s)"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-primary hover:bg-primary/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        :aria-label="'Modifier ' + s.title" title="Modifier le sujet">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <button type="button" @click="confirmDeleteSujet(s)"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-error hover:bg-error/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-error"
                                        :aria-label="'Supprimer ' + s.title" title="Supprimer le sujet">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-2 border-t border-outline/10 pt-3 text-xs sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Journaliste · JRI</p>
                                <div class="mt-1 text-on-surface" x-html="s.journaliste_nom || '—'"></div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Livré le</p>
                                <p class="mt-1 font-semibold text-emerald-700" x-text="s.livre_at_hm || 'En attente'"></p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ([
                                'Terrain' => ['statut_terrain', 'terrain'],
                                'Montage' => ['statut_montage', 'montage'],
                                'Habillage' => ['statut_habillage', 'habillage'],
                                'Livraison' => ['statut_livraison', 'livraison'],
                                'Diffusion' => ['statut_diffusion', 'diffusion'],
                            ] as $workflowLabel => $workflow)
                                <div class="min-w-0">
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ $workflowLabel }}</p>
                                    <div @click="clicWorkflow(s, '{{ $workflow[1] }}')"
                                         @keydown.enter.prevent="clicWorkflow(s, '{{ $workflow[1] }}')"
                                         @keydown.space.prevent="clicWorkflow(s, '{{ $workflow[1] }}')"
                                         :class="s.can_workflow.{{ $workflow[0] }} ? '' : 'pointer-events-none opacity-60'"
                                         class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg focus-within:outline focus-within:outline-2 focus-within:outline-primary [&>button]:min-h-11 [&>button]:w-full [&>button]:justify-center"
                                         x-html="s.{{ $workflow[0] }}_btn"></div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                </template>
            </div>
        </div>
    </section>

    {{-- ========================================================= --}}
    {{-- PRÉSENCES À LA RÉUNION DE RÉDACTION (personnes internes)  --}}
    {{-- ========================================================= --}}
    <section class="mt-6"
             id="presences"
             x-data='presencePanel({
                 conferenceStartedAt: @json($conducteur->conference_started_at?->toIso8601String()),
                 timezone: @json(config('app.timezone')),
                 presences: @json($presences),
                 journalistes: @json($journalistes),
                 gestion: @json((bool) $canGererPresences),
                 conducteurId: @json((int) $conducteur->id),
                 totalJournalistes: @json((int) $totalJournalistes),
                 routes: {
                     state: @json(route('conducteurs.redaction.presence.index', $conducteur)),
                     conference: @json(route('conducteurs.redaction.conference.update', $conducteur)),
                     ponctualite: @json(route('conducteurs.redaction.presence.ponctualite', ['conducteur' => $conducteur->id, 'presence' => '__PRESENCE__'])),
                     store:  @json(route('conducteurs.redaction.presence.store',  ['conducteur' => $conducteur->id])),
                     reconcile: @json(route('conducteurs.redaction.presence.reconcile', ['conducteur' => $conducteur->id])),
                     update: @json(route('conducteurs.redaction.presence.update', ['conducteur' => $conducteur->id, 'presence' => '__PRESENCE__'])),
                     destroy:@json(route('conducteurs.redaction.presence.destroy',['conducteur' => $conducteur->id, 'presence' => '__PRESENCE__'])),
                 },
                 csrf: @json(csrf_token()),
             })'
             x-cloak>
        <div class="bg-white border border-outline/10 rounded-xl shadow-sm overflow-hidden">
            <header class="px-5 py-4 border-b border-outline/10 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-on-surface flex items-center gap-2">
                            Présences · Réunion de rédaction
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-primary/10 text-primary text-xs font-bold"
                                  x-text="presences.length + ' / ' + totalJournalistes + ' présents'"></span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold"
                                  :class="progressionPresences >= 60 ? 'bg-green-100 text-green-800' : 'bg-surface-container text-on-surface-variant'"
                                  x-text="progressionPresences + ' %'"></span>
                        </h2>
                        <p class="text-xs text-on-surface-variant">
                            Personnes <strong>internes</strong> présentes — date &amp; heure d'arrivée.
                            La ponctualité est pointée manuellement après le début de la conférence.
                        </p>
                        <div class="mt-1.5 flex items-center gap-2 max-w-[260px]">
                            <div class="h-1.5 flex-1 bg-surface-container-high rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                     :class="progressionPresences >= 60 ? 'bg-green-500' : 'bg-primary'"
                                     :style="'width:' + progressionPresences + '%'"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            x-show="gestion"
                            @click="ouvrirRevision()"
                            class="inline-flex items-center gap-2 px-4 py-2 border border-outline/25 text-on-surface-variant text-sm font-semibold rounded-lg hover:bg-surface-container hover:text-primary transition-colors">
                        <i class="fa-solid fa-list-check text-xs"></i> Réviser la liste
                    </button>
                    <button type="button"
                            x-show="gestion"
                            @click="ouvrirFormAjout()"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-container transition-colors">
                        <i class="fa-solid fa-plus text-xs"></i> Ajouter une présence
                    </button>
                </div>
            </header>

            <div class="border-b border-outline/10 bg-slate-50 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-primary">Début effectif de la conférence</h3>
                        <p class="mt-1 text-sm text-on-surface-variant" x-text="conferenceLabel"></p>
                        <p class="mt-1 text-xs text-on-surface-variant">Choisissez le statut de chaque personne d’après le début indiqué.</p>
                    </div>
                    <button type="button" x-show="gestion && !conferenceStartedAt" @click="enregistrerDebut(true)" :disabled="conferenceBusy" class="min-h-[44px] rounded-lg bg-primary px-4 text-sm font-semibold text-white disabled:opacity-50">Démarrer la conférence maintenant</button>
                </div>
                <form x-show="gestion" @submit.prevent="enregistrerDebut(false)" class="mt-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="conference-started-at" class="mb-1 block text-xs font-semibold text-on-surface-variant">Date et heure de début · {{ config('app.timezone') }}</label>
                        <input id="conference-started-at" type="datetime-local" x-model="conferenceForm" @input="conferenceDirty = true" required class="min-h-[44px] max-w-full rounded-lg border border-outline/20 bg-white px-3 text-sm">
                    </div>
                    <button type="submit" :disabled="conferenceBusy || !conferenceForm" class="min-h-[44px] rounded-lg border border-outline/25 bg-white px-4 text-sm font-semibold text-primary disabled:opacity-50">Enregistrer le début</button>
                </form>
                <p x-show="gestion && conferenceStartedAt" class="mt-2 text-xs text-on-surface-variant">Après correction du début, vérifiez les pointages déjà saisis.</p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold" aria-live="polite">
                    <span class="rounded-full bg-green-100 px-3 py-2 text-green-800" x-text="pointageCounts.aLHeure + ' à l’heure'"></span>
                    <span class="rounded-full bg-amber-100 px-3 py-2 text-amber-800" x-text="pointageCounts.enRetard + ' en retard'"></span>
                    <span class="rounded-full bg-slate-200 px-3 py-2 text-slate-700" x-text="pointageCounts.aPointer + ' à pointer'"></span>
                </div>
            </div>

            {{-- Formulaire d'ajout / modification --}}
            <div x-show="formOuvert" x-transition class="px-5 py-4 bg-surface-container-lowest border-b border-outline/10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                    {{-- Recherche personne --}}
                    <div class="lg:col-span-6 relative">
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                            Personne <span class="text-error">*</span>
                        </label>
                        <input type="text"
                               x-model="form.recherche"
                               @focus="form.listeOuverte = true"
                               @click.outside="form.listeOuverte = false"
                               :disabled="modeEdition"
                               placeholder="Rechercher un journaliste…"
                               class="w-full px-3 py-2 rounded-lg border border-outline/20 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-60">
                        <div x-show="form.listeOuverte && form.recherche.trim().length > 0"
                             x-transition
                             class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto border border-outline/20 rounded-lg bg-white shadow-lg">
                            <template x-for="j in resultats" :key="j.id">
                                <button type="button"
                                        @click="choisir(j)"
                                        :disabled="dejaPresent(j.id)"
                                        class="w-full text-left px-3 py-2 hover:bg-primary/5 text-sm flex items-center justify-between gap-2 border-b border-outline/10 last:border-0 disabled:opacity-40">
                                    <span class="min-w-0">
                                        <span class="font-medium text-on-surface block truncate" x-text="j.nom_complet"></span>
                                        <span class="text-xs text-on-surface-variant" x-text="j.fonction + ' · ' + j.pole"></span>
                                    </span>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full shrink-0"
                                          :class="dejaPresent(j.id) ? 'bg-surface-container text-on-surface-variant' : 'bg-primary/10 text-primary'"
                                          x-text="dejaPresent(j.id) ? 'déjà présent' : 'choisir'"></span>
                                </button>
                            </template>
                            <div x-show="resultats.length === 0" class="px-3 py-4 text-center text-xs text-on-surface-variant italic">
                                Aucun résultat.
                            </div>
                        </div>
                        <div x-show="form.journaliste_id" class="mt-1.5 text-xs text-primary font-medium">
                            <i class="fa-solid fa-circle-check mr-1"></i>
                            <span x-text="form.nom_complet"></span>
                        </div>
                    </div>

                    {{-- Date & heure d'arrivée --}}
                    <div class="lg:col-span-4">
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                            <i class="fa-regular fa-clock mr-1"></i>Date &amp; heure d'arrivée <span class="text-error">*</span>
                        </label>
                        <input type="datetime-local"
                               x-model="form.arrivee_at"
                               class="w-full px-3 py-2 rounded-lg border border-outline/20 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                    </div>

                    {{-- Actions --}}
                    <div class="lg:col-span-2 flex items-end gap-2">
                        <button type="button"
                                @click="enregistrer()"
                                :disabled="! form.journaliste_id || ! form.arrivee_at || chargement"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-container transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i :class="chargement ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-check'" class="text-xs"></i>
                            <span x-text="modeEdition ? 'Modifier' : 'Ajouter'"></span>
                        </button>
                        <button type="button"
                                @click="fermerForm()"
                                class="px-3 py-2 rounded-lg border border-outline/25 text-on-surface-variant text-sm hover:bg-surface-container transition-colors">
                            Annuler
                        </button>
                    </div>
                </div>
            </div>

            {{-- Liste des présences --}}
            @if(collect($presences)->contains(fn ($presence) => $presence['can_delete'] ?? false))
                @include('_partials.bulk-delete-toolbar', ['resource' => 'presences-conference', 'parentId' => $conducteur->id, 'actionLabel' => 'Retirer', 'explanation' => 'Les pointages cochés seront retirés de cette conférence. Les fiches des journalistes seront conservées.'])
            @endif
            <div x-show="presences.length > 0" class="overflow-x-auto" data-bulk-list="bulk-presences-conference">
                <table data-mobile-cards class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-outline/10 bg-surface-container-lowest">
                            <th class="px-4 py-3 text-xs uppercase tracking-wider font-semibold text-on-surface-variant w-12">#</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wider font-semibold text-on-surface-variant">Nom &amp; Prénom</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wider font-semibold text-on-surface-variant">Fonction</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wider font-semibold text-on-surface-variant">Pôle</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wider font-semibold text-on-surface-variant">
                                <i class="fa-regular fa-clock mr-1"></i>Arrivée
                            </th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wider font-semibold text-on-surface-variant">Ponctualité · pointage manuel</th>
                            <th class="px-4 py-3 text-xs uppercase tracking-wider font-semibold text-on-surface-variant text-right" x-show="gestion">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(p, i) in presences" :key="p.id">
                            <tr class="border-b border-outline/8 hover:bg-surface-container-lowest/60 transition-colors group">
                                <td class="px-4 py-3">
                                    <span class="w-7 h-7 rounded-full bg-primary/10 text-primary inline-flex items-center justify-center text-xs font-bold"
                                          x-text="i + 1"></span>
                                </td>
                                <td class="px-4 py-3 font-medium text-on-surface">
                                    <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 text-sm text-primary" @click.stop>
                                        <input type="checkbox" data-bulk-item form="bulk-presences-conference" :value="p.id" :disabled="!p.can_delete" :aria-label="'Sélectionner la présence de '+p.nom_complet" class="h-5 w-5 rounded border-outline-variant text-primary">
                                        <span class="sr-only">Sélectionner</span>
                                    </label>
                                    <span x-text="p.nom_complet"></span>

                                </td>
                                <td class="px-4 py-3 text-on-surface-variant" x-text="p.fonction"></td>
                                <td class="px-4 py-3 text-on-surface-variant" x-text="p.pole"></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono tabular-nums text-sm font-semibold text-primary"
                                              x-text="p.heure_arrivee_hm"></span>
                                        <span class="text-xs text-on-surface-variant"
                                              x-text="'(' + p.date_arrivee_fr + ')'"></span>

                                    </div>
                                </td>
                                <td class="px-4 py-3 min-w-[220px]">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold" :class="p.ponctualite === 'a_l_heure' ? 'bg-green-100 text-green-800' : (p.ponctualite === 'en_retard' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')" x-text="p.ponctualite_label || 'À pointer'"></span>
                                    <div x-show="gestion && conferenceStartedAt" class="mt-2 flex flex-wrap items-center gap-1">
                                        <button type="button" @click="pointer(p, 'a_l_heure')" :disabled="pointageBusy" :aria-pressed="p.ponctualite === 'a_l_heure'" class="min-h-[44px] rounded-lg border border-outline/20 px-2 text-xs text-primary disabled:opacity-50">À l’heure</button>
                                        <button type="button" @click="pointer(p, 'en_retard')" :disabled="pointageBusy" :aria-pressed="p.ponctualite === 'en_retard'" class="min-h-[44px] rounded-lg border border-outline/20 px-2 text-xs text-primary disabled:opacity-50">En retard</button>
                                        <button type="button" x-show="p.ponctualite" @click="pointer(p, null)" :disabled="pointageBusy" class="min-h-[44px] px-2 text-xs text-on-surface-variant underline disabled:opacity-50">Effacer</button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap" x-show="gestion">
                                    <div class="inline-flex items-center gap-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                        <button type="button"
                                                @click="ouvrirFormEdition(p)"
                                                class="w-11 h-11 rounded-lg text-on-surface-variant hover:text-primary hover:bg-primary/10 transition-colors inline-flex items-center justify-center"
                                                title="Modifier l'heure d'arrivée">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                        <button type="button"
                                                @click="supprimer(p)"
                                                class="w-11 h-11 rounded-lg text-on-surface-variant hover:text-error hover:bg-error/10 transition-colors inline-flex items-center justify-center"
                                                title="Retirer de la liste des présents">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- État vide --}}
            <div x-show="presences.length === 0" class="px-6 py-12 text-center">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-xl">
                    <i class="fa-solid fa-users"></i>
                </div>
                <p class="text-on-surface-variant text-sm">
                    Aucune présence enregistrée pour cette réunion de rédaction.
                </p>
                <button type="button"
                        x-show="gestion"
                        @click="ouvrirRevision()"
                        class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-container transition-colors">
                    <i class="fa-solid fa-list-check text-xs"></i> Renseigner la liste des présents
                </button>
                <button type="button"
                        x-show="gestion"
                        @click="ouvrirFormAjout()"
                        class="mt-2 ml-2 inline-flex items-center gap-2 px-5 py-2.5 border border-outline/25 text-on-surface-variant text-sm font-semibold rounded-lg hover:bg-surface-container transition-colors">
                    <i class="fa-solid fa-plus text-xs"></i> Ajouter une présence
                </button>
            </div>
        </div>
    </section>

    {{-- ========================================================= --}}
    {{-- MODALE : RÉVISER LA LISTE DES PRÉSENTS                    --}}
    {{-- ========================================================= --}}
    <template x-if="modalRevision">
        <div x-cloak class="fixed inset-0 z-[110] flex items-center justify-center p-4"
             x-transition.opacity.duration.200ms
             @keydown.escape.window="fermerRevision()">
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px]" @click.self="fermerRevision()"></div>

            <div class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl border border-outline/10 max-h-[92vh] overflow-hidden flex flex-col"
                 x-transition.scale.origin.center.duration.200ms>

                <header class="px-6 py-5 border-b border-outline/10">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-on-surface flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-primary"></i>
                                Réviser la liste des présents
                            </h3>
                            <p class="text-sm text-on-surface-variant mt-1">
                                Ajoutez, modifiez les heures ou retirez des personnes. La liste enregistrée devient
                                <strong>la référence</strong> pour l'assignation des sujets.
                            </p>
                        </div>
                        <button @click="fermerRevision()"
                                class="p-2.5 min-w-[44px] min-h-[44px] rounded-lg hover:bg-surface-container text-secondary transition-colors">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </header>

                <div class="flex-1 overflow-y-auto p-6 space-y-5">

                    {{-- Recherche / ajout --}}
                    <div class="relative">
                        <label class="block text-xs font-semibold text-on-surface-variant mb-1.5">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i>Ajouter une personne
                        </label>
                        <input type="text"
                               x-model="revisionRecherche"
                               @focus="revisionListeOuverte = true"
                               @click.outside="revisionListeOuverte = false"
                               placeholder="Rechercher un journaliste (nom, fonction, pôle)…"
                               class="w-full px-4 py-3 rounded-lg border border-outline/20 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">

                        <div x-show="revisionListeOuverte && revisionRecherche.trim().length > 0"
                             x-transition
                             class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto border border-outline/20 rounded-lg bg-white shadow-lg">
                            <template x-for="j in revisionResultats" :key="j.id">
                                <button type="button"
                                        @click="revisionAjouter(j)"
                                        :disabled="revisionDejaLa(j.id)"
                                        class="w-full text-left px-4 py-2.5 hover:bg-primary/5 text-sm flex items-center justify-between gap-2 border-b border-outline/10 last:border-0 disabled:opacity-40">
                                    <span class="min-w-0">
                                        <span class="font-medium text-on-surface block truncate" x-text="j.nom_complet"></span>
                                        <span class="text-xs text-on-surface-variant" x-text="j.fonction + ' · ' + j.pole"></span>
                                    </span>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full shrink-0"
                                          :class="revisionDejaLa(j.id) ? 'bg-surface-container text-on-surface-variant' : 'bg-primary/10 text-primary'"
                                          x-text="revisionDejaLa(j.id) ? 'déjà dans la liste' : '+ ajouter'"></span>
                                </button>
                            </template>
                            <div x-show="revisionResultats.length === 0"
                                 class="px-4 py-4 text-center text-xs text-on-surface-variant italic">
                                Aucune personne ne correspond.
                            </div>
                        </div>
                    </div>

                    {{-- Liste de travail --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs uppercase tracking-wider text-on-surface-variant font-semibold">
                                Liste de travail — <span x-text="brouillon.length"></span> personne(s)
                            </span>
                            <button type="button"
                                    x-show="brouillon.length > 0"
                                    @click="brouillon = []"
                                    class="text-xs text-error hover:underline inline-flex items-center gap-1">
                                <i class="fa-solid fa-trash-can"></i> Tout retirer
                            </button>
                        </div>

                        <div x-show="brouillon.length === 0"
                             class="rounded-lg border border-dashed border-outline/25 p-6 text-center text-sm text-on-surface-variant">
                            <i class="fa-solid fa-user-group text-on-surface-variant/50 text-xl mb-2"></i>
                            <p>Aucune personne dans la liste.</p>
                            <p class="text-xs opacity-75 mt-1">Utilisez la recherche ci-dessus pour en ajouter.</p>
                        </div>

                        <div class="space-y-2">
                            <template x-for="(ligne, idx) in brouillon" :key="ligne.journaliste_id">
                                <div class="flex flex-wrap items-center gap-3 p-3 rounded-lg border border-outline/15 bg-surface-container-lowest">
                                    <span class="w-7 h-7 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold shrink-0"
                                          x-text="idx + 1"></span>

                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-on-surface truncate" x-text="ligne.nom_complet"></div>
                                        <div class="text-xs text-on-surface-variant truncate">
                                            <span x-text="ligne.fonction"></span> · <span x-text="ligne.pole"></span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-on-surface-variant whitespace-nowrap">
                                            <i class="fa-regular fa-clock mr-1"></i>Arrivée
                                        </label>
                                        <input type="datetime-local"
                                               x-model="ligne.arrivee_at"
                                               class="px-3 py-1.5 rounded-md border border-outline/20 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                                        <button type="button" @click="revisionRetirer(ligne.journaliste_id)"
                                                class="w-11 h-11 rounded-md text-error hover:bg-error/10 transition-colors inline-flex items-center justify-center"
                                                title="Retirer de la liste">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <footer class="px-6 py-4 border-t border-outline/10 bg-surface-container-lowest flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs text-on-surface-variant flex items-start gap-2 max-w-md">
                        <i class="fa-solid fa-circle-info mt-0.5 text-primary"></i>
                        <span>
                            Les personnes retirées ne seront <strong>plus proposées</strong> à l'assignation des sujets.
                            Les affectations existantes restent visibles.
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="fermerRevision()"
                                class="px-5 py-2 rounded-lg border border-outline/25 text-on-surface-variant text-sm hover:bg-surface-container transition-colors">
                            Annuler
                        </button>
                        <button type="button"
                                @click="enregistrerRevision()"
                                :disabled="chargementRevision"
                                class="px-6 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container transition-colors inline-flex items-center gap-2 disabled:opacity-50">
                            <i :class="chargementRevision ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-floppy-disk'" class="text-xs"></i>
                            <span x-text="chargementRevision ? 'Enregistrement…' : 'Enregistrer la liste'"></span>
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </template>

    {{-- ========================================================= --}}
    {{-- MODALE CRUD SUJET (Create / Edit 7 champs: Ordre, Durée, Émission, Type, Sujet, Angle, JRI) --}}
    {{-- ========================================================= --}}
    <template x-if="showModal">
        <div x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4"
             x-transition.opacity.duration.200ms
             @keydown.escape.window="closeModal()">
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px]" @click.self="closeModal()"></div>
            <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl border border-outline/10 max-h-[92vh] overflow-hidden flex flex-col"
                 x-transition.scale.origin.center.duration.200ms>

                <header class="px-6 py-5 border-b border-outline/10 bg-white">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-on-surface flex items-center gap-2" x-text="modalTitle">Ajouter un sujet</h3>
                            <p class="text-sm text-on-surface-variant mt-1" x-text="modalSubtitle">Renseignez le sujet, son émission, son angle, le type et le journaliste responsable.</p>
                        </div>
                        <button @click="closeModal()" class="p-2.5 min-w-[44px] min-h-[44px] rounded-lg hover:bg-surface-container text-secondary transition-colors">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </header>

                <div class="p-6 overflow-y-auto space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Ordre dans le conducteur
                            </label>
                            <input type="number" min="1" x-model.number="sujetForm.position"
                                   class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all font-mono text-on-surface">
                            <p class="mt-1 text-xs text-on-surface-variant italic" x-text="modalOrdreHint">Position 1 = tout début</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Durée estimée <span class="text-primary">(minutes)</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" max="600" step="1" x-model.number="sujetForm.dureeMinutes"
                                       @change="majDureeLive()" @input="majDureeLive()"
                                       class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all font-mono text-on-surface">
                                <span class="text-xs text-on-surface-variant">min</span>
                                <input type="number" min="0" max="59" step="5" x-model.number="sujetForm.dureeSecondes"
                                       @change="majDureeLive()" @input="majDureeLive()"
                                       class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all font-mono text-on-surface">
                                <span class="text-xs text-on-surface-variant">sec</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Durée totale
                            </label>
                            <div class="px-4 py-2.5 rounded-lg bg-primary-fixed/10 border border-primary/15 text-primary font-mono font-bold text-center"
                                 x-text="sujetForm.dureeTotaleHM">03:30</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Émission
                                <span x-show="String(sujetForm.type_value || '').toUpperCase() === 'REPORTAGE'" class="text-primary font-normal normal-case">
                                    (choisir l'émission de diffusion)
                                </span>
                            </label>
                            <input type="search" x-model="emissionSearch" aria-label="Rechercher une émission"
                                   placeholder="Rechercher dans le catalogue…" autocomplete="off"
                                   class="w-full mb-2 px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                            <select x-model="sujetForm.emission_id"
                                    class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                                <option value="">(non assigné)</option>
                                <template x-for="e in filteredEmissionOptions()" :key="e.value">
                                    <option :value="e.value" x-text="e.label"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Type de sujet <span class="text-error">*</span>
                            </label>
                            <select x-model="sujetForm.type_value"
                                    class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                                <template x-for="opt in typeOptions" :key="opt.value">
                                    <option :value="opt.value" x-text="opt.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Titre / Sujet <span class="text-error">*</span>
                        </label>
                        <input type="text" x-model="sujetForm.title" maxlength="500"
                               placeholder="Ex: Réaction des députés sur la loi immigration"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Angle éditorial
                        </label>
                        <textarea x-model="sujetForm.angle_editorial" rows="3" maxlength="5000"
                                  placeholder="Ex: Focus sur la voix des députés de l'opposition + une contre-analyse économique. Croisement avec volet citoyens."
                                  class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface resize-y"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Journaliste responsable / JRI
                        </label>
                        <div class="relative">
                            <button type="button"
                                    @click.stop="jrOpenSelect()"
                                    class="w-full pl-3.5 pr-11 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-left flex items-center gap-2.5 min-h-[48px]"
                                    :class="!selectedJr ? 'text-on-surface-variant' : 'text-on-surface'">
                                <template x-if="selectedJr">
                                    <span class="w-7 h-7 rounded-full bg-primary-fixed/15 text-primary text-xs font-bold flex items-center justify-center flex-shrink-0"
                                          x-text="jrInitiales(selectedJr.label)">JV</span>
                                </template>
                                <template x-if="!selectedJr">
                                    <span class="w-7 h-7 rounded-full bg-on-surface/5 text-on-surface-variant text-xs flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-user"></i>
                                    </span>
                                </template>
                                <span class="truncate flex-1">
                                    <template x-if="selectedJr">
                                        <span class="flex flex-col leading-tight">
                                            <span class="font-semibold truncate" x-text="selectedJr.label">Nom journaliste</span>
                                            <span x-show="selectedJr.fonction" class="text-xs text-on-surface-variant truncate" x-text="selectedJr.fonction">Fonction</span>
                                        </span>
                                    </template>
                                    <template x-if="!selectedJr">
                                        <span class="italic text-sm">Choisissez un journaliste ou JRI...</span>
                                    </template>
                                </span>
                                <span class="absolute right-0 top-0 bottom-0 flex items-center pr-3 gap-1">
                                    <button type="button" tabindex="-1"
                                            @click.stop="jrClear();"
                                            class="text-on-surface-variant/70 hover:text-error transition-colors"
                                            x-show="selectedJr"
                                            x-cloak
                                            title="Retirer l'assignation">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                    <i class="fa-solid fa-chevron-down text-xs text-on-surface-variant transition-transform flex-shrink-0"
                                       :class="jrDropdownOpen ? 'rotate-180' : ''"></i>
                                </span>
                            </button>

                            {{-- Avertissements de disponibilité --}}
                            <div x-show="jrVide && ! selectedJrHorsListe" x-cloak
                                 class="mt-2 flex items-start gap-2 px-3 py-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs leading-relaxed">
                                <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                                <div>
                                    <strong>Aucun journaliste présent à la réunion de rédaction.</strong>
                                    Seules les personnes présentes peuvent être assignées à un sujet — ajoutez-les dans la section « Présences » plus bas.
                                </div>
                            </div>

                            <div x-show="selectedJrHorsListe" x-cloak
                                 class="mt-2 flex items-start gap-2 px-3 py-2.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-700 text-xs leading-relaxed">
                                <i class="fa-solid fa-circle-info mt-0.5 text-slate-500"></i>
                                <div>
                                    <strong>Affectation conservée.</strong>
                                    Cette personne n'est <strong>plus présente</strong> à la réunion de rédaction :
                                    elle n'est donc plus proposée dans la liste, mais l'affectation actuelle est conservée.
                                    Pour changer de responsable, choisissez une personne présente.
                                </div>
                            </div>

                            <div x-show="jrDropdownOpen"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-1"
                                 @click.away="jrDropdownOpen = false"
                                 class="absolute z-20 left-0 right-0 mt-2 bg-white border border-outline/20 rounded-2xl shadow-2xl overflow-hidden">
                                <div class="p-3 border-b border-outline/5 bg-surface-container-lowest sticky top-0">
                                    <div class="relative">
                                        <input type="text"
                                               id="jr-search-input"
                                               x-model="jrSearch"
                                               @keydown.escape="jrDropdownOpen = false"
                                               placeholder="🔎 Rechercher un journaliste, un JRI, une fonction..."
                                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-on-surface-variant"></i>
                                    </div>
                                </div>
                                <div class="overflow-y-auto max-h-80">
                                    <div x-show="jrFiltered.length === 0" class="px-4 py-8 text-center text-sm text-on-surface-variant">
                                        <i class="fa-solid fa-user-slash text-lg mb-2 block opacity-40"></i>
                                        <template x-if="jrSearch !== ''">
                                            <span>Aucune personne présente ne correspond à « <span class="font-semibold text-on-surface" x-text="jrSearch"></span> »</span>
                                        </template>
                                        <template x-if="jrSearch === ''">
                                            <div class="space-y-2">
                                                <p class="font-semibold text-on-surface">
                                                    Aucun journaliste présent à la réunion de rédaction.
                                                </p>
                                                <p class="text-xs leading-relaxed max-w-sm mx-auto">
                                                    Seules les personnes <strong>présentes à la réunion de rédaction</strong>
                                                    peuvent être assignées à un sujet.
                                                    Ajoutez d'abord les présents dans la section
                                                    <strong>« Présences · Réunion de rédaction »</strong> plus bas.
                                                </p>
                                                <a href="#presences" @click="closeModal()"
                                                   class="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 rounded-lg bg-primary/10 text-primary text-xs font-semibold hover:bg-primary/20 transition-colors">
                                                    <i class="fa-solid fa-users text-xs"></i>
                                                    Aller aux présences
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button"
                                            @click="jrPick(null)"
                                            x-show="jrFiltered.length > 0"
                                            class="w-full text-left px-4 py-3 text-sm transition-colors flex items-center gap-3"
                                            :class="!sujetForm.journaliste_responsable_id ? 'bg-primary-fixed/8 text-primary font-semibold' : 'hover:bg-surface-container text-on-surface-variant'">
                                        <span class="w-8 h-8 rounded-full bg-on-surface/5 text-on-surface-variant text-xs flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid fa-ban"></i>
                                        </span>
                                        <div class="flex flex-col flex-1">
                                            <span>Aucun journaliste assigné</span>
                                            <span class="text-xs text-on-surface-variant/70">Reste libre — à assigner plus tard</span>
                                        </div>
                                        <i x-show="!sujetForm.journaliste_responsable_id" class="fa-solid fa-check text-primary text-xs flex-shrink-0"></i>
                                    </button>
                                    <template x-for="j in jrFiltered" :key="j.value">
                                        <button type="button"
                                                @click="jrPick(j)"
                                                class="w-full text-left px-4 py-3 text-sm transition-colors flex items-center gap-3 border-t border-outline/5"
                                                :class="String(sujetForm.journaliste_responsable_id) === String(j.value) ? 'bg-primary-fixed/8 text-primary font-semibold' : 'hover:bg-surface-container text-on-surface'">
                                            <span class="w-8 h-8 rounded-full bg-primary-fixed/15 text-primary text-xs font-bold flex items-center justify-center flex-shrink-0"
                                                  x-text="jrInitiales(j.label)">JV</span>
                                            <div class="flex flex-col min-w-0 flex-1">
                                                <span class="truncate" x-text="j.label">Nom journaliste</span>
                                                <span x-show="j.fonction" class="text-xs text-on-surface-variant/70 truncate" x-text="j.fonction">Fonction</span>
                                            </div>
                                            <i x-show="String(sujetForm.journaliste_responsable_id) === String(j.value)" class="fa-solid fa-check text-primary text-xs flex-shrink-0"></i>
                                        </button>
                                    </template>
                                </div>
                                <div class="px-4 py-2.5 border-t border-outline/5 bg-surface-container-lowest text-xs text-on-surface-variant flex items-center gap-1.5 italic">
                                    <i class="fa-solid fa-lightbulb text-amber-500 text-xs"></i>
                                    Astuce: saisissez quelques lettres pour filtrer instantanément.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="Object.keys(modalErrs).length > 0"
                         class="p-4 rounded-lg bg-error-container/20 border border-error-container/50 space-y-1">
                        <template x-for="msg in Object.values(modalErrs)" :key="msg">
                            <p class="text-sm text-error flex items-start gap-2">
                                <i class="fa-solid fa-circle-xmark mt-0.5 text-xs"></i>
                                <span x-text="msg"></span>
                            </p>
                        </template>
                    </div>
                </div>

                <footer class="px-6 py-4 border-t border-outline/10 bg-surface-container-lowest flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs text-on-surface-variant">
                        <i class="fa-solid fa-circle-info text-primary mr-1.5"></i>
                        Les étapes de la production se gèrent directement dans la liste des sujets : cliquez sur un badge pour faire progresser le sujet.
                    </p>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm text-on-surface-variant hover:bg-surface-container rounded-lg transition-colors">
                            Annuler
                        </button>
                        <button type="button" @click="saveSujet()" :disabled="isSavingSujet"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-container transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                            <i x-show="!isSavingSujet" class="fa-solid fa-check"></i>
                            <i x-show="isSavingSujet" class="fa-solid fa-spinner fa-spin"></i>
                            <span x-text="isSavingSujet ? 'Enregistrement…' : modalSubmitLabel">Ajouter le sujet</span>
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </template>

    {{-- TOAST --}}
    <div x-show="showToast" x-cloak x-transition.opacity.duration.200ms
         class="fixed bottom-4 right-4 left-4 sm:left-auto sm:right-6 z-[200] max-w-sm w-auto sm:w-full px-5 py-3.5 rounded-xl shadow-2xl border border-outline/10 flex items-start gap-3"
         :class="toastKind === 'success' ? 'bg-emerald-50 text-emerald-900 border-emerald-100'
                : toastKind === 'error'   ? 'bg-red-50 text-red-900 border-red-100'
                : 'bg-blue-50 text-blue-900 border-blue-100'">
        <i class="fa-solid mt-0.5"
           :class="toastKind === 'success' ? 'fa-circle-check text-emerald-600'
                 : toastKind === 'error'   ? 'fa-circle-xmark text-red-600'
                 : 'fa-circle-info text-blue-600'"></i>
        <div class="flex-1">
            <p class="text-sm font-semibold" x-text="toastText"></p>
        </div>
        <button @click="showToast = false" class="text-on-surface-variant hover:text-on-surface transition-colors">
            <i class="fa-solid fa-xmark text-xs"></i>
        </button>
    </div>

</div>
@endsection
