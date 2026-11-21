@php
    $canPilotAntenne = ! $conducteur->date_locked_at && (auth()->user()?->can('piloterAntenne', $conducteur) ?? false);
    $canEditNotes = ! $conducteur->date_locked_at && $sequences->isNotEmpty()
        && (auth()->user()?->can('updateNotesPresentateur', $sequences->first()) ?? false);
@endphp
<section class="h-full rounded-xl border border-outline/15 bg-white p-5 shadow-sm"
         x-data="chefAntennePanel()"
         x-init='initialize({
             csrf: @json(csrf_token()),
             routes: {
                 liveState: @json(route('conducteurs.presentateur.live-state', $conducteur)),
                 saveNotes: @json(route('conducteurs.sequences.notes_presentateur.update', ['sequence' => '__SEQ__'])),
                 start: @json(route('conducteurs.presentateur.antenne.start', $conducteur)),
                 pause: @json(route('conducteurs.presentateur.antenne.pause', $conducteur)),
                 reset: @json(route('conducteurs.presentateur.antenne.reset', $conducteur)),
             }
         })'
         aria-label="Panneau antenne et notes présentateur">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-on-surface">
                <i class="fa-solid fa-satellite-dish text-primary" aria-hidden="true"></i>
                Antenne & notes présentateur
            </h3>
            <p class="mt-0.5 text-xs text-on-surface-variant">Chronomètre, séquences et notes sur une seule interface.</p>
            <p class="mt-1 text-xs" role="status" aria-live="polite"
               :class="syncState === 'connected' ? 'text-green-700' : (syncState === 'offline' ? 'text-red-700' : 'text-on-surface-variant')">
                <i class="fa-solid fa-circle text-[8px] mr-1" aria-hidden="true"></i>
                <span x-text="syncState === 'connected' ? 'Synchronisé · ' + lastSyncAt : (syncState === 'offline' ? 'Connexion instable · réessai automatique' : 'Connexion en cours…')"></span>
            </p>
        </div>
        <button type="button" @click="refresh()" :disabled="busy"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-outline/30 bg-white px-3 py-2 text-xs font-semibold text-on-surface transition-colors hover:bg-surface-container disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
            <i class="fa-solid fa-rotate" :class="busy ? 'fa-spin' : ''" aria-hidden="true"></i>
            Actualiser
        </button>
    </div>

    <div class="flex flex-col gap-5 lg:flex-row">
        {{-- Chrono --}}
        <div class="rounded-lg border border-outline/15 bg-surface-container-low/50 p-4 lg:w-[280px] lg:shrink-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Chrono de l'émission</p>
            <div class="mt-2 flex items-baseline gap-1.5 font-mono tabular-nums" aria-live="off">
                <span class="text-2xl font-black text-on-surface" x-text="formatSec(elapsedSec)">00:00</span>
                <span class="text-xs text-on-surface-variant">/</span>
                <span class="text-xs text-on-surface-variant" x-text="formatSec(totalSec)">00:00</span>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-surface-container-high">
                <div class="h-full rounded-full bg-primary transition-all duration-500" :style="'width:' + progress() + '%'"></div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                <span x-show="enMarche()" class="inline-flex items-center gap-1.5 font-bold uppercase tracking-wider text-primary">
                    <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-primary"></span> En cours
                </span>
                <span x-show="!enMarche() && !terminee() && elapsedSec > 0" class="font-bold uppercase tracking-wider text-amber-700">En pause</span>
                <span x-show="terminee()" class="font-bold uppercase tracking-wider text-green-700">Terminé</span>
                <span x-show="!enMarche() && elapsedSec === 0 && !terminee()" class="italic text-on-surface-variant">Prêt</span>
            </div>
            @if($canPilotAntenne)
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" @click="control('start')" :disabled="busy || enMarche()"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white transition-colors hover:bg-primary-container hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                    <i class="fa-solid fa-play text-xs" aria-hidden="true"></i> Démarrer
                </button>
                <button type="button" @click="control('pause')" :disabled="busy || !enMarche()"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-outline/30 px-3 py-2 text-xs font-semibold text-on-surface transition-colors hover:bg-surface-container disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                    <i class="fa-solid fa-pause text-xs" aria-hidden="true"></i> Pause
                </button>
                <button type="button" @click="control('reset')" :disabled="busy"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-outline/30 px-3 py-2 text-xs font-semibold text-on-surface transition-colors hover:bg-surface-container disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                    <i class="fa-solid fa-rotate-left text-xs" aria-hidden="true"></i> Reset
                </button>
            </div>
            @else
                <p class="mt-3 text-xs text-on-surface-variant">Pilotage réservé aux utilisateurs autorisés. Le suivi reste consultable.</p>
            @endif
            <p x-show="controlError" x-cloak class="mt-2 text-xs text-red-700" role="alert" x-text="controlError"></p>
            <template x-if="courante()">
                <p class="mt-3 rounded-md bg-white px-2.5 py-2 text-xs text-on-surface-variant">
                    À l'antenne : séquence <span class="font-bold text-primary" x-text="'#' + courante().position"></span>
                    · <span class="font-semibold text-on-surface" x-text="courante().title"></span>
                </p>
            </template>
        </div>

        {{-- Notes --}}
        <div class="min-w-0 flex-1">
            <label for="chef-notes-seq" class="mb-1 block whitespace-nowrap text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Séquence</label>
            <select id="chef-notes-seq"
                    @change="selectSequence(sequences.find((s) => Number(s.id) === Number($event.target.value)))"
                    class="w-full rounded-lg border border-outline/30 bg-white px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                <template x-for="s in sequences" :key="s.id">
                    <option :value="s.id" :selected="Number(s.id) === Number(activeId)" x-text="'#' + s.position + ' · ' + s.title"></option>
                </template>
            </select>

            <label for="chef-notes-presentateur" class="mb-1 mt-3 block whitespace-nowrap text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Notes présentateur</label>
            <textarea id="chef-notes-presentateur" rows="6"
                      x-model="notes" @input="notesDirty = true; scheduleSave()" @blur="saveNotes()"
                      @readonly(!$canEditNotes)
                      placeholder="Notes de régie, consignes de lecture, points d'attention…"
                      class="w-full rounded-lg border border-outline/30 bg-white px-3 py-2.5 text-sm leading-relaxed text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 text-xs" role="status" aria-live="polite">
                    <span class="text-on-surface-variant" x-show="notesDirty" x-cloak>Modifications non enregistrées…</span>
                    <span class="font-semibold text-green-700" x-show="saved" x-cloak>Notes enregistrées.</span>
                    <span class="font-semibold text-error" x-show="saveError" x-cloak x-text="saveError"></span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="ouvrirPrompter()"
                            class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-outline/30 px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container">
                        <i class="fa-solid fa-display text-xs" aria-hidden="true"></i> Afficher le prompteur
                    </button>
                    @if($canEditNotes)
                    <button type="button" @click="saveNotes()" :disabled="saving || !courante()"
                            class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-primary-container hover:text-primary disabled:cursor-not-allowed disabled:opacity-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                        <i class="fa-solid fa-floppy-disk text-xs" :class="saving ? 'fa-spin' : ''" aria-hidden="true"></i>
                        <span x-text="saving ? 'Enregistrement…' : 'Enregistrer les notes'">Enregistrer les notes</span>
                    </button>
                    @else
                        <span class="text-xs text-on-surface-variant">Notes consultables en lecture seule</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Petit écran prompteur : s'ouvre avec le chrono, vitesse réglable --}}
    <div x-show="prompterOpen" x-cloak
         x-ref="prompterPanel"
         :style="stylePrompter()"
         @pointermove.window="onDrag($event)"
         @pointerup.window="endDrag()"
         @pointercancel.window="endDrag()"
         class="fixed bottom-24 right-5 z-[60] w-[min(460px,calc(100vw-2rem))] overflow-hidden rounded-xl border border-white/10 bg-[#0b1220] text-slate-100 shadow-2xl"
         role="dialog" aria-modal="false" aria-label="Prompteur du présentateur"
         tabindex="-1" @keydown.escape.stop="fermerPrompter()">
        <div @pointerdown="startDrag($event)"
             class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 bg-[#111a2e] px-4 py-2.5 cursor-move select-none touch-none"
             title="Glissez pour déplacer le prompteur">
            <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider">
                <i class="fa-solid fa-display text-sky-300" aria-hidden="true"></i>
                Prompteur
            </span>
            <div class="flex flex-wrap items-center gap-3">
                <label for="prompter-sequence" class="sr-only">Séquence du prompteur</label>
                <select id="prompter-sequence"
                        @change="selectSequence(sequences.find((s) => Number(s.id) === Number($event.target.value)))"
                        class="min-h-11 max-w-[210px] rounded-md border border-white/15 bg-[#16223c] px-2 py-1 text-sm text-slate-100 focus:outline-none focus:ring-1 focus:ring-sky-400">
                    <template x-for="s in sequences" :key="`prompter-${s.id}`">
                        <option :value="s.id" :selected="Number(s.id) === Number(activeId)" x-text="sequenceLabel(s)"></option>
                    </template>
                </select>
                <label for="prompter-speed" class="text-[11px] text-slate-300">Vitesse</label>
                <input id="prompter-speed" type="range" min="5" max="90" step="5"
                       x-model.number="prompterSpeed"
                       class="h-1.5 w-24 cursor-pointer accent-sky-400">
                <span class="w-12 font-mono text-[11px] text-slate-300" x-text="prompterSpeed + ' px/s'">30 px/s</span>
                <button type="button" @click="prompterPaused = !prompterPaused"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-md border border-white/15 px-3 text-xs font-semibold text-slate-100 hover:bg-white/10">
                    <i :class="prompterPaused ? 'fa-solid fa-play' : 'fa-solid fa-pause'" aria-hidden="true"></i>
                    <span x-text="prompterPaused ? 'Reprendre' : 'Pause'">Pause</span>
                </button>
                <button type="button" @click="fermerPrompter()" aria-label="Fermer le prompteur"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-white/15 text-slate-200 hover:bg-white/10">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="border-b border-white/10 px-5 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-sky-300"
             x-text="sequenceLabel(courante())"></div>
        <div x-ref="prompterTexte"
             class="h-56 overflow-y-auto px-5 py-4 text-lg leading-relaxed whitespace-pre-line"
             x-text="notesCourantes() || 'Aucune note pour cette séquence.'"></div>
        <p class="border-t border-white/10 px-4 py-2 text-[11px] text-slate-400">
            Le texte défile automatiquement quand le chrono est lancé. Ajustez la vitesse avec le curseur.
        </p>
    </div>
</section>
