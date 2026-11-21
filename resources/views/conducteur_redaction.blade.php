@extends('layouts.app')

@php
    $pageTitle = 'Conducteur · '.(optional($conducteur->emission)->nom ?? 'Émission');
    $pageSubtitle = 'Préparation du déroulé et suivi de l’antenne';
@endphp

@section('content')

@php
    $nomEmission = old('emission_nom', optional($conducteur->emission)->nom ?? 'MID\'INFOS');
    $dateEdition = old('date_edition', $conducteur->date_edition ? $conducteur->date_edition->format('Y-m-d') : now()->toDateString());
    $statutBadge = $conducteur->statut_badge_html;
    $user = auth()->user();
    $sequencesJson = $sequences->map(function ($s) use ($user, $conducteur) {
        return [
            'id' => $s->id,
            'can_delete' => !$conducteur->date_locked_at && ($user?->can('deleteSequencePresentateur', $s) ?? false),
            'code_conducteur' => $s->code_conducteur_affichage,
            'code_ligne' => $s->code_ligne,
            'conducteur_id' => $s->conducteur_id,
            'date_edition' => optional($s->conducteur)->date_edition ? $s->conducteur->date_edition->format('d/m/Y') : '',
            'position' => $s->position,
            'title' => $s->title,
            'type_html' => $s->type_badge_html,
            'debut_hm' => $s->debut_hm,
            'fin_hm' => $s->fin_hm,
            'duree_hm' => $s->duree_hm,
            'intervenant_nom' => $s->intervenant_nom_affichage ?: '—',
            'fonction' => $s->intervenant_fonction_affichage ?: ($s->fonction_text ?: '—'),
            'alerte_info' => (bool) $s->alerte_info,
            'has_notes' => trim(strip_tags((string) $s->notes_presentateur)) !== '',
            'notes_preview' => \Illuminate\Support\Str::limit(strip_tags((string) $s->notes_presentateur), 140),
        ];
    })->values()->all();
    // Keep visible actions aligned with the server-side permission.
    $canAnyCreate = $user && ! $conducteur->date_locked_at
        && $user->can('crudSequencePresentateur', \App\Models\Sequence::class);
    $canReorder  = $user && $user->can('reorderSequences', $conducteur);
@endphp

<div x-data="conducteurRedaction()"
     x-init='init({
        conducteurId: {{ $conducteur->id }},
        csrfToken: @json(csrf_token()),
        routes: {
            reorder: @json(route('conducteurs.reorder', $conducteur)),
            seqPayloadBase: @json(route('conducteurs.sequences.payload', '_SEQ_')),
            seqStore: @json(route('conducteurs.sequences.store')),
            seqUpdateBase: @json(route('conducteurs.sequences.update', '_SEQ_')),
            seqDestroyBase: @json(route('conducteurs.sequences.destroy', '_SEQ_')),
        },
        sequences: @json($sequencesJson),
        dureeActuelle: @json($dureeActuelle ?? '00:00'),
    })'
     @keydown.escape.window="fermerToutesModales()"
     class="flex flex-col gap-6 pb-6">

    <style>
        @keyframes tv-sequence-active {
            0%, 100% { background-color: rgb(254 242 242); box-shadow: inset 0 0 0 2px rgb(185 28 28); }
            50% { background-color: rgb(255 255 255); box-shadow: inset 0 0 0 2px rgb(254 202 202); }
        }
        .tv-sequence-active { animation: tv-sequence-active 1.15s ease-in-out infinite; }
        .tv-sequence-active .tv-sequence-live {
            animation: tv-sequence-active 1.15s ease-in-out infinite;
        }
        @media (prefers-reduced-motion: reduce) {
            .tv-sequence-active, .tv-sequence-active .tv-sequence-live { animation: none; }
        }
    </style>

    {{-- =========== STATS BAR (harmonisée — infos regroupées) =========== --}}
    <section class="bg-white border border-outline-variant rounded-xl p-5 flex flex-wrap gap-6 items-center shadow-sm">
        <div class="min-w-[140px]">
            <p class="text-xs uppercase tracking-widest text-secondary mb-1">Émission</p>
            <h1 class="font-black text-lg">{{ $nomEmission }}</h1>
            <p class="text-xs text-secondary mt-0.5">{{ \Carbon\Carbon::parse($dateEdition)->isoFormat('dddd D MMMM YYYY') }}</p>
        </div>
        <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
        <div class="min-w-[110px]">
            <p class="text-xs uppercase tracking-widest text-secondary mb-1">Horaires</p>
            <p class="font-bold text-lg font-mono">{{ $heureDebutEmission }} <span class="text-secondary font-normal">→</span> {{ $heureFinCible }}</p>
        </div>
        <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
        <div class="min-w-[130px]">
            <p class="text-xs uppercase tracking-widest text-secondary mb-1">Durée prévue</p>
            <p class="font-bold text-lg font-mono">{{ $dureeTotaleCible }}</p>
        </div>
        <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
        <div class="min-w-[130px]">
            <p class="text-xs uppercase tracking-widest text-secondary mb-1">Durée actuelle</p>
            <p class="font-bold text-lg font-mono text-blue-700" x-text="dureeActuelle">{{ $dureeActuelle }}</p>
        </div>
        <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
        <div class="min-w-[100px]">
            <p class="text-xs uppercase tracking-widest text-secondary mb-1">Statut</p>
            <div>{!! $statutBadge !!}</div>
        </div>
        <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
        <div class="min-w-[100px]">
            <p class="text-xs uppercase tracking-widest text-secondary mb-1">Type</p>
            <div>{!! $conducteur->type_badge_html !!}</div>
        </div>
        @can('updateEnTete', $conducteur)
            <div class="hidden md:block w-px h-10 bg-outline-variant"></div>
            <div class="min-w-[220px] flex-1" x-data='{ presentateurId: @json((string) $conducteur->presentateur_id) }'>
                <form method="POST" action="{{ route('conducteurs.presentateur.update', $conducteur) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <label class="w-full text-xs uppercase tracking-widest text-secondary" for="presentateur-select">Présentateur</label>
                    <select id="presentateur-select" name="presentateur_id" x-model="presentateurId" autocomplete="off"
                            class="min-w-[180px] flex-1 rounded-lg border border-outline/30 bg-white px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30">
                        <option value="">— Non assigné —</option>
                        @foreach($intervenants as $p)
                            <option value="{{ $p->id }}" @selected((string) $conducteur->presentateur_id === (string) $p->id)>{{ trim(($p->civilite?->value ?? '').' '.$p->prenom.' '.$p->nom) }}</option>
                        @endforeach
                        @foreach($journalistes as $j)
                            <option value="j:{{ $j->id }}" @selected((string) $conducteur->presentateur_id === 'j:'.$j->id)>{{ trim(($j->civilite?->value ?? '').' '.$j->prenom.' '.$j->nom) }} (journaliste)</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white hover:opacity-90">Changer</button>
                </form>
            </div>
        @endcan
    </section>

    {{-- Bulles pop-up : message en cours + messages du public --}}
    @include('_partials.bulles-conducteur')

    {{-- QR public, messages et antenne/notes : un même bloc visible dès l'ouverture --}}
    <section class="mt-6 grid items-start gap-4 lg:grid-cols-2">
        <div class="min-w-0 space-y-4">
            @if((string) $conducteur->type === 'emission')
                @include('_partials.chef-qr-public', ['conducteur' => $conducteur, 'interactionMessages' => $interactionMessages ?? collect(), 'interactionMessagesCount' => $interactionMessagesCount ?? 0])
            @endif
            @include('_partials.conducteur-sondage', ['conducteur' => $conducteur, 'sondage' => $sondage ?? null])
        </div>
        @include('_partials.chef-antenne-panel', ['conducteur' => $conducteur])
    </section>

    {{-- =========== TABLE SECTION =========== --}}
<section class="bg-white border border-outline-variant rounded-xl overflow-hidden shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant bg-surface-container-low px-5 py-4">
            <div class="min-w-0">
                <h3 class="text-sm font-bold uppercase tracking-wide text-on-surface">Programmation du conducteur</h3>
                <p class="mt-0.5 text-xs text-on-surface-variant">
                    <span x-text="sequences.length">0</span> élément(s) · utilisez les flèches pour changer l’ordre.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canAnyCreate)
                    <button type="button" @click="ouvrirModaleSequence(null)"
                            class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-container hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Ajouter un élément
                    </button>
                @endif
                <button type="button" @click="$dispatch('tv-chat-open')"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-outline/30 bg-white px-4 py-2 text-sm font-semibold text-on-surface transition-colors hover:bg-surface-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <i class="fa-solid fa-comment-dots text-primary" aria-hidden="true"></i>
                    Discussion interne
                </button>
                <a href="{{ route('conducteurs.emission.export-pdf', $conducteur) }}"
                   class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-outline/30 bg-white px-4 py-2 text-sm font-semibold text-on-surface transition-colors hover:bg-surface-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                   title="Exporter le script présentateur et les données du conducteur en PDF (impression)">
                    <i class="fa-solid fa-file-pdf text-primary" aria-hidden="true"></i>
                    Exporter (PDF)
                </a>
                @can('update', $conducteur)
                    <details class="group relative">
                        <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-outline/30 bg-white px-4 py-2 text-sm font-semibold text-on-surface transition-colors hover:bg-surface-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            <i class="fa-solid fa-bell text-xs text-error" aria-hidden="true"></i>
                            Diffuser une alerte
                            <i class="fa-solid fa-chevron-down text-xs transition-transform group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        <div class="absolute right-0 top-full z-40 mt-2 w-[min(360px,calc(100vw-2rem))] rounded-xl border border-outline/25 bg-white p-4 shadow-lg">
                            <form method="POST" action="{{ route('alertes-info.store') }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label for="alerte-titre" class="mb-1 block text-xs font-semibold text-on-surface-variant">Titre</label>
                                    <input id="alerte-titre" name="titre" required maxlength="150"
                                           class="w-full rounded-lg border border-outline/30 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                           placeholder="Ex : Coupure de diffusion">
                                </div>
                                <div>
                                    <label for="alerte-message" class="mb-1 block text-xs font-semibold text-on-surface-variant">Message</label>
                                    <textarea id="alerte-message" name="message" required maxlength="1000" rows="3"
                                              class="w-full rounded-lg border border-outline/30 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                              placeholder="Précisez ce que l’équipe doit savoir."></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="alerte-niveau" class="mb-1 block text-xs font-semibold text-on-surface-variant">Niveau</label>
                                        <select id="alerte-niveau" name="niveau"
                                                class="w-full rounded-lg border border-outline/30 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                            <option value="info">Information</option>
                                            <option value="avertissement">Avertissement</option>
                                            <option value="urgence">Urgence</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="alerte-expire" class="mb-1 block text-xs font-semibold text-on-surface-variant">Expire le (facultatif)</label>
                                        <input id="alerte-expire" name="expire_at" type="datetime-local"
                                               class="w-full rounded-lg border border-outline/30 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    </div>
                                </div>
                                <div>
                                    <label for="alerte-ordre" class="mb-1 block text-xs font-semibold text-on-surface-variant">Ordre dans la pile (facultatif)</label>
                                    <input id="alerte-ordre" name="ordre" type="number" min="0" step="1"
                                           placeholder="Par défaut : passe au-dessus des alertes précédentes"
                                           class="w-full rounded-lg border border-outline/30 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                </div>
                                <button type="submit"
                                        class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-error px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-error">
                                    <i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i> Diffuser sur toutes les pages
                                </button>
                            </form>
                        </div>
                    </details>
                @endcan

            </div>
        </div>

        <div class="overflow-x-auto">
            <table data-mobile-cards class="w-full text-left border-collapse text-xs" id="conducteur-tableau">
                <thead>
                    <tr class="border-b border-outline-variant bg-white sticky top-0 z-10">
                        <th class="p-3 table-header font-semibold w-14 text-center hidden lg:table-cell">Ordre</th>
                        <th class="p-3 table-header font-semibold w-24">Début</th>
                        <th class="p-3 table-header font-semibold w-24">Fin</th>
                        <th class="p-3 table-header font-semibold w-20">Durée</th>
                        <th class="p-3 table-header font-semibold w-32">Type</th>
                        <th class="p-3 table-header font-semibold min-w-[180px] lg:min-w-[260px]">Titre ou Sujet</th>
                        <th class="p-3 table-header font-semibold min-w-[120px] lg:min-w-[180px]">Intervenant</th>
                        <th class="p-3 table-header font-semibold w-32">Fonction</th>
                        <th class="p-3 table-header font-semibold text-center w-20">Actions</th>
                    </tr>
                </thead>
                <tbody id="conducteur-tbody"
                       {{-- SortableJS Alpine handle si modeReorg activé --}}>

                    <template x-if="sequences.length === 0">
                        <tr>
                            <td colspan="9" class="p-16 text-center text-secondary italic">
                                Aucun élément dans le conducteur. Cliquez sur <span class="font-semibold text-primary">« Ajouter un élément »</span> pour commencer.
                            </td>
                        </tr>
                    </template>

                    <template x-for="(seq, indexSeq) in sequences" :key="seq.id">
                        <tr x-data="{ estActive: false }"
                            @tv-sequence-active.window="estActive = Number($event.detail.id) === Number(seq.id)"
                            class="border-b border-outline-variant transition-colors group"
                            :class="estActive ? 'tv-sequence-active' : (seq.alerte_info ? 'bg-red-50/40 animate-pulse' : 'hover:bg-surface-container-low')"
                            :data-seq-id="seq.id"
                            :data-conducteur-id="seq.conducteur_id">

                            {{-- #1 Ordre (le code conducteur est affiché dans le bandeau ; le code ligne reste en tooltip) --}}
                            <td class="p-3 text-center font-mono hidden lg:table-cell">
                                @if($canReorder)
                                <div class="flex items-center justify-center gap-2" @mousedown.stop>
                                    <button type="button" @click="deplacerHaut(indexSeq)"
                                            title="Monter le sujet" aria-label="Monter le sujet"
                                            class="p-2 min-w-[44px] min-h-[44px] rounded border border-outline text-secondary hover:bg-white disabled:opacity-30"
                                            :disabled="indexSeq === 0 || ordreEnCours">
                                        <i class="fa-solid fa-chevron-up text-xs"></i>
                                    </button>
                                    <span class="font-bold w-6 text-center" x-text="indexSeq + 1"></span>
                                    <button type="button" @click="deplacerBas(indexSeq)"
                                            title="Descendre le sujet" aria-label="Descendre le sujet"
                                            class="p-2 min-w-[44px] min-h-[44px] rounded border border-outline text-secondary hover:bg-white disabled:opacity-30"
                                            :disabled="indexSeq === sequences.length - 1 || ordreEnCours">
                                        <i class="fa-solid fa-chevron-down text-xs"></i>
                                    </button>
                                </div>
                                @else
                                    <span class="text-secondary" x-text="indexSeq + 1"></span>
                                @endif
                            </td>

                            {{-- #2 Début --}}
                            <td class="p-3 font-mono text-on-surface whitespace-nowrap"
                                :class="indexSeq === 0 ? 'font-semibold' : ''"
                                x-text="seq.debut_hm">12:00:00</td>

                            {{-- #3 Fin --}}
                            <td class="p-3 font-mono text-secondary whitespace-nowrap" x-text="seq.fin_hm">12:02:00</td>

                            {{-- #4 Durée --}}
                            <td class="p-3 whitespace-nowrap font-mono text-on-surface">
                                <span x-show="!estActive" x-text="seq.duree_hm">02:00</span>
                                <span x-show="estActive" x-cloak class="tv-sequence-live inline-flex items-center gap-1 rounded-md border border-error/40 bg-white px-2 py-0.5 text-[11px] font-bold text-error">
                                    <span class="inline-block h-2 w-2 rounded-full bg-error"></span>
                                    EN COURS · <span x-text="seq.duree_hm"></span>
                                </span>
                            </td>

                            {{-- #5 Type (badge) --}}
                            <td class="p-3" x-html="seq.type_html"><span class="badge badge-blue">SUJET</span></td>

                            {{-- #6 Titre ou Sujet --}}
                            <td class="p-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start gap-2">
                                        <i x-show="seq.alerte_info" class="fa-solid fa-triangle-exclamation text-error mt-0.5" title="Alerte info"></i>
                                        <span class="font-medium" :class="seq.alerte_info ? 'text-error' : ''" x-text="seq.title">Titre sujet</span>
                                        <span x-show="seq.has_notes" x-cloak
                                              class="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary"
                                              :title="seq.notes_preview">
                                            <i class="fa-regular fa-note-sticky" aria-hidden="true"></i> Notes
                                        </span>
                                    </div>
                                    <p x-show="seq.has_notes" x-cloak
                                       class="mt-1 line-clamp-1 text-[11px] italic text-on-surface-variant"
                                       x-text="seq.notes_preview"></p>
                                </div>
                            </td>

                            {{-- #7 Intervenant --}}
                            <td class="p-3 text-on-surface" x-text="seq.intervenant_nom">—</td>

                            {{-- #8 Fonction --}}
                            <td class="p-3 text-secondary italic" x-text="seq.fonction">Présentateur</td>

                            {{-- #9 Actions --}}
                            <td class="p-3 text-center">
                                <div class="flex gap-2 justify-center opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                                    <button @click="ouvrirModaleSequence(seq.id)"
                                            title="Éditer"
                                            class="text-secondary hover:text-primary transition-colors p-2.5 rounded-lg">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button @click="supprimerSequence(seq.id, seq.title)"
                                            title="Supprimer"
                                            class="text-secondary hover:text-error transition-colors p-2.5 rounded-lg">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Footer durée totale --}}
        <div class="p-4 border-t border-outline-variant bg-surface-container-low flex justify-between items-center">
            <p class="text-secondary text-xs">
                {{ \Carbon\Carbon::parse($dateEdition)->isoFormat('dddd D MMMM YYYY') }}
            </p>
            <p class="text-secondary text-xs">
                <span x-text="sequences.length">{{ $sequences->count() }}</span> élément(s) dans le conducteur
            </p>
        </div>
    </section>

    {{-- =========== MODALE CRÉATION / ÉDITION SÉQUENCE =========== --}}
    <div x-show="modaleSequenceVisible"
         x-transition.opacity.duration.200ms
         class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
         @click.self="fermerToutesModales()"
         style="display:none;">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90dvh] overflow-y-auto"
             @click.stop>
            <div class="p-5 border-b border-outline-variant flex justify-between items-center sticky top-0 bg-white z-10">
                <h3 class="font-bold text-lg" x-text="modeEdition ? 'Éditer un élément' : 'Ajouter un élément'">Ajouter un élément</h3>
                <button @click="fermerToutesModales()"
                        class="w-11 h-11 rounded-full hover:bg-surface-container flex items-center justify-center text-secondary">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form @submit.prevent="soumettreModaleSequence()"
                  method="POST"
                  class="p-5 grid grid-cols-1 gap-4">
                @csrf

                <input type="hidden" name="conducteur_id" value="{{ $conducteur->id }}">
                <input type="hidden" name="_method" x-bind:value="modeEdition ? 'PATCH' : 'POST'">

                {{-- Titre (OBLIGATOIRE) --}}
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Titre ou Sujet <span class="text-error">*</span></label>
                    <input type="text" name="title"
                           x-model="formSeq.title"
                           required
                           class="w-full border border-outline rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors"
                           placeholder="Ex : Réforme du Code pénal">
                </div>

                {{-- Type + Durée --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Type <span class="text-error">*</span></label>
                    <select name="type"
                            x-model="formSeq.type"
                            required
                            class="w-full border border-outline rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                        @foreach($typeOptions as $val => $lab)
                            <option value="{{ $val }}">{{ $lab }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Durée</label>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative">
                            <input type="number" name="duration_minutes"
                                   x-model.number="formSeq.duration_minutes"
                                   min="0" max="600"
                                   class="w-full border border-outline rounded-lg px-3 py-2.5 pr-8 focus:ring-2 focus:ring-primary focus:border-primary outline-none"
                                   placeholder="0">
                            <span class="absolute right-3 top-2.5 text-secondary text-xs">min</span>
                        </div>
                        <div class="relative">
                            <input type="number" name="duration_seconds"
                                   x-model.number="formSeq.duration_seconds"
                                   min="0" max="59"
                                   class="w-full border border-outline rounded-lg px-3 py-2.5 pr-8 focus:ring-2 focus:ring-primary focus:border-primary outline-none"
                                   placeholder="0">
                            <span class="absolute right-3 top-2.5 text-secondary text-xs">s</span>
                        </div>
                    </div>
                </div>

                {{-- Invités (1 ou plusieurs) + Fonction --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Intervenants invités</label>
                    <div class="flex gap-2">
                        <div class="flex-1 min-w-0 relative">
                            <input type="search"
                                   x-model="inviteSearch"
                                   @focus="inviteDropdownOpen = true"
                                   @click.outside="inviteDropdownOpen = false"
                                   placeholder="Rechercher un invité (nom, fonction)…"
                                   class="w-full border border-outline rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                            <div x-show="inviteDropdownOpen" x-cloak
                                 class="absolute z-30 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border border-outline bg-white shadow-lg">
                                @foreach($intervenants as $int)
                                    <button type="button"
                                            x-show="intervenantCorrespond('{{ addslashes(strtolower($int->nomComplet.' '.($int->fonction ?? ''))) }}', inviteSearch)"
                                            @click="inviteSelection = '{{ $int->id }}'; ajouterInvite();"
                                            :disabled="formSeq.intervenant_ids.includes({{ $int->id }})"
                                            class="w-full text-left px-3 py-2 hover:bg-surface-container transition-colors flex items-center justify-between gap-2 border-b border-outline/10 last:border-0 disabled:opacity-40 disabled:cursor-not-allowed">
                                        <span class="text-sm text-on-surface">{{ $int->nomComplet }}</span>
                                        <span class="text-xs text-on-surface-variant">{{ $int->fonction ?? '' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <button type="button"
                                @click="ajouterInvite()"
                                :disabled="String(formSeq.type || '').toUpperCase() === 'DÉBAT' && formSeq.intervenant_ids.length >= 4"
                                class="min-h-11 min-w-11 px-3 border border-outline rounded-lg text-primary hover:bg-surface-container transition-colors font-semibold text-sm whitespace-nowrap disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fa-solid fa-plus mr-1"></i> Ajouter
                        </button>
                    </div>

                    <div x-show="formSeq.intervenant_ids.length > 0" x-cloak class="mt-3 space-y-2">
                        <template x-for="(inviteId, inviteIdx) in formSeq.intervenant_ids" :key="inviteId">
                            <div class="flex items-center gap-2 border border-outline rounded-lg px-2 py-1.5 bg-surface-container-low">
                                <span class="w-6 h-6 shrink-0 rounded-full bg-primary-container/20 text-primary text-xs font-bold flex items-center justify-center"
                                      x-text="inviteIdx + 1"></span>
                                <span class="flex-1 min-w-0 text-sm truncate" x-text="labelIntervenant(inviteId)"></span>
                                <button type="button" @click="deplacerInviteHaut(inviteIdx)" :disabled="inviteIdx === 0"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded text-secondary hover:bg-surface-container disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Monter" aria-label="Monter l'invité">
                                    <i class="fa-solid fa-arrow-up text-xs"></i>
                                </button>
                                <button type="button" @click="deplacerInviteBas(inviteIdx)" :disabled="inviteIdx === formSeq.intervenant_ids.length - 1"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded text-secondary hover:bg-surface-container disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Descendre" aria-label="Descendre l'invité">
                                    <i class="fa-solid fa-arrow-down text-xs"></i>
                                </button>
                                <button type="button" @click="retirerInvite(inviteIdx)"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded text-error hover:bg-error-container transition-colors"
                                        title="Retirer" aria-label="Retirer l'invité">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </template>
                        <p class="text-xs text-secondary"><i class="fa-solid fa-circle-info mr-1"></i>Le n° 1 est l'invité principal : nom et fonction pré-remplis sur le sujet.</p>
                    </div>
                    <p x-show="formSeq.intervenant_ids.length === 0" class="text-xs text-secondary mt-2">Aucun invité — le sujet peut ne pas en avoir.</p>
                    <p x-show="String(formSeq.type || '').toUpperCase() === 'DÉBAT'" x-cloak class="text-xs text-on-surface-variant mt-2">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Pour un débat, sélectionnez <strong>2 à 4 intervenants</strong>. Le premier est l'invité principal.
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Fonction</label>
                    <input type="text" name="fonction_text"
                           x-model="formSeq.fonction_text"
                           class="w-full border border-outline rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none"
                           placeholder="Ex : Journaliste">
                </div>

                {{-- Journaliste responsable + Alerte info --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Journaliste responsable</label>
                    <select name="journaliste_responsable_id"
                            x-model.number="formSeq.journaliste_responsable_id"
                            class="w-full border border-outline rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none">
                        <option value="">— Aucun —</option>
                        @foreach($journalistes as $j)
                            <option value="{{ $j->id }}">
                                {{ $j->nomComplet }}
                                @if($j->fonction) <span class="text-secondary">({{ $j->fonction?->label() }})</span>@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                {{-- Note Régie + Angle éditorial --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Note régie</label>
                    <textarea name="note_regie"
                              x-model="formSeq.note_regie"
                              rows="3"
                              class="w-full border border-outline rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary outline-none text-sm"
                              placeholder="Ex : Attendre le duplex de Serge Obame avant d'enchaîner"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">Angle éditorial</label>
                    <textarea name="angle_editorial"
                              x-model="formSeq.angle_editorial"
                              rows="3"
                              class="w-full border border-outline rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary outline-none text-sm"
                              placeholder="Ex : Accentuer les 3 innovations majeures pour les citoyens"></textarea>
                </div>

                <div class="md:col-span-2 pt-4 flex flex-wrap justify-end gap-3 border-t border-outline-variant mt-2">
                    <button type="button"
                            @click="fermerToutesModales()"
                            class="px-5 py-2.5 border border-outline rounded-lg text-secondary hover:bg-surface-container font-semibold transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                            :disabled="envoiEnCours"
                            class="px-5 py-2.5 bg-primary text-white rounded-lg hover:bg-primary-container font-semibold transition-colors disabled:opacity-60 flex items-center gap-2">
                        <i x-show="envoiEnCours" class="fa-solid fa-spinner fa-spin"></i>
                        <span x-text="modeEdition ? 'Enregistrer modifications' : 'Ajouter au conducteur'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Feedback toast (optionnel) --}}
    <div x-show="toastVisible"
         x-transition.opacity.duration.300ms
         class="fixed bottom-6 right-6 z-50 max-w-sm"
         style="display:none;">
        <div :class="[
            'px-5 py-3 rounded-xl shadow-xl font-semibold text-sm flex items-center gap-3 border',
            toastType === 'success' ? 'bg-green-100 text-green-900 border-green-300' :
            toastType === 'error'   ? 'bg-red-100 text-red-900 border-red-300'   :
                                      'bg-blue-100 text-blue-900 border-blue-300'
        ]">
            <i :class="[
                'text-lg',
                toastType === 'success' ? 'fa-solid fa-circle-check' :
                toastType === 'error'   ? 'fa-solid fa-circle-xmark'  : 'fa-solid fa-circle-info'
            ]"></i>
            <span x-text="toastMessage">OK</span>
        </div>
    </div>

</div>

@push('scripts')
    {{-- Alpine & Sortable sont déjà dans app.js / Vite bundle --}}
@endpush

@include('_partials.emission-chat', ['conducteur' => $conducteur])
@endsection
