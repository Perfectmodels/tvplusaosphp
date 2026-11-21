@extends('layouts.app')
@section('content')
    <div class="px-4 md:px-6 py-4 bg-surface-container-lowest border-b border-outline/10">
        <div class="max-w-[1600px] mx-auto flex flex-wrap justify-between items-end gap-4">
            <div>
                <div class="flex items-center gap-3 text-xs text-on-surface-variant uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-microphone-lines text-primary"></i>
                    <span>Conducteur · Vue Présentateur</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <h1 class="text-2xl font-bold text-on-surface">
                        {{ optional($conducteur->emission)->nom ?? 'MID\'INFOS' }}
                    </h1>
                    @can('updateEnTete', $conducteur)
                        <form method="POST" action="{{ route('conducteurs.presentateur.update', $conducteur) }}"
                              class="inline-flex items-center gap-2 rounded-full border border-outline/20 bg-surface-container px-3 py-1.5 text-sm">
                            @csrf
                            <i class="fa-regular fa-user text-xs text-primary" aria-hidden="true"></i>
                            <label for="cp-presentateur-select" class="text-on-surface-variant">Conduit par</label>
                            <select id="cp-presentateur-select" name="presentateur_id"
                                    class="max-w-[220px] rounded-md border border-outline/30 bg-white px-2 py-1 text-sm font-semibold text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30">
                                <option value="">Non assigné</option>
                                @foreach($intervenants as $p)
                                    <option value="{{ $p->id }}" @selected((string) $conducteur->presentateur_id === (string) $p->id)>
                                        {{ trim(($p->civilite?->value ?? '').' '.$p->prenom.' '.$p->nom) }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-md bg-primary px-2.5 py-1 text-xs font-bold text-white transition-colors hover:bg-primary-container hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                                Appliquer
                            </button>
                        </form>
                    @else
                        <span class="inline-flex items-center gap-2 text-sm text-on-surface-variant bg-surface-container rounded-full px-4 py-1.5 border border-outline/20">
                            <i class="fa-regular fa-user text-xs" aria-hidden="true"></i>
                            Conduit par
                            <span class="font-semibold text-on-surface">
                                @php
                                    $pres = $conducteur->presentateur;
                                    if ($pres) {
                                        $civ = '';
                                        if (! empty($pres->civilite)) {
                                            try {
                                                $civ = is_object($pres->civilite) && method_exists($pres->civilite, 'label') ? $pres->civilite->label() : (string) $pres->civilite?->value;
                                            } catch (\Throwable) { $civ = ''; }
                                        }
                                        echo e(trim(($civ ? $civ.' ' : '').($pres->prenom ?? '').' '.($pres->nom ?? '')));
                                    } else {
                                        echo '<span class="italic text-secondary">Non assigné</span>';
                                    }
                                @endphp
                            </span>
                        </span>
                    @endcan
                    {!! $conducteur->statut_badge_html ?? '' !!}
                    <span class="inline-flex items-center gap-2 text-xs text-on-surface-variant">
                        <i class="fa-regular fa-calendar"></i>
                        {{ optional($conducteur->date_edition)->isoFormat('dddd D MMMM YYYY') ?? now()->isoFormat('dddd D MMMM YYYY') }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('conducteurs.chef_edition', $conducteur) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 text-sm border border-outline/30 rounded-lg hover:bg-surface-container transition-colors text-on-surface">
                    <i class="fa-solid fa-pen-to-square text-xs"></i> Aller en Chef Édition
                </a>
                <a href="{{ route('conducteurs.emission.index') }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary-container transition-colors">
                    <i class="fa-solid fa-list text-xs"></i> Tous les conducteurs
                </a>
            </div>
        </div>
    </div>

    @if((string) $conducteur->type === 'emission')
        @php
            $publicInteractionMessages = $interactionMessages ?? collect();
            $publicInteractionMessagesCount = (int) ($interactionMessagesCount ?? 0);
            $publicQrToken = $conducteur->public_interaction_token;
            $publicQrUrl = $publicQrToken ? route('emission-interactions.show', $publicQrToken) : null;
        @endphp
        <section class="mt-3 flex flex-wrap items-start gap-3">
            @can('updateEnTete', $conducteur)
                <details class="w-full sm:w-auto rounded-xl border border-outline/20 bg-white group">
                    <summary class="flex items-center justify-between gap-3 p-4 cursor-pointer list-none select-none">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <i class="fa-solid fa-qrcode"></i>
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-on-surface">QR code public</h3>
                                <p class="mt-0.5 text-xs text-on-surface-variant">Messages du public · {{ $publicInteractionMessagesCount }} reçu(s)</p>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-down text-on-surface-variant transition-transform group-open:rotate-180"></i>
                    </summary>
                    <div class="px-4 pb-4 space-y-3 border-t border-outline/10">

                    @if($publicQrToken)
                        <div class="mt-3 flex flex-col items-center gap-3 sm:flex-row sm:items-start">
                            <img src="{{ route('conducteurs.emission.interactions.qr', $conducteur) }}"
                                 alt="QR code public de l’émission"
                                 class="h-40 w-40 rounded-lg border border-outline/20 bg-white p-2">
                            <div class="min-w-0 flex-1 space-y-2">
                                <a href="{{ $publicQrUrl }}" target="_blank" rel="noopener" class="block break-all text-xs font-medium text-primary underline underline-offset-2">{{ $publicQrUrl }}</a>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('conducteurs.emission.interactions.qr.download', $conducteur) }}"
                                       class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-container hover:text-primary">
                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        Télécharger le QR
                                    </a>
                                    <form method="POST" action="{{ route('conducteurs.emission.interactions.generate-qr', $conducteur) }}"
                                          x-data="{ qrBusy: false }"
                                          @submit="if (qrBusy) { $event.preventDefault(); return; } if (! confirm('Régénérer le QR code public ?\n\nL\'ancien lien restera valable, une nouvelle image sera générée.')) { $event.preventDefault(); return; } qrBusy = true;">
                                        @csrf
                                        <button type="submit" :disabled="qrBusy" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-outline/30 px-3 py-2 text-sm font-semibold text-on-surface hover:bg-surface-container disabled:opacity-50 disabled:cursor-not-allowed">
                                            <i x-show="! qrBusy" class="fa-solid fa-rotate" aria-hidden="true"></i>
                                            <i x-show="qrBusy" x-cloak class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                            Re-télécharger le QR
                                        </button>
                                    </form>
                                </div>
                                <p class="text-xs text-on-surface-variant">Le QR est affiché ci-dessus et peut être téléchargé.</p>
                            </div>
                        </div>
                    @else
                        <div class="mt-3">
                            <form method="POST" action="{{ route('conducteurs.emission.interactions.generate-qr', $conducteur) }}"
                                  x-data="{ qrBusy: false }"
                                  @submit="if (qrBusy) { $event.preventDefault(); return; } qrBusy = true;">
                                @csrf
                                <button type="submit"
                                        :disabled="qrBusy"
                                        class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-container hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i x-show="! qrBusy" class="fa-solid fa-qrcode" aria-hidden="true"></i>
                                    <i x-show="qrBusy" x-cloak class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                                    <span x-text="qrBusy ? 'Génération…' : 'Générer et afficher le QR'">Générer et afficher le QR</span>
                                </button>
                            </form>
                            <p class="mt-2 text-xs text-on-surface-variant">Une fois généré, le QR sera affiché et téléchargé automatiquement.</p>
                        </div>
                    @endif
                </details>
            @endcan

            <details data-emission-interactions
                     data-endpoint="{{ route('conducteurs.emission.interactions.index', $conducteur) }}"
                     class="min-w-0 flex-1 rounded-lg border border-outline/20 bg-white">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 text-sm font-semibold text-on-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <span class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-comments text-primary" aria-hidden="true"></i>
                        Messages du public
                    </span>
                    <span data-message-count class="inline-flex min-w-7 items-center justify-center rounded-full bg-primary/10 px-2 py-1 text-xs font-bold text-primary">
                        {{ $publicInteractionMessagesCount }}
                    </span>
                </summary>
                <div class="border-t border-outline/10 p-3">
                    <ol data-message-list class="max-h-48 divide-y divide-outline/10 overflow-y-auto">
                        @foreach($publicInteractionMessages as $publicMessage)
                            <li class="space-y-1 px-2 py-2.5">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs">
                                    <span class="font-semibold text-on-surface">
                                        {{ $publicMessage->nom ?: 'Téléspectateur' }}
                                        @if($publicMessage->destinataire)
                                            <span class="font-normal text-on-surface-variant">· à {{ $publicMessage->destinataire }}</span>
                                        @endif
                                    </span>
                                    <time class="text-on-surface-variant" datetime="{{ $publicMessage->created_at->toIso8601String() }}">
                                        {{ $publicMessage->created_at->format('d/m/Y à H:i') }}
                                    </time>
                                </div>
                                <p class="whitespace-pre-line break-words text-sm text-on-surface">{{ $publicMessage->message }}</p>
                            </li>
                        @endforeach
                    </ol>
                    <p data-message-empty @class([
                        'rounded-lg bg-surface-container-low px-3 py-3 text-sm text-on-surface-variant',
                        'hidden' => $publicInteractionMessages->isNotEmpty(),
                    ])>
                        Aucun message reçu pour le moment.
                    </p>
                    <p data-message-status class="sr-only" aria-live="polite"></p>
                </div>
            </details>
        </section>

        @if(session('download_public_qr'))
            <script>
                (function () {
                    const url = @json(route('conducteurs.emission.interactions.qr.download', $conducteur));
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = '';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                })();
            </script>
        @endif
    @endif

    <div class="w-full max-w-[1600px] mx-auto">
    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\Sequence> $sequences */
        // Durée totale de l'émission = somme des durées antenne (une séquence sans
        // durée compte pour 60 s) → cohérent avec le chrono affiché.
        $dureeTotale = (int) $sequences->sum(fn ($s) => $s->duree_antenne_sec);
        $dureeTotaleHM = \App\Services\ConducteurCalculatorService::formatDuree($dureeTotale);
        $user = auth()->user();

        $canCrudCpGlobale = false;
        if ($user) {
            try {
                $canCrudCpGlobale = (bool) $user->can('crudSequencePresentateur', \App\Models\Sequence::class);
            } catch (\Throwable) {
                $canCrudCpGlobale = true;
            }
        }
        $canDeleteCpGlobale = false;
        if ($user) {
            try {
                $canDeleteCpGlobale = (bool) $user->can('deleteSequencePresentateur', \App\Models\Sequence::class);
            } catch (\Throwable) {
                $canDeleteCpGlobale = true;
            }
        }
        $canReorderCp = false;
        if ($user) {
            try {
                $canReorderCp = (bool) $user->can('reorderSequences', $conducteur);
            } catch (\Throwable) {
                $canReorderCp = true;
            }
        }

        // Droit de piloter le CHRONO ANTENNE de l'émission (démarrer / pause / reset)
        // Un seul chrono pour toute l'émission → l'autorisation est portée par le
        // conducteur (ConducteurPolicy::piloterAntenne), plus par la séquence.
        $canPiloterAntenne = false;
        if ($user) {
            try {
                $canPiloterAntenne = (bool) $user->can('piloterAntenne', $conducteur);
            } catch (\Throwable) {
                $canPiloterAntenne = false;
            }
        }

        $typeOptions = [];
        foreach (\App\Enums\TypeSequenceEnum::cases() as $case) {
            try {
                $typeOptions[] = [
                    'value' => $case->value,
                    'label' => method_exists($case, 'label') ? $case->label() : $case->value,
                    'badge_class' => method_exists($case, 'badgeClass') ? $case->badgeClass() : 'bg-slate-100 text-slate-700',
                ];
            } catch (\Throwable) { continue; }
        }

        $sequencesJson = $sequences->map(fn($s) => \App\Http\Controllers\ConducteurPresentateurController::sequenceForAlpine($s))->values()->all();

        $lastPos = $sequences->max('position');
        if (! is_int($lastPos) || $lastPos < 0) $lastPos = 0;
        $defaultNextPos = $lastPos + 1;

        $dernierFin = null;
        foreach ($sequences->sortBy('position') as $s) {
            if (! empty($s->heure_fin_calculee)) {
                $dernierFin = (string) $s->heure_fin_calculee;
            } elseif (! empty($s->end_time)) {
                try { $dernierFin = optional(\Illuminate\Support\Carbon::parse($s->end_time))->format('H:i:s'); } catch (\Throwable) {}
            }
        }
        $finHM = $dernierFin ? (strlen($dernierFin) === 5 ? ($dernierFin . ':00') : $dernierFin) : '12:30:00';
        try {
            $defautDebut = \Illuminate\Support\Carbon::createFromFormat('H:i:s', $finHM);
            $defautFin = (clone $defautDebut)->addMinutes(2);
            $defaultDebut = $defautDebut ? $defautDebut->format('H:i') : '12:30';
            $defaultFin   = $defautFin   ? $defautFin->format('H:i')   : '12:32';
        } catch (\Throwable) {
            $defaultDebut = '12:30';
            $defaultFin   = '12:32';
        }
    @endphp

    <div
        x-data="conducteurPresentateur()"
        x-init='initialize({
            conducteurId: {{ $conducteur->id }},
            activeSequenceId: @json((int) ($conducteur->active_sequence_id ?? 0)),
            csrfToken: @json(csrf_token()),
            dureeTotaleHM: @json($dureeTotaleHM),
            sequences: @json($sequencesJson),
            canCrudCp: @json((bool) $canCrudCpGlobale),
            canDeleteCp: @json((bool) $canDeleteCpGlobale),
            canReorderCp: @json((bool) $canReorderCp),
            canPiloterAntenne: @json((bool) $canPiloterAntenne),
            // ---- État du chrono ÉMISSION (un seul compte à rebours) ----
            antenne: {
                startedAt: @json($conducteur->antenne_started_at?->toIso8601String()),
                offsetSec: @json((int) ($conducteur->antenne_offset_sec ?? 0)),
                endedAt:   @json($conducteur->antenne_ended_at?->toIso8601String()),
            },
            defaultNextPosition: @json((int) $defaultNextPos),
            defaultHeureDebut: @json($defaultDebut),
            defaultHeureFin:   @json($defaultFin),
            typeOptions: @json($typeOptions),
            intervenantsCp: @json($intervenantsCp ?? []),
            routes: {
                saveNotes: @json(route('conducteurs.sequences.notes_presentateur.update', ['sequence' => '__SEQ__'])),
                storeSeq:  @json(route('conducteurs.presentateur.sequence.store',   ['conducteur' => $conducteur->id])),
                updateSeq: @json(route('conducteurs.presentateur.sequence.update',  ['conducteur' => $conducteur->id, 'sequence' => '__SEQ__'])),
                destroySeq:@json(route('conducteurs.presentateur.sequence.destroy', ['conducteur' => $conducteur->id, 'sequence' => '__SEQ__'])),
                reorderSeq:@json(route('conducteurs.presentateur.sequence.reorder', ['conducteur' => $conducteur->id])),
                indexCp:   @json(route('conducteurs.emission.index')),
                redaction: @json(route('conducteurs.chef_edition', $conducteur)),
                liveState: @json(route('conducteurs.presentateur.live-state', $conducteur)),
                antenneStart:    @json(route('conducteurs.presentateur.antenne.start',    ['conducteur' => $conducteur->id])),
                antennePause:    @json(route('conducteurs.presentateur.antenne.pause',    ['conducteur' => $conducteur->id])),
                antenneReset:    @json(route('conducteurs.presentateur.antenne.reset',    ['conducteur' => $conducteur->id])),
                antenneSequence: @json(route('conducteurs.presentateur.antenne.sequence', ['conducteur' => $conducteur->id])),
            },
        })'
        class="w-full min-h-[520px] flex flex-col {{ (string) $conducteur->type === 'emission' ? 'lg:h-[calc(100dvh-var(--navbar-height,80px)-20rem)]' : 'lg:h-[calc(100dvh-var(--navbar-height,80px)-9rem)]' }} lg:flex-row overflow-hidden bg-surface-dim/40">

        {{-- ==================================================================== --}}
        {{--  PANEL GAUCHE = TABLEAU 6 COLONNES                                  --}}
        {{-- ==================================================================== --}}
        <section id="cp-leftpanel"
                 class="flex-1 min-w-0 min-h-0 flex flex-col border-b lg:border-b-0 border-r border-outline/10 bg-white overflow-y-auto lg:overflow-hidden">

            <div class="border-b border-outline/10 bg-gradient-to-b from-surface-container-low/70 to-white px-4 py-4 sm:px-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface flex items-center gap-2">
                                <i class="fa-solid fa-list-ol text-primary text-xs"></i>
                                Programmation
                            </h2>
                            <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">
                                <span x-text="sequences.length">0</span>&nbsp;séquence<span x-show="sequences.length > 1">s</span>
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-on-surface-variant">Sélectionnez une séquence pour voir ses notes et suivre l’antenne.</p>
                    </div>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                        <template x-if="canCrudCp">
                            <button
                                type="button"
                                @click="openCreateModal()"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-container hover:text-primary">
                                <i class="fa-solid fa-plus text-xs"></i>
                                Ajouter une séquence
                            </button>
                        </template>
                        <label class="relative w-full sm:w-auto">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-on-surface-variant"></i>
                            <input type="search"
                                   placeholder="Rechercher une séquence…"
                                   @input="filter = $event.target.value.trim().toLowerCase()"
                                   class="w-full min-w-0 rounded-lg border border-outline/30 bg-white py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:min-w-[260px]">
                        </label>
                    </div>
                </div>
            </div>

            {{-- ================================================================ --}}
            {{--  ⏱️ CHRONO ÉMISSION — UN SEUL COMPTE À REBOURS POUR TOUT        --}}
            {{--  Le présentateur le démarre une fois ; il décompte ensuite les  --}}
            {{--  séquences les unes après les autres, dans l'ordre.            --}}
            {{-- ================================================================ --}}
            <div class="border-b border-outline/10 bg-gradient-to-r from-primary-fixed/15 via-white to-white px-4 py-3 sm:px-5"
                 :class="antenneAlerte ? 'ring-2 ring-inset ring-error/30' : ''">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">

                    {{-- Compteur global écoulé / total --}}
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-lg transition-colors"
                             :class="antenneEnMarche() ? 'bg-primary text-white animate-pulse' : (antenneTerminee() ? 'bg-green-600 text-white' : 'bg-primary/10 text-primary')">
                            <i class="fa-solid fa-stopwatch"></i>
                        </div>
                        <div>
                            <div class="uppercase tracking-wider text-xs font-semibold text-on-surface-variant">
                                Chrono de l'émission
                            </div>
                            <div class="flex items-baseline gap-1.5 font-mono tabular-nums leading-none">
                                <span class="text-2xl font-black"
                                      :class="antenneEnMarche() ? 'text-primary' : (antenneTerminee() ? 'text-green-700' : 'text-on-surface')"
                                      x-text="formatSec(antenneEcouleSec())">00:00</span>
                                <span class="text-xs text-on-surface-variant">/</span>
                                <span class="text-xs text-on-surface-variant" x-text="formatSec(antenneTotalSec())">00:00</span>
                            </div>
                        </div>
                    </div>

                    {{-- Progression + état + séquence à l'antenne --}}
                    <div class="min-w-[180px] flex-1">
                        <div class="h-2 w-full overflow-visible lg:overflow-hidden rounded-full bg-surface-container-high">
                            <div class="h-full rounded-full transition-all duration-700"
                                 :class="antenneTerminee() ? 'bg-green-600' : (antenneEnMarche() ? 'bg-primary' : 'bg-outline/40')"
                                 :style="'width:' + antenneProgression() + '%'"></div>
                        </div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span x-show="antenneEnMarche()" class="inline-flex items-center gap-1.5 font-bold uppercase tracking-wider text-primary">
                                <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-primary"></span> En cours
                            </span>
                            <span x-show="! antenneEnMarche() && antenneDemarree() && ! antenneTerminee()"
                                  class="inline-flex items-center gap-1.5 font-bold uppercase tracking-wider text-amber-700"><i class="fa-solid fa-pause text-xs" aria-hidden="true"></i> En pause</span>
                            <span x-show="antenneTerminee()"
                                  class="inline-flex items-center gap-1.5 font-bold uppercase tracking-wider text-green-700"><i class="fa-solid fa-check text-xs" aria-hidden="true"></i> Terminé</span>
                            <span x-show="! antenneDemarree()" class="inline-flex items-center gap-1.5 italic text-on-surface-variant/80"><i class="fa-regular fa-circle text-xs" aria-hidden="true"></i> Prêt</span>

                            <template x-if="antenneDemarree() && ! antenneTerminee() && sequenceCourante()">
                                <span class="text-on-surface-variant">
                                    Séquence <b class="text-on-surface" x-text="sequenceCourante().position"></b>
                                    · reste <b class="font-mono text-primary" x-text="formatSec(seqRestantSec(sequenceCourante()))"></b>
                                </span>
                            </template>
                        </div>
                    </div>

                    {{-- Pilote : Démarrer / Pause / Réinitialiser --}}
                    @include('_partials.antenne_controls')
                </div>
            </div>

            @if($canDeleteCpGlobale && !$conducteur->date_locked_at)
            @include('_partials.bulk-delete-toolbar', ['resource' => 'sequences-emission', 'parentId' => $conducteur->id])
            @endif
            <div class="flex-1 min-w-0 overflow-y-auto bg-surface-container-lowest/40" data-bulk-list="bulk-sequences-emission">
                <template x-if="sequences.length === 0">
                    <div class="flex h-full items-center justify-center p-8 sm:p-12">
                        <div class="w-full max-w-md rounded-2xl border border-outline/15 bg-white p-8 text-center shadow-sm">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl text-primary">
                                <i class="fa-solid fa-list-timeline"></i>
                            </div>
                            <h3 class="mb-1 text-lg font-bold text-on-surface">Aucune séquence pour l'instant</h3>
                            <p class="mb-6 text-sm leading-relaxed text-on-surface-variant">
                                Ajoutez la première séquence de votre émission : générique, reportage, débat, chronique, conclusion…
                            </p>
                            <div class="flex flex-wrap items-center justify-center gap-3">
                                <template x-if="canCrudCp">
                                    <button
                                        type="button"
                                        @click="openCreateModal()"
                                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 font-semibold text-white shadow-sm transition-colors hover:bg-primary-container hover:text-primary">
                                        <i class="fa-solid fa-plus text-xs"></i>
                                        Ajouter la première séquence
                                    </button>
                                </template>
                                <a
                                    :href="routes.redaction"
                                    class="inline-flex items-center gap-2 rounded-lg border border-outline/30 px-4 py-2 text-sm font-medium text-on-surface transition-colors hover:bg-surface-container-low">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    Ouvrir la Vue Rédaction
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="hidden min-[1280px]:block overflow-x-auto">
                <table x-show="sequences.length > 0" class="w-full border-collapse text-left text-xs" id="cp-table">
                    <thead class="sticky top-0 z-10 bg-white/95 backdrop-blur">
                        <tr class="border-b border-outline-variant/50">
                            <th class="w-16 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Ordre</th>
                            <th class="hidden w-24 px-3 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant lg:table-cell">Heure début</th>
                            <th class="hidden w-24 px-3 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant lg:table-cell">Heure fin</th>
                            <th class="hidden w-20 px-3 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant lg:table-cell">Durée</th>
                            <th class="w-36 px-3 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Type</th>
                            <th class="min-w-[180px] px-3 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant lg:min-w-[300px]">Titre / sujet</th>
                            <th class="w-36 px-3 py-3 text-xs font-semibold uppercase tracking-wider text-on-surface-variant" title="Chrono de l'émission : temps écoulé dans chaque séquence (dans l'ordre)">⏱️ Antenne</th>
                            <th class="sticky right-0 top-0 z-30 w-24 bg-white/95 px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="visibleSequences.length === 0">
                            <tr>
                                <td colspan="8" class="p-12 text-center text-sm italic text-on-surface-variant">
                                    Aucune séquence ne correspond à votre recherche.
                                </td>
                            </tr>
                        </template>

                        <template x-for="s in visibleSequences" :key="s.id">
                            <tr
                                @click="selectSequence(s)"
                                :class="rowActiveClass(s) + (s.alerte_info ? ' bg-red-50/60 animate-pulse' : '')"
                                class="group relative cursor-pointer border-b border-outline-variant/20 transition-colors hover:bg-surface-container-low/70"
                                :data-conducteur-id="s.conducteur_id"
                                :data-seq-id="s.id">

                                <td class="px-3 py-3 text-center align-top" @click.stop="selectSequence(s)" :title="'Conducteur '+s.code_conducteur+' · Ligne '+s.code_ligne">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold"
                                              :class="s.id === activeSequenceId ? 'bg-primary text-white shadow-sm' : 'border border-outline/30 bg-surface-container text-on-surface'">
                                            <span x-text="s.position"></span>
                                        </span>
                                        <template x-if="canReorderCp">
                                            <span class="inline-flex items-center gap-1">
                                                <button type="button" @click.stop="deplacerCpHaut(sequences.indexOf(s))"
                                                        :disabled="reorderEnCours || sequences.indexOf(s) === 0"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-outline/25 text-on-surface-variant hover:bg-primary/10 hover:text-primary disabled:opacity-30"
                                                        :aria-label="'Monter ' + s.title" title="Monter la séquence">
                                                    <i class="fa-solid fa-chevron-up text-xs" aria-hidden="true"></i>
                                                </button>
                                                <button type="button" @click.stop="deplacerCpBas(sequences.indexOf(s))"
                                                        :disabled="reorderEnCours || sequences.indexOf(s) === sequences.length - 1"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-outline/25 text-on-surface-variant hover:bg-primary/10 hover:text-primary disabled:opacity-30"
                                                        :aria-label="'Descendre ' + s.title" title="Descendre la séquence">
                                                    <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-3 align-top font-mono text-sm tabular-nums lg:table-cell" @click.stop="selectSequence(s)" :class="s.id === activeSequenceId ? 'font-semibold text-on-surface' : 'text-on-surface'"><span x-text="s.heure_debut_hm"></span></td>
                                <td class="hidden px-3 py-3 align-top font-mono text-sm tabular-nums text-on-surface-variant lg:table-cell" @click.stop="selectSequence(s)"><span x-text="s.heure_fin_hm"></span></td>
                                <td class="hidden px-3 py-3 align-top font-mono text-sm tabular-nums text-on-surface-variant lg:table-cell" @click.stop="selectSequence(s)">
                                    <span class="inline-flex items-center gap-1 rounded-full border border-outline/20 bg-surface-container px-2 py-0.5 text-xs font-semibold">
                                        <i class="fa-regular fa-clock text-xs text-on-surface-variant"></i>
                                        <span x-text="s.duree_hm"></span>
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top" @click.stop="selectSequence(s)">
                                    <span class="inline-flex items-center rounded-full border border-transparent px-2 py-0.5 text-xs font-semibold" :class="s.type_badge_class || 'bg-slate-100 text-slate-700'">
                                        <span x-text="s.type_label"></span>
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top" @click.stop="selectSequence(s)">
                                    <div class="flex items-start gap-1.5 font-semibold leading-snug text-on-surface transition-colors line-clamp-2 group-hover:text-primary" :class="s.id === activeSequenceId ? 'text-primary' : ''">
                                        <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 text-sm text-primary" @click.stop @mousedown.stop><input type="checkbox" data-bulk-item form="bulk-sequences-emission" :value="s.id" :disabled="!(canDeleteCp && s.can_delete_cp)" :aria-label="'Sélectionner '+ s.title" class="h-5 w-5 rounded border-outline-variant text-primary"><span class="sr-only">Sélectionner</span></label>
                                        <span class="min-w-0 flex-1" x-text="s.title"></span>
                                        <i class="fa-regular fa-note-sticky mt-1 shrink-0 text-xs opacity-60" x-show="s.notes_presentateur && String(s.notes_presentateur).trim().length > 0" title="Cette séquence possède une note présentateur"></i>
                                    </div>
                                    <div class="mt-1 text-xs italic text-on-surface-variant line-clamp-1" x-show="typeof s.intervenant_soustitre === 'string' && s.intervenant_soustitre.length > 0" x-text="'Avec ' + s.intervenant_soustitre"></div>
                                </td>
                                <td class="cursor-pointer px-3 py-3 align-top" @click.stop="selectSequence(s)">
                                    <div class="min-w-[124px] space-y-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-xs font-bold uppercase tracking-wider"
                                                  :class="{
                                                      'bg-surface-container text-on-surface-variant border-outline/20': seqEtat(s) === 'avenir',
                                                      'bg-primary text-white border-primary': seqEtat(s) === 'encours',
                                                      'tv-live-fill': seqEtat(s) === 'encours',
                                                      'bg-green-100 text-green-800 border-green-300': seqEtat(s) === 'termine',
                                                  }"><span x-text="seqEtatLabel(s)"></span></span>
                                            <span x-show="alerteActive(s)" class="inline-flex animate-pulse items-center gap-1 text-xs font-bold uppercase tracking-wider text-error"><i class="fa-solid fa-triangle-exclamation text-xs" aria-hidden="true"></i> dépassée</span>
                                        </div>
                                        <div class="flex items-baseline gap-1 font-mono text-xs tabular-nums" :class="alerteActive(s) ? 'font-bold text-error' : (seqEtat(s) === 'encours' ? 'font-semibold text-primary' : 'text-on-surface-variant/70')">
                                            <span x-text="formatSec(seqEcouleSec(s))">00:00</span>
                                            <span class="text-xs opacity-50">/</span>
                                            <span class="text-xs opacity-80" x-text="formatSec(dureeSeq(s))">00:00</span>
                                        </div>
                                        <div class="h-1.5 w-full overflow-visible lg:overflow-hidden rounded-full bg-surface-container-high">
                                            <div class="h-full rounded-full transition-all duration-500" :class="alerteActive(s) ? 'animate-pulse bg-error' : (seqEtat(s) === 'encours' ? 'bg-primary' : 'bg-outline/40')" :style="'width:' + seqProgression(s) + '%'"></div>
                                        </div>
                                        <div x-show="seqEtat(s) === 'encours'" x-cloak class="text-xs leading-tight text-on-surface-variant">reste <span class="font-mono font-bold tabular-nums text-primary" x-text="formatSec(seqRestantSec(s))"></span></div>
                                    </div>
                                </td>
                                <td class="sticky right-0 whitespace-nowrap bg-white px-3 py-3 text-right align-top">
                                    <template x-if="canCrudCp && s.can_crud_cp">
                                        <div class="inline-flex items-center gap-2 opacity-100 transition-opacity md:opacity-0 md:group-hover:opacity-100">
                                            <button type="button" @click.stop="openEditModal(s)" class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-on-surface-variant transition-colors hover:bg-primary-fixed/10 hover:text-primary" title="Modifier la séquence"><i class="fa-solid fa-pen text-xs"></i></button>
                                            <button type="button" @click.stop="deleteSequence(s)" class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-on-surface-variant transition-colors hover:bg-danger/10 hover:text-danger" :disabled="! (canDeleteCp && s.can_delete_cp)" :title="canDeleteCp && s.can_delete_cp ? 'Supprimer la séquence' : 'Suppression non autorisée'"><i class="fa-solid fa-trash text-xs"></i></button>
                                        </div>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>

                <div x-show="sequences.length > 0" id="cp-card-list" class="min-[1280px]:hidden p-3 sm:p-4 lg:p-5">
                    <div class="space-y-3">
                        <template x-for="s in visibleSequences" :key="`card-${s.id}`">
                            <article class="group rounded-2xl border bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                     :class="[rowActiveClass(s), s.id === activeSequenceId ? 'border-primary/40 ring-1 ring-primary/20' : 'border-outline/15']">
                                <div class="flex items-start gap-3">
                                    <label class="mt-0.5 inline-flex min-h-[44px] shrink-0 cursor-pointer items-center justify-center rounded-lg px-1 text-primary" @click.stop @mousedown.stop>
                                        <input type="checkbox" data-bulk-item form="bulk-sequences-emission" :value="s.id" :disabled="!(canDeleteCp && s.can_delete_cp)" :aria-label="'Sélectionner '+ s.title" class="h-5 w-5 rounded border-outline-variant text-primary">
                                        <span class="sr-only">Sélectionner</span>
                                    </label>

                                    <button type="button" @click="selectSequence(s)"
                                            class="flex min-w-0 flex-1 items-start gap-3 text-left rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                        <div class="flex shrink-0 flex-col items-center gap-1.5">
                                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-sm font-bold shadow-sm"
                                                  :class="s.id === activeSequenceId ? 'bg-primary text-white' : 'bg-surface-container text-on-surface border border-outline/20'"
                                                  x-text="s.position"></span>
                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                                  :class="seqEtat(s) === 'encours' ? 'bg-primary text-white border-primary tv-live-fill' : (seqEtat(s) === 'termine' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-surface-container text-on-surface-variant border-outline/20')"
                                                  x-text="seqEtatLabel(s)"></span>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                                      :class="s.type_badge_class || 'bg-slate-100 text-slate-700'"
                                                      x-text="s.type_label"></span>
                                                <span class="inline-flex items-center gap-1 rounded-full border border-outline/15 bg-surface-container-low px-2 py-0.5 text-xs font-mono tabular-nums text-on-surface-variant">
                                                    <i class="fa-regular fa-clock text-[10px]"></i>
                                                    <span x-text="s.heure_debut_hm + ' – ' + s.heure_fin_hm"></span>
                                                </span>
                                                <span class="text-xs font-semibold text-on-surface-variant" x-text="s.duree_hm"></span>
                                            </div>

                                            <h3 class="mt-2 text-base font-semibold leading-snug text-on-surface transition-colors group-hover:text-primary"
                                                :class="s.id === activeSequenceId ? 'text-primary' : ''"
                                                x-text="s.title"></h3>

                                            <p class="mt-1 text-sm text-on-surface-variant" x-show="s.intervenant_soustitre">
                                                <i class="fa-solid fa-microphone mr-1 text-xs text-primary/70"></i>
                                                <span x-text="'Avec ' + s.intervenant_soustitre"></span>
                                            </p>

                                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                                                <div class="min-w-[160px] flex-1">
                                                    <div class="mb-1 flex items-center justify-between text-xs">
                                                        <span class="font-medium text-on-surface-variant">Progression antenne</span>
                                                        <span class="font-mono tabular-nums text-on-surface-variant">
                                                            <span x-text="formatSec(seqEcouleSec(s))">00:00</span> / <span x-text="formatSec(dureeSeq(s))">00:00</span>
                                                        </span>
                                                    </div>
                                                    <div class="h-1.5 overflow-visible lg:overflow-hidden rounded-full bg-surface-container-high">
                                                        <div class="h-full rounded-full transition-all duration-500"
                                                             :class="alerteActive(s) ? 'bg-error animate-pulse' : (seqEtat(s) === 'encours' ? 'bg-primary' : 'bg-outline/40')"
                                                             :style="'width:' + seqProgression(s) + '%'"></div>
                                                    </div>
                                                </div>
                                                <div x-show="seqEtat(s) === 'encours'" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                                                    <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-primary"></span>
                                                    reste <span class="font-mono tabular-nums" x-text="formatSec(seqRestantSec(s))"></span>
                                                </div>
                                                <span x-show="alerteActive(s)" class="inline-flex items-center gap-1 rounded-full bg-error/10 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-error"><i class="fa-solid fa-triangle-exclamation text-xs" aria-hidden="true"></i> dépassée</span>
                                            </div>
                                        </div>
                                    </button>

                                    <template x-if="canReorderCp">
                                        <div class="flex shrink-0 flex-col items-center gap-1">
                                            <button type="button"
                                                    @click.stop="deplacerCpHaut(sequences.indexOf(s))"
                                                    :disabled="reorderEnCours || sequences.indexOf(s) === 0"
                                                    class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-outline/25 text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary disabled:opacity-30 disabled:cursor-not-allowed focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                    :aria-label="'Monter ' + s.title" title="Monter la séquence">
                                                <i class="fa-solid fa-chevron-up text-xs" aria-hidden="true"></i>
                                            </button>
                                            <button type="button"
                                                    @click.stop="deplacerCpBas(sequences.indexOf(s))"
                                                    :disabled="reorderEnCours || sequences.indexOf(s) === sequences.length - 1"
                                                    class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-outline/25 text-on-surface-variant transition-colors hover:bg-primary/10 hover:text-primary disabled:opacity-30 disabled:cursor-not-allowed focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                    :aria-label="'Descendre ' + s.title" title="Descendre la séquence">
                                                <i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </template>

                                    <template x-if="canCrudCp && s.can_crud_cp">
                                        <div class="flex shrink-0 flex-col items-center gap-1 sm:flex-row">
                                            <button type="button" @click.stop="openEditModal(s)"
                                                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-primary transition-colors hover:bg-primary/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                    :aria-label="'Modifier ' + s.title" title="Modifier la séquence">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button type="button" @click.stop="deleteSequence(s)"
                                                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-error transition-colors hover:bg-error/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-error"
                                                    :disabled="! (canDeleteCp && s.can_delete_cp)"
                                                    :aria-label="'Supprimer ' + s.title" title="Supprimer la séquence">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div x-show="visibleSequences.length === 0" class="rounded-2xl border border-dashed border-outline/30 bg-white px-6 py-10 text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-surface-container text-on-surface-variant">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <p class="text-sm font-medium text-on-surface">Aucune séquence ne correspond à votre recherche.</p>
                        <p class="mt-1 text-xs text-on-surface-variant">Essayez un autre mot-clé ou effacez le filtre.</p>
                    </div>
                </div>
            </div>

            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-outline/15 bg-white/90 px-4 py-3 text-xs text-on-surface-variant sm:px-5">
                <span class="inline-flex items-center gap-2">
                    <i class="fa-solid fa-stopwatch text-primary"></i>
                    Durée totale :
                    <span class="rounded-md border border-outline/20 bg-surface-container-low px-2 py-1 font-mono font-bold tabular-nums text-on-surface" x-text="dureeTotaleHM">00:00</span>
                </span>
                <span class="inline-flex items-center gap-2"
                      x-show="antenneDemarree() && ! antenneTerminee() && sequenceCourante()" x-cloak>
                    <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-primary"></span>
                    À l'antenne : séquence <span class="font-semibold text-primary" x-text="'#' + (sequenceCourante() ? sequenceCourante().position : '—')"></span>
                </span>
            </footer>
        </section>

        {{-- ==================================================================== --}}
        {{--  PANEL DROIT = NOTES PRÉSENTATEUR 500px ou PLEIN ÉCRAN              --}}
        {{-- ==================================================================== --}}
        <aside
            id="cp-rightpanel"
            :class="panelRightClass()"
            class="shrink-0 w-full lg:w-[500px] h-auto lg:h-auto max-w-full transition-[width,position,inset,padding] duration-300 ease-out flex flex-col bg-surface border-t lg:border-t-0 border-l border-outline/10 overflow-hidden shadow-[0_0_30px_-10px_rgba(0,0,0,0.15)] z-20">

            {{-- HEADER PANEL DROIT : close + fullscreen toggle + infos type séquence  --}}
            <header class="p-4 border-b border-outline/15 bg-surface-container-lowest"
                    x-effect="window.__tvCpSignaux && window.__tvCpSignaux(activeSequenceId, sequences, $el)">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <h3 class="text-xs uppercase tracking-wider font-semibold text-on-surface-variant">
                        Résumé · Séquence en cours
                    </h3>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="togglePrompteur"
                            class="inline-flex items-center justify-center w-11 h-11 rounded-lg text-on-surface-variant hover:text-primary hover:bg-primary-fixed/15 transition-colors"
                            :title="prompteurMode ? 'Quitter le prompteur' : 'Mode prompteur'">
                            <i :class="prompteurMode ? 'fa-solid fa-pen' : 'fa-solid fa-tv'" class="text-sm"></i>
                        </button>
                        <button
                            type="button"
                            @click="toggleFullscreen"
                            class="inline-flex items-center justify-center w-11 h-11 rounded-lg text-on-surface-variant hover:text-primary hover:bg-primary-fixed/15 transition-colors"
                            :title="isFullscreen ? 'Quitter le plein écran' : 'Plein écran'">
                            <i :class="isFullscreen ? 'fa-solid fa-compress' : 'fa-solid fa-up-right-and-down-left-from-center'" class="text-sm"></i>
                        </button>
                        <button
                            type="button"
                            @click="closePanel"
                            class="inline-flex items-center justify-center w-11 h-11 rounded-lg text-on-surface-variant hover:text-danger hover:bg-danger/10 transition-colors"
                            title="Fermer le panneau">
                            <i class="fa-solid fa-xmark text-base"></i>
                        </button>
                    </div>
                </div>

                <template x-if="current">
                    <div>
                        <div class="flex items-center flex-wrap gap-2 mb-2">
                            <div x-html="current.type_badge_html"></div>
                            <div x-html="current.statut_presentation_html" x-show="current.statut_presentation !== 'Avenir'"></div>
                        </div>
                        <h2 class="text-xl font-bold leading-tight text-on-surface line-clamp-3 mb-2" x-text="current.title"></h2>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs border-t border-outline/15 pt-3 mt-3">
                            <div class="flex items-start gap-2">
                                <i class="fa-regular fa-clock text-primary mt-0.5"></i>
                                <div>
                                    <div class="uppercase tracking-wider text-xs text-on-surface-variant font-semibold">Durée</div>
                                    <div class="font-mono tabular-nums text-sm font-semibold text-on-surface" x-text="current.duree_hm"></div>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fa-regular fa-user text-primary mt-0.5"></i>
                                <div>
                                    <div class="uppercase tracking-wider text-xs text-on-surface-variant font-semibold">Journaliste</div>
                                    <div class="text-sm font-medium text-on-surface truncate" :title="current.journaliste_nom">
                                        <span x-text="current.journaliste_nom || 'Non assigné'"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-2 col-span-2" x-show="current.intervenant_soustitre">
                                <i class="fa-solid fa-microphone text-primary mt-0.5"></i>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="uppercase tracking-wider text-xs text-on-surface-variant font-semibold">Intervenant invité</div>
                                        <span x-show="current.invitation_statut === 'confirmee'" x-cloak class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">Confirmé</span>
                                        <span x-show="current.invitation_statut === 'en_attente'" x-cloak class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-amber-100 text-amber-800">En attente</span>
                                        <span x-show="current.invitation_statut === 'absent'" x-cloak class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-red-100 text-red-800">Absent</span>
                                    </div>
                                    <div class="text-sm font-medium text-on-surface truncate" x-text="current.intervenant_soustitre"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="! current">
                    <div class="py-10 text-center text-on-surface-variant italic border-t border-dashed border-outline/20 mt-3">
                        <i class="fa-solid fa-hand-pointer text-4xl mb-3 opacity-50 block mx-auto"></i>
                        Sélectionnez une séquence pour afficher les notes.
                    </div>
                </template>
            </header>

            {{-- CORPS DU PANNEAU — défilable d'un seul bloc (chrono → angle → notes) --}}
            <div class="flex-1 min-h-0 overflow-y-auto cp-panel-scroll">

            {{-- ⏱️ RÉSUMÉ COMPACT DU CHRONO — l'affichage complet et les contrôles sont dans le bandeau de gauche --}}
            <div class="p-4 border-b border-outline/10 transition-colors"
                 :class="antenneAlerte ? 'bg-error/5' : (antenneEnMarche() ? 'bg-primary-fixed/10' : 'bg-surface-container-low/60')">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-stopwatch"
                           :class="antenneEnMarche() ? 'text-primary animate-pulse' : (antenneTerminee() ? 'text-green-700' : 'text-on-surface-variant')"></i>
                        <span class="font-mono tabular-nums text-xl font-bold"
                              :class="antenneTerminee() ? 'text-green-700' : (antenneEnMarche() ? 'text-primary' : 'text-on-surface-variant')"
                              x-text="formatSec(antenneEcouleSec())">00:00</span>
                        <span class="text-xs text-on-surface-variant font-mono tabular-nums"
                              x-text="'/ ' + formatSec(antenneTotalSec())"></span>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                          :class="antenneTerminee()
                              ? 'bg-green-600 text-white'
                              : (antenneEnMarche() ? 'bg-primary text-white' : (antenneDemarree() ? 'bg-amber-500 text-white' : 'bg-surface-container text-on-surface-variant'))"
                          x-text="antenneTerminee()
                              ? 'TERMINÉ'
                              : (antenneEnMarche() ? 'EN COURS' : (antenneDemarree() ? 'EN PAUSE' : 'PRÊT'))">
                    </span>
                    <template x-if="antenneDemarree() && ! antenneTerminee() && sequenceCourante()">
                        <div class="flex items-center gap-2 text-xs bg-white/70 border border-outline/15 rounded-lg px-3 py-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary text-white text-xs font-bold shrink-0"
                                  x-text="sequenceCourante().position"></span>
                            <span class="flex-1 min-w-0 truncate text-on-surface font-medium"
                                  x-text="sequenceCourante().title"></span>
                            <span class="font-mono tabular-nums text-xs">
                                <span class="text-on-surface-variant">reste </span>
                                <b class="text-primary" x-text="formatSec(seqRestantSec(sequenceCourante()))"></b>
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- BLOC ANGLE + NOTES WYSIWYG --}}
            <div class="flex flex-col">

                <template x-if="current && current.angle">
                    <div class="p-5 border-b border-outline/10 bg-primary-fixed/5">
                        <div class="flex items-start gap-2">
                            <i class="fa-solid fa-compass text-primary mt-0.5"></i>
                            <div>
                                <div class="uppercase tracking-wider text-xs text-on-surface-variant font-semibold mb-1">Angle éditorial</div>
                                <p class="text-sm text-on-surface leading-relaxed whitespace-pre-line" x-text="current.angle"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <section class="p-5 flex flex-col">

                    {{-- ONGLETS : Notes présentateur / Notes perso (privé) --}}
                    <div class="flex items-center gap-1 mb-3 border-b border-outline/15">
                        <button type="button"
                                @click="notesTab = 'presentateur'"
                                :class="notesTab === 'presentateur'
                                    ? 'border-primary text-primary bg-primary/5'
                                    : 'border-transparent text-on-surface-variant hover:text-primary'"
                                class="px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-pen text-xs"></i> Notes présentateur
                        </button>
                        <button type="button"
                                x-show="current && current.can_edit_notes_perso"
                                @click="notesTab = 'perso'"
                                :class="notesTab === 'perso'
                                    ? 'border-tertiary text-tertiary bg-tertiary/5'
                                    : 'border-transparent text-on-surface-variant hover:text-primary'"
                                class="px-3 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5">
                            <i class="fa-solid fa-lock text-xs"></i> Notes perso
                            <span class="text-xs px-1.5 py-0.5 rounded-full bg-tertiary/10 text-tertiary uppercase tracking-wider">privé</span>
                        </button>

                        <div class="ml-auto inline-flex items-center gap-2 text-xs text-on-surface-variant" x-show="notesLastSavedAt">
                            <i class="fa-regular fa-circle-check text-green-600"></i>
                            <span>Enregistré à <span x-text="notesLastSavedAt"></span></span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3" x-show="notesTab === 'presentateur'">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-pen text-primary"></i>
                            <h4 class="text-sm font-bold text-on-surface">Notes Présentateur</h4>
                            <span class="inline-flex items-center gap-1 text-xs uppercase tracking-wider font-semibold text-on-surface-variant">
                                <i class="fa-solid fa-circle-info text-xs"></i>
                                Mise en forme (gras, italique, listes)
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3" x-show="notesTab === 'perso'">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-lock text-tertiary"></i>
                            <h4 class="text-sm font-bold text-on-surface">Notes personnelles</h4>
                            <span class="inline-flex items-center gap-1 text-xs uppercase tracking-wider font-semibold text-tertiary">
                                <i class="fa-solid fa-eye-slash text-xs"></i>
                                Visible par vous uniquement
                            </span>
                        </div>
                    </div>

                    {{-- Toolbar TipTap Mini = 6 outils prototype : Gras Italique Puce Num Lien Séparateur --}}
                    <div
                        x-show="current && notesTab === 'presentateur'"
                        x-transition
                        class="flex flex-wrap items-center gap-1 p-2 bg-surface-container rounded-t-lg border border-outline/20 border-b-0">
                        <template x-for="btn in tiptapButtons" :key="btn.action">
                            <button
                                type="button"
                                @click="tiptapToolbarAction(btn.action)"
                                :class="tiptapActiveClass(btn.action, editorRevision)"
                                class="inline-flex items-center justify-center h-11 min-w-11 px-2 text-xs rounded hover:bg-primary-fixed/20 transition-colors"
                                :title="btn.title || btn.label"
                                :disabled="! canEditCurrentNotes">
                                <i :class="btn.icon" class="text-sm" x-show="btn.icon"></i>
                                <span class="text-xs font-semibold" x-show="btn.label && ! btn.icon" x-text="btn.label"></span>
                            </button>
                        </template>
                        <div class="w-px h-5 bg-outline/30 mx-1" aria-hidden="true"></div>
                        <button
                            type="button"
                            @click="tiptapClearFormat()"
                            class="inline-flex items-center justify-center h-11 px-3 text-xs rounded hover:bg-danger/10 text-on-surface-variant hover:text-danger transition-colors"
                            title="Effacer le formatage">
                            <i class="fa-solid fa-eraser text-xs mr-1"></i>Effacer
                        </button>
                        <button
                            type="button"
                            id="tv-assistant-script-btn"
                            class="inline-flex items-center justify-center h-11 px-3 text-xs rounded hover:bg-primary-fixed/20 transition-colors"
                            title="Ouvrir l'assistant IA pour la rédaction du script">
                            <i class="fa-solid fa-wand-magic-sparkles text-xs mr-1"></i> Assistant IA
                        </button>
                        <button
                            type="button"
                            @click="saveNotes(false)"
                            class="inline-flex items-center justify-center h-11 px-3 text-xs rounded hover:bg-primary-fixed/20 transition-colors"
                            title="Enregistrer les notes maintenant">
                            <i class="fa-solid fa-floppy-disk text-xs mr-1"></i> Enregistrer
                        </button>
                    </div>

                    {{-- Editor Mount --}}
                    <div
                        x-show="current && notesTab === 'presentateur'"
                        x-transition
                        class="border border-outline/20 rounded-b-lg bg-white">
                        <div
                            id="tiptap-cp-mount"
                            @keydown.debounce.1500ms="autoSaveIfDirty()"
                            @input="notesDirty = true; scheduleAutoSave()"
                            class="min-h-[340px] p-4 prose prose-sm max-w-none text-sm leading-relaxed focus:outline-none"
                            :class="{ 'opacity-60 pointer-events-none bg-surface-container/50': ! canEditCurrentNotes }"
                            data-placeholder="Saisissez ici les notes du présentateur… Exemples : intro lente sur ZLECAf, puis relance avec chroniqueur. Rappeler audimat fin JT…"></div>
                    </div>

                    {{-- NOTES PERSO (PRIVÉES) — SPEC §5.4 --}}
                    <div x-show="current && notesTab === 'perso'" x-transition class="flex flex-col">
                        <textarea
                            x-model="notesPersoDraft"
                            @input="notesPersoDirty = true; scheduleAutoSave()"
                            :disabled="! canEditCurrentNotesPerso"
                            placeholder="Vos notes personnelles… visibles par vous uniquement.&#10;&#10;Exemples : respirer avant le 3ᵉ sujet, ne pas couper l'invité sur le duplex, penser à la transition vers la météo…"
                            class="min-h-[340px] w-full p-4 text-sm leading-relaxed rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-tertiary/30 resize-none"
                            :class="{ 'opacity-60 bg-surface-container/50': ! canEditCurrentNotesPerso }"></textarea>
                        <p class="mt-2 text-xs text-tertiary flex items-start gap-1.5">
                            <i class="fa-solid fa-shield-halved mt-0.5"></i>
                            <span><strong>Confidentiel.</strong> Ces notes ne sont visibles que par vous (et le chef d'édition). Les autres rôles n'y ont pas accès.</span>
                        </p>
                    </div>

                    <div
                        x-show="current && ! canEditCurrentNotes && notesTab === 'presentateur'"
                        class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md p-2">
                        <i class="fa-solid fa-lock text-xs mr-1"></i>
                        Vous n'êtes pas autorisé à modifier les notes pour cette séquence.
                    </div>

                    <div class="mt-4 pt-4 border-t border-outline/15 flex flex-wrap items-center gap-3 justify-between">
                        <span class="text-xs text-on-surface-variant italic">
                            ✏️ Modifiable à tout moment · Enregistrement automatique.
                        </span>
                        <button
                            type="button"
                            @click="saveNotes()"
                            :disabled="isSaving || (notesTab === 'presentateur' ? (! canEditCurrentNotes || ! notesDirty) : (! canEditCurrentNotesPerso || ! notesPersoDirty))"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition-all
                                   bg-primary-container text-primary shadow-sm
                                   hover:bg-primary hover:text-white
                                   disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-primary-container disabled:hover:text-primary">
                            <i :class="isSaving ? 'fa-solid fa-spinner fa-spin' : 'fa-regular fa-floppy-disk'" class="text-xs"></i>
                            <span x-text="isSaving ? 'Enregistrement…' : 'Enregistrer notes'"></span>
                        </button>
                    </div>
                </section>
            </div>
            </div>{{-- fin corps défilable --}}

            {{-- ==================================================================== --}}
            {{--  MODE PROMPTEUR — overlay plein écran, défilement automatique        --}}
            {{-- ==================================================================== --}}
            <div x-show="prompteurMode" x-cloak
                 class="fixed inset-0 z-[999] bg-slate-900 text-white flex flex-col">
                <header class="flex flex-wrap items-center gap-3 px-4 py-3 bg-slate-800 border-b border-slate-700">
                    <button type="button" @click="prompteurScrolling ? stopPrompteurScroll() : startPrompteurScroll()"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-container transition-colors">
                        <i :class="prompteurScrolling ? 'fa-solid fa-pause' : 'fa-solid fa-play'"></i>
                        <span x-text="prompteurScrolling ? 'Pause' : 'Lire'"></span>
                    </button>
                    <button type="button" @click="resetPrompteurScroll()"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-600 text-slate-200 text-sm hover:bg-slate-700 transition-colors">
                        <i class="fa-solid fa-rotate-left"></i> Haut
                    </button>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-gauge-simple text-slate-400 text-xs"></i>
                        <input type="range" min="1" max="10" :value="prompteurSpeed"
                               @input="setPrompteurSpeed($event.target.value)"
                               class="w-24 accent-primary" aria-label="Vitesse de défilement">
                        <span class="text-xs text-slate-400 tabular-nums w-6" x-text="prompteurSpeed"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-text-height text-slate-400 text-xs"></i>
                        <input type="range" min="16" max="48" :value="prompteurFontSize"
                               @input="setPrompteurFontSize($event.target.value)"
                               class="w-24 accent-primary" aria-label="Taille des caractères">
                        <span class="text-xs text-slate-400 tabular-nums w-8" x-text="prompteurFontSize + 'px'"></span>
                    </div>
                    <div class="ml-auto flex items-center gap-3">
                        <div class="text-xs text-slate-400" x-show="current">
                            <span class="font-semibold text-slate-200" x-text="current ? current.title : ''"></span>
                            <span class="ml-2">·</span>
                            <span class="font-mono" x-text="current ? current.duree_hm : ''"></span>
                        </div>
                        <button type="button" @click="togglePrompteur"
                                class="inline-flex items-center justify-center w-11 h-11 rounded-lg text-slate-200 hover:text-white hover:bg-slate-700 transition-colors"
                                title="Quitter le prompteur">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                </header>
                <div id="prompteur-scroll" class="flex-1 overflow-y-auto px-6 py-8"
                     :style="'font-size:' + prompteurFontSize + 'px; line-height:1.6;'">
                    <div class="max-w-3xl mx-auto">
                        <h2 class="text-2xl font-bold mb-6 text-primary-fixed" x-text="current ? current.title : ''"></h2>
                        <div class="prompteur-text prose prose-invert max-w-none"
                             x-html="current ? (current.notes_presentateur || '<p class=\'italic text-slate-400\'>Aucune note pour cette séquence.</p>') : ''"></div>
                        <template x-if="nextSequence()">
                            <div class="mt-10 pt-6 border-t border-slate-700">
                                <div class="text-xs uppercase tracking-wider text-slate-400 mb-2">À suivre</div>
                                <div class="text-lg font-semibold text-slate-200" x-text="nextSequence().title"></div>
                                <div class="text-sm text-slate-400 font-mono" x-text="nextSequence().duree_hm"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </aside>

        {{-- ==================================================================== --}}
        {{--  MODALE CRUD SÉQUENCE 6 CHAMPS (Ajout / Modification)                 --}}
        {{-- ==================================================================== --}}
        <template x-if="showModal">
        <div x-cloak role="dialog" aria-modal="true" aria-labelledby="cp-modal-title"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4"
             x-transition.opacity.duration.200ms
             @keydown.escape.window="closeModal()">
            <div class="absolute inset-0 bg-slate-900/55 backdrop-blur-[2px]"
                 @click.self="closeModal()"></div>

            <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-outline/20 overflow-visible lg:overflow-hidden max-h-[90dvh] flex flex-col z-10"
                 x-transition.scale.origin.center.duration.200ms>

                <header class="px-6 py-4 border-b border-outline/15 flex flex-wrap items-center justify-between gap-3 bg-surface-container-lowest">
                    <div>
                        <h3 id="cp-modal-title" class="text-base font-bold text-on-surface"
                            x-text="modalTitle"></h3>
                        <p class="text-xs text-on-surface-variant mt-0.5 m-0 p-0 leading-relaxed"
                           x-text="modalSubtitle"></p>
                    </div>
                    <button
                        type="button"
                        @click="closeModal()"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-on-surface-variant hover:text-danger hover:bg-danger/10 transition-colors">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </header>

                <form id="cp-sequence-form" @submit.prevent="saveSequence()" class="flex-1 overflow-y-auto p-6 space-y-5" novalidate>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Ordre (position)
                            </label>
                            <input
                                type="number" min="1" max="999" step="1"
                                x-model.number="seqForm.position"
                                class="w-full border border-outline/30 rounded-lg px-3.5 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                            <p class="text-xs text-on-surface-variant mt-1"
                               x-text="modalOrdreHint"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Heure début <span class="text-error">*</span>
                            </label>
                            <input
                                type="time" step="1"
                                :value="seqForm.heure_debut"
                                @input="seqForm.heure_debut = $event.target.value; calculDureeSeqForm();"
                                required
                                class="w-full border border-outline/30 rounded-lg px-3.5 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Heure fin <span class="text-error">*</span>
                            </label>
                            <input
                                type="time" step="1"
                                :value="seqForm.heure_fin"
                                @input="seqForm.heure_fin = $event.target.value; calculDureeSeqForm();"
                                required
                                class="w-full border border-outline/30 rounded-lg px-3.5 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Titre / sujet <span class="text-error">*</span>
                            </label>
                            <input
                                type="text" maxlength="255" required
                                x-model.trim="seqForm.title"
                                placeholder="Ex: Reportage ZLECAf · Point COP · Interview invitée X"
                                class="w-full border border-outline/30 rounded-lg px-3.5 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                            <p class="text-xs text-on-surface-variant mt-1">
                                <span class="tabular-nums"
                                      x-text="(seqForm.title || '').length + ' / 255 caractères'"></span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Type <span class="text-error">*</span>
                            </label>
                            <select
                                required
                                x-model="seqForm.type"
                                class="w-full border border-outline/30 rounded-lg px-3.5 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                                <option value="">Sélectionner…</option>
                                <template x-for="t in typeOptions" :key="t.value">
                                    <option :value="t.value" x-text="t.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-start">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                Résumé (optionnel)
                            </label>
                            <textarea
                                rows="3" maxlength="5000"
                                x-model.trim="seqForm.summary"
                                placeholder="Petit résumé à l'intention du présentateur, ou angles clés à traiter en direct…"
                                class="w-full border border-outline/30 rounded-lg px-3.5 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors resize-none"></textarea>
                            <p class="text-xs text-on-surface-variant mt-1">
                                <span class="tabular-nums" x-text="(seqForm.summary || '').length + ' / 5 000 caractères'"></span>
                            </p>
                        </div>
                        <div class="md:col-span-1">
                            <div class="p-4 rounded-xl bg-primary-fixed/5 border border-primary-container/50 h-full">
                                <div class="text-xs uppercase tracking-wider text-on-surface-variant font-semibold mb-2">
                                    <i class="fa-regular fa-clock mr-1.5"></i> Durée estimée
                                </div>
                                <div class="text-3xl font-bold font-mono tabular-nums text-primary"
                                     x-text="dureeSeqFormHM || '--:--'"></div>
                                <p class="text-xs text-on-surface-variant/80 mt-2 leading-relaxed">
                                    Calculée automatiquement selon heure début et fin.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Intervenants invités — réservé aux séquences de type Débat --}}
                    <div x-show="String(seqForm.type || '').toUpperCase() === 'DÉBAT'" x-cloak>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Intervenants invités
                        </label>
                        <div class="flex gap-2">
                            <div class="flex-1 min-w-0 relative">
                                <input type="search"
                                       x-model="inviteSearchCp"
                                       @focus="inviteDropdownOpenCp = true"
                                       @click.outside="inviteDropdownOpenCp = false"
                                       placeholder="Rechercher un invité (nom, fonction)…"
                                       class="w-full border border-outline/30 rounded-lg px-3.5 py-2.5 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-colors">
                                <div x-show="inviteDropdownOpenCp" x-cloak
                                     class="absolute z-30 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border border-outline bg-white shadow-lg">
                                    @foreach($intervenants as $int)
                                        <button type="button"
                                                x-show="intervenantCorrespondCp('{{ addslashes(strtolower((is_object($int->civilite) && method_exists($int->civilite, 'label') ? $int->civilite->label() : (string)($int->civilite?->value ?? $int->civilite)).' '.$int->prenom.' '.$int->nom.' '.($int->fonction ?? ''))) }}', inviteSearchCp)"
                                                @click="inviteSelectionCp = {{ $int->id }}; ajouterInviteCp();"
                                                :disabled="seqForm.intervenant_ids.includes({{ $int->id }})"
                                                class="w-full text-left px-3 py-2 hover:bg-surface-container transition-colors flex items-center justify-between gap-2 border-b border-outline/10 last:border-0 disabled:opacity-40 disabled:cursor-not-allowed">
                                            <span class="text-sm text-on-surface">{{ is_object($int->civilite) && method_exists($int->civilite, 'label') ? $int->civilite->label() : (string)($int->civilite?->value ?? $int->civilite) }} {{ $int->prenom }} {{ $int->nom }}</span>
                                            <span class="text-xs text-on-surface-variant">{{ $int->fonction ?? '' }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <button type="button"
                                    @click="ajouterInviteCp()"
                                    :disabled="String(seqForm.type || '').toUpperCase() === 'DÉBAT' && (seqForm.intervenant_ids || []).length >= 4"
                                    class="min-h-11 min-w-11 px-3 border border-outline/30 rounded-lg text-primary hover:bg-surface-container transition-colors font-semibold text-sm whitespace-nowrap disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="fa-solid fa-plus mr-1"></i> Ajouter
                            </button>
                        </div>
                        <div x-show="(seqForm.intervenant_ids || []).length > 0" x-cloak class="mt-3 space-y-2">
                            <template x-for="(inviteId, inviteIdx) in (seqForm.intervenant_ids || [])" :key="inviteId">
                                <div class="flex items-center gap-2 border border-outline/30 rounded-lg px-2 py-1.5 bg-surface-container-low">
                                    <span class="w-6 h-6 shrink-0 rounded-full bg-primary-container/20 text-primary text-xs font-bold flex items-center justify-center"
                                          x-text="inviteIdx + 1"></span>
                                    <span class="flex-1 min-w-0 text-sm truncate" x-text="labelIntervenantCp(inviteId)"></span>
                                    <button type="button" @click="retirerInviteCp(inviteIdx)"
                                            class="w-8 h-8 shrink-0 flex items-center justify-center rounded text-error hover:bg-error-container transition-colors"
                                            title="Retirer" aria-label="Retirer l'invité">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </template>
                            <p x-show="String(seqForm.type || '').toUpperCase() === 'DÉBAT'" class="text-xs text-on-surface-variant mt-2">
                                <i class="fa-solid fa-circle-info mr-1"></i>
                                Pour un débat, sélectionnez <strong>2 à 4 intervenants</strong>.
                            </p>
                        </div>
                    </div>

                    <div
                        x-show="modalErrors && modalErrors.length > 0"
                        class="p-3 rounded-lg border border-error/30 bg-error/5 text-sm text-error flex items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0"></i>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <template x-for="(msg, idx) in modalErrors" :key="idx">
                                <li x-text="msg"></li>
                            </template>
                        </ul>
                    </div>
                </form>

                <footer class="sticky bottom-0 px-6 py-4 border-t border-outline/15 bg-surface-container-lowest flex flex-wrap items-center justify-end gap-3">
                    <button type="button"
                            @click="closeModal()"
                            class="px-5 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 font-medium text-sm">
                        Annuler
                    </button>
                    <button
                        type="submit"
                        form="cp-sequence-form"
                        :disabled="isSavingSeq"
                        class="inline-flex items-center gap-2 px-6 py-2 rounded-lg bg-primary text-white hover:bg-primary-container hover:text-primary font-semibold text-sm shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <i :class="isSavingSeq ? 'fa-solid fa-spinner fa-spin' : 'fa-regular fa-floppy-disk'" class="text-xs"></i>
                        <span x-text="isSavingSeq ? 'Enregistrement…' : modalSubmitLabel"></span>
                    </button>
                </footer>
            </div>
        </div>
        </template>
    </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const panel = document.querySelector('[data-emission-interactions][data-endpoint]');
                if (!panel) return;

                const messageList = panel.querySelector('[data-message-list]');
                const emptyState = panel.querySelector('[data-message-empty]');
                const count = panel.querySelector('[data-message-count]');
                const status = panel.querySelector('[data-message-status]');
                const endpoint = panel.dataset.endpoint;
                let lastKnownCount = parseInt(count?.textContent || '0', 10) || 0;

                let titleFlashTimer = null;
                let originalTitle = document.title;

                function notifyNewMessages(delta) {
                    if (!delta || delta < 1) return;

                    panel.open = true;
                    panel.classList.remove('tv-public-alert');
                    void panel.offsetWidth;
                    panel.classList.add('tv-public-alert');
                    window.setTimeout(() => panel.classList.remove('tv-public-alert'), 7000);

                    const summary = panel.querySelector('summary');
                    if (summary) {
                        summary.classList.remove('tv-public-summary-flash');
                        void summary.offsetWidth;
                        summary.classList.add('tv-public-summary-flash');
                        window.setTimeout(() => summary.classList.remove('tv-public-summary-flash'), 6000);
                    }

                    const badge = panel.querySelector('[data-message-count]');
                    if (badge) {
                        badge.classList.remove('tv-public-badge-pulse');
                        void badge.offsetWidth;
                        badge.classList.add('tv-public-badge-pulse');
                        window.setTimeout(() => badge.classList.remove('tv-public-badge-pulse'), 6000);
                    }

                    if (messageList) {
                        messageList.scrollTop = 0;
                        const first = messageList.firstElementChild;
                        if (first) {
                            first.classList.remove('tv-public-message-new');
                            void first.offsetWidth;
                            first.classList.add('tv-public-message-new');
                            window.setTimeout(() => first.classList.remove('tv-public-message-new'), 8000);
                        }
                    }

                    const existing = document.querySelector('[data-public-toast]');
                    if (existing) existing.remove();
                    const toast = document.createElement('div');
                    toast.setAttribute('data-public-toast', '1');
                    toast.setAttribute('role', 'alert');
                    toast.setAttribute('aria-live', 'assertive');
                    toast.className = 'fixed inset-x-4 top-20 z-[140] mx-auto max-w-lg sm:inset-x-auto sm:right-4 sm:mx-0 tv-public-toast-enter';
                    toast.innerHTML = ''
                        + '<div class="relative overflow-visible lg:overflow-hidden rounded-2xl border-2 border-primary bg-white shadow-[0_20px_60px_-15px_rgba(0,84,205,0.55)]">'
                        + '  <div class="absolute inset-y-0 left-0 w-2 bg-primary tv-public-toast-bar"></div>'
                        + '  <div class="flex items-start gap-4 p-4 pl-5">'
                        + '    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary text-white text-xl tv-public-icon-ring"><i class="fa-solid fa-comments"></i></span>'
                        + '    <div class="min-w-0 flex-1">'
                        + '      <p class="text-base font-extrabold uppercase tracking-wide text-primary">Nouveau message du public</p>'
                        + '      <p class="mt-0.5 text-sm font-semibold text-on-surface">' + (delta === 1 ? '1 nouvelle réaction vient d’arriver.' : delta + ' nouvelles réactions viennent d’arriver.') + '</p>'
                        + '      <p class="mt-1 text-xs text-on-surface-variant">Ouvrez le panneau « Messages du public » pour lire le message.</p>'
                        + '    </div>'
                        + '  </div>'
                        + '</div>';
                    document.body.appendChild(toast);
                    window.setTimeout(() => toast.remove(), 7000);

                    const overlay = document.createElement('div');
                    overlay.setAttribute('data-public-overlay', '1');
                    overlay.className = 'pointer-events-none fixed inset-0 z-[130] tv-public-screen-flash';
                    document.body.appendChild(overlay);
                    window.setTimeout(() => overlay.remove(), 1600);

                    if (navigator.vibrate) {
                        try { navigator.vibrate([120, 60, 120]); } catch (e) {}
                    }

                    if (titleFlashTimer) window.clearInterval(titleFlashTimer);
                    originalTitle = document.title.replace(/^\(\d+\)\s*🔔\s*/, '');
                    let on = false;
                    let ticks = 0;
                    titleFlashTimer = window.setInterval(() => {
                        on = !on;
                        ticks++;
                        document.title = on ? '(' + delta + ') 🔔 MESSAGE PUBLIC — ' + originalTitle : originalTitle;
                        if (ticks >= 12) {
                            window.clearInterval(titleFlashTimer);
                            titleFlashTimer = null;
                            document.title = originalTitle;
                        }
                    }, 700);
                }

                function renderMessages(messages) {
                    messageList.replaceChildren();

                    messages.forEach((message) => {
                        const item = document.createElement('li');
                        item.className = 'space-y-1 px-3 py-2.5';

                        const heading = document.createElement('div');
                        heading.className = 'flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs';

                        const sender = document.createElement('span');
                        sender.className = 'font-semibold text-on-surface';
                        sender.textContent = message.name || 'Téléspectateur';

                        if (message.recipient) {
                            const recipient = document.createElement('span');
                            recipient.className = 'font-normal text-on-surface-variant';
                            recipient.textContent = ` · à ${message.recipient}`;
                            sender.append(recipient);
                        }

                        const time = document.createElement('time');
                        time.className = 'text-on-surface-variant';
                        time.dateTime = message.created_at || '';
                        time.textContent = message.created_at_label || '';
                        heading.append(sender, time);

                        const content = document.createElement('p');
                        content.className = 'whitespace-pre-line break-words text-sm text-on-surface';
                        content.textContent = message.message;

                        item.append(heading, content);
                        messageList.append(item);
                    });

                    messageList.hidden = messages.length === 0;
                    emptyState.hidden = messages.length > 0;
                }

                async function refreshMessages() {
                    if (document.visibilityState === 'visible') {
                        try {
                            const response = await fetch(endpoint, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });
                            if (!response.ok) {
                                throw new Error(`Actualisation impossible (${response.status}).`);
                            }

                            const data = await response.json();
                            const nextCount = parseInt(data.count || 0, 10) || 0;
                            if (count) count.textContent = String(nextCount);
                            renderMessages(Array.isArray(data.messages) ? data.messages : []);
                            if (nextCount > lastKnownCount) {
                                notifyNewMessages(nextCount - lastKnownCount);
                                status.textContent = (nextCount - lastKnownCount) === 1
                                    ? 'Nouveau message du public reçu.'
                                    : (nextCount - lastKnownCount) + ' nouveaux messages du public reçus.';
                            } else {
                                status.textContent = 'Les questions du public sont actualisées.';
                            }
                            lastKnownCount = nextCount;
                        } catch (error) {
                            console.error('Erreur d’actualisation des questions du public :', error);
                            status.textContent = 'Les questions du public n’ont pas pu être actualisées.';
                        }
                    }

                    window.setTimeout(refreshMessages, 5000);
                }

                window.setTimeout(refreshMessages, 5000);
            })();
        </script>

        <script>
            {{-- SIGNAUX ANTENNE : réactions des ALERTES INFO et de la SÉQUENCE EN COURS,
                 avec le même vocabulaire visuel que les messages du public (toast,
                 flash écran, clignotement du titre d'onglet, vibration).
                 Le suivi est alimenté par l'app Alpine : x-effect sur le panneau
                 « Résumé · Séquence en cours » (cf. header ci-dessus) déclenche
                 window.__tvCpSignaux à chaque changement d'état (polling liveState
                 ou temps réel Echo). --}}

            (() => {
                const BLEU = '#0054cd';
                const ROUGE = '#ba1a1a';
                const DUREE_TOAST = 7000;
                const CLASSE_FLASH_PANNEAU = 'tv-public-summary-flash';

                let baseAlertes = null;
                let baseActive = null;
                let titreTimer = null;

                const echapper = (valeur) => {
                    const div = document.createElement('div');
                    div.textContent = valeur === null || valeur === undefined ? '' : String(valeur);
                    return div.innerHTML;
                };

                const titreDeBase = () => document.title.replace(/^\(\d+\)\s*/, '')
                    .replace(/(?:🔔|⚠️|▶)\s*(?:MESSAGE PUBLIC|ALERTE INFO|SÉQUENCE EN COURS)\s*—\s*/g, '');

                function flashTitre(label) {
                    if (titreTimer) window.clearInterval(titreTimer);
                    const base = titreDeBase();
                    let allume = false;
                    let battements = 0;
                    titreTimer = window.setInterval(() => {
                        allume = !allume;
                        battements++;
                        document.title = allume ? '⚠️ ' + label + ' — ' + base : base;
                        if (battements >= 10) {
                            window.clearInterval(titreTimer);
                            titreTimer = null;
                            document.title = base;
                        }
                    }, 700);
                }

                function flashEcran(gradient) {
                    const overlay = document.createElement('div');
                    overlay.className = 'pointer-events-none fixed inset-0 z-[130] tv-public-screen-flash';
                    overlay.style.background = gradient;
                    document.body.appendChild(overlay);
                    window.setTimeout(() => overlay.remove(), 1600);
                }

                function vibrer(motif) {
                    if (navigator.vibrate) {
                        try { navigator.vibrate(motif); } catch (e) {}
                    }
                }

                function toastSignal({ couleur, icon, titre, message, note }) {
                    const ancien = document.querySelector('[data-cp-toast]');
                    if (ancien) ancien.remove();
                    const toast = document.createElement('div');
                    toast.setAttribute('data-cp-toast', '1');
                    toast.setAttribute('role', 'alert');
                    toast.setAttribute('aria-live', 'assertive');
                    toast.className = 'fixed inset-x-4 top-20 z-[140] mx-auto max-w-lg sm:inset-x-auto sm:right-4 sm:mx-0 tv-public-toast-enter';
                    toast.innerHTML = ''
                        + '<div class="relative overflow-visible lg:overflow-hidden rounded-2xl border-2 bg-white shadow-[0_20px_60px_-15px_rgba(0,0,0,0.45)]" style="border-color:' + couleur + '">'
                        + '  <div class="absolute inset-y-0 left-0 w-2 tv-public-toast-bar" style="background:' + couleur + '"></div>'
                        + '  <div class="flex items-start gap-4 p-4 pl-5">'
                        + '    <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-white text-xl tv-public-icon-ring" style="background:' + couleur + '"><i class="fa-solid ' + icon + '"></i></span>'
                        + '    <div class="min-w-0 flex-1">'
                        + '      <p class="text-base font-extrabold uppercase tracking-wide" style="color:' + couleur + '">' + echapper(titre) + '</p>'
                        + '      <p class="mt-0.5 text-sm font-semibold text-on-surface">' + echapper(message) + '</p>'
                        + (note ? '      <p class="mt-1 text-xs text-on-surface-variant">' + echapper(note) + '</p>' : '')
                        + '    </div>'
                        + '  </div>'
                        + '</div>';
                    document.body.appendChild(toast);
                    window.setTimeout(() => toast.remove(), DUREE_TOAST);
                }

                function flasherPanneau(panneau) {
                    if (! panneau) return;
                    panneau.classList.remove(CLASSE_FLASH_PANNEAU);
                    void panneau.offsetWidth;
                    panneau.classList.add(CLASSE_FLASH_PANNEAU);
                    window.setTimeout(() => panneau.classList.remove(CLASSE_FLASH_PANNEAU), 6000);
                }

                const chercherSequence = (sequences, id) => (Array.isArray(sequences) ? sequences : [])
                    .find((s) => s && +s.id === +id) || null;

                window.__tvCpSignaux = function (activeId, sequences, panneau) {
                    const idsAlertes = new Set((Array.isArray(sequences) ? sequences : [])
                        .filter((s) => s && s.alerte_info)
                        .map((s) => +s.id));
                    const idActif = +activeId || 0;

                    if (baseAlertes === null || baseActive === null) {
                        baseAlertes = idsAlertes;
                        baseActive = idActif;
                        return;
                    }

                    const visible = document.visibilityState === 'visible';

                    // --- ALERTES INFO : une nouvelle séquence passe en alerte ---
                    const nouvellesAlertes = [];
                    idsAlertes.forEach((id) => {
                        if (! baseAlertes.has(id)) nouvellesAlertes.push(id);
                    });
                    baseAlertes = idsAlertes;

                    if (visible && nouvellesAlertes.length) {
                        const seq = chercherSequence(sequences, nouvellesAlertes[0]);
                        toastSignal({
                            couleur: ROUGE,
                            icon: 'fa-triangle-exclamation',
                            titre: 'Alerte info',
                            message: seq ? '« ' + (seq.title || 'Séquence') + ' »' : 'Une séquence du déroulé.',
                            note: nouvellesAlertes.length === 1
                                ? 'Cette séquence est passée en alerte info.'
                                : nouvellesAlertes.length + ' séquences sont passées en alerte info.',
                        });
                        flashEcran('radial-gradient(ellipse at top, rgba(186,26,26,.30), rgba(186,26,26,0) 60%)');
                        vibrer([120, 60, 120]);
                        flashTitre('ALERTE INFO');
                    }

                    // --- SÉQUENCE EN COURS : le conducteur suit une nouvelle séquence ---
                    if (idActif && idActif !== baseActive) {
                        const seq = chercherSequence(sequences, idActif);
                        if (visible) {
                            toastSignal({
                                couleur: BLEU,
                                icon: 'fa-bullseye',
                                titre: 'Séquence en cours',
                                message: seq
                                    ? ((seq.position ? '#' + seq.position + ' · ' : '') + (seq.title || 'Séquence'))
                                    : 'La séquence active a changé.',
                                note: 'Le déroulé antenne suit la nouvelle séquence active.',
                            });
                            flasherPanneau(panneau);
                            flashTitre('SÉQUENCE EN COURS');
                            vibrer([80, 40, 80]);
                        }
                    }
                    baseActive = idActif;
                };
            })();
        </script>
    @endpush

    @include('_partials.emission-chat', ['conducteur' => $conducteur])
@endsection


{{-- Styles du panneau de notes : ascenseur discret, défilement fluide --}}
@push('styles')
<style>
    /* Corps du panneau « Résumé · Séquence en cours » : un seul flux défilable */
    .cp-panel-scroll {
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 26, 65, .22) transparent;
    }
    .cp-panel-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .cp-panel-scroll::-webkit-scrollbar-track { background: transparent; }
    .cp-panel-scroll::-webkit-scrollbar-thumb {
        background: rgba(0, 26, 65, .22);
        border-radius: 999px;
    }
    .cp-panel-scroll::-webkit-scrollbar-thumb:hover { background: rgba(0, 26, 65, .38); }

    /* L'éditeur de notes garde une zone d'écriture exploitable */
    #tiptap-cp-mount:empty::before {
        content: attr(data-placeholder);
        color: rgba(0, 26, 65, .38);
        pointer-events: none;
        display: block;
    }

    @keyframes tv-public-alert-ring {
        0%, 100% { box-shadow: 0 0 0 0 rgba(0, 84, 205, 0); border-color: rgba(0,0,0,.12); background-color: #fff; }
        10% { box-shadow: 0 0 0 6px rgba(0, 84, 205, .35), 0 0 0 14px rgba(0, 84, 205, .12); border-color: rgba(0, 84, 205, .8); background-color: rgba(219, 234, 254, .55); }
        30% { box-shadow: 0 0 0 3px rgba(0, 84, 205, .22), 0 0 0 8px rgba(0, 84, 205, .08); border-color: rgba(0, 84, 205, .55); background-color: #fff; }
        50% { box-shadow: 0 0 0 6px rgba(0, 84, 205, .3), 0 0 0 14px rgba(0, 84, 205, .1); border-color: rgba(0, 84, 205, .75); background-color: rgba(219, 234, 254, .45); }
        70% { box-shadow: 0 0 0 2px rgba(0, 84, 205, .18), 0 0 0 6px rgba(0, 84, 205, .06); border-color: rgba(0, 84, 205, .45); background-color: #fff; }
    }
    @keyframes tv-public-badge-pulse {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(0,84,205,0); }
        15% { transform: scale(1.45); box-shadow: 0 0 0 10px rgba(0,84,205,.25); }
        30% { transform: scale(1.1); box-shadow: 0 0 0 4px rgba(0,84,205,.18); }
        45% { transform: scale(1.35); box-shadow: 0 0 0 8px rgba(0,84,205,.2); }
        60% { transform: scale(1.05); box-shadow: 0 0 0 2px rgba(0,84,205,.1); }
    }
    @keyframes tv-public-screen-flash {
        0% { opacity: 0; }
        15% { opacity: 1; }
        100% { opacity: 0; }
    }
    @keyframes tv-public-toast-enter {
        0% { transform: translateY(-16px) scale(.96); opacity: 0; }
        100% { transform: translateY(0) scale(1); opacity: 1; }
    }
    @keyframes tv-public-toast-bar {
        0%, 100% { opacity: 1; }
        50% { opacity: .35; }
    }
    @keyframes tv-public-icon-ring {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255,255,255,.6); }
        50% { transform: scale(1.08); box-shadow: 0 0 0 8px rgba(255,255,255,0); }
    }
    @keyframes tv-public-summary-flash {
        0%, 100% { background-color: transparent; }
        20%, 60% { background-color: rgba(219, 234, 254, .85); }
        40%, 80% { background-color: rgba(191, 219, 254, .55); }
    }
    @keyframes tv-public-message-new {
        0% { background-color: rgba(219, 234, 254, .95); box-shadow: inset 4px 0 0 #0054cd; }
        40% { background-color: rgba(239, 246, 255, .95); box-shadow: inset 4px 0 0 #0054cd; }
        100% { background-color: transparent; box-shadow: inset 4px 0 0 transparent; }
    }
    .tv-public-alert {
        animation: tv-public-alert-ring 1s ease-in-out 4;
    }
    .tv-public-badge-pulse {
        animation: tv-public-badge-pulse 1s ease-in-out 4;
        background-color: #0054cd !important;
        color: #fff !important;
    }
    .tv-public-screen-flash {
        background: radial-gradient(ellipse at top, rgba(0,84,205,.22), rgba(0,84,205,0) 60%), linear-gradient(to bottom, rgba(0,84,205,.10), rgba(0,84,205,0));
        animation: tv-public-screen-flash 1.4s ease-out forwards;
    }
    .tv-public-toast-enter {
        animation: tv-public-toast-enter .28s ease-out;
    }
    .tv-public-toast-bar {
        animation: tv-public-toast-bar .8s ease-in-out infinite;
    }
    .tv-public-icon-ring {
        animation: tv-public-icon-ring 1s ease-in-out infinite;
    }
    .tv-public-summary-flash {
        animation: tv-public-summary-flash 1s ease-in-out 3;
        border-radius: 0.5rem;
    }
    .tv-public-message-new {
        animation: tv-public-message-new 2.2s ease-out 2;
        border-radius: 0.5rem;
    }
</style>

    <script>
        // Assistant IA : ouverture contextuelle depuis la saisie des notes / script présentateur.
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('tv-assistant-script-btn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var root = document.querySelector('[data-assistant]');
                var dialog = root ? root.querySelector('dialog') : null;
                var input = root ? root.querySelector('textarea') : null;
                var form = root ? root.querySelector('form') : null;
                if (!dialog || !input || !form) return;
                input.value = 'Rédige le script et les notes de présentation pour une séquence TV+AOS. Propose le déroulé, les transitions, les passages clés et le ton adapté au contexte éditorial. Si tu connais l’angle de la séquence en cours, l’intègre ; sinon demande-le avant de rédiger.';
                dialog.showModal();
                input.focus();
            });
        });
    </script>
@endpush
