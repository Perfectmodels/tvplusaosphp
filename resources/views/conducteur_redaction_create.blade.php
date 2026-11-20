@extends('layouts.app')
@section('content')
    <div class="max-w-5xl mx-auto px-4 md:px-6 py-10">
        <div class="mb-8">
            <a href="{{ route('conducteurs.redaction.index') }}"
               class="inline-flex items-center gap-2 text-sm text-on-surface-variant hover:text-primary transition-colors mb-4">
                <i class="fa-solid fa-arrow-left"></i> Retour aux conducteurs de rédaction
            </a>
            <div class="flex items-center gap-3 text-xs text-on-surface-variant uppercase tracking-wider mb-2">
                <i class="fa-solid fa-plus-circle text-primary"></i>
                <span>Nouveau conducteur de rédaction</span>
            </div>
            <h1 class="text-3xl font-bold text-on-surface">
                Créer un conducteur de rédaction
            </h1>
            <p class="text-sm text-on-surface-variant mt-2 max-w-2xl">
                Nommez votre conducteur et indiquez sa date d'édition. Vous pourrez ensuite ajouter les sujets en
                leur assignant une émission, un angle, un journaliste responsable et suivre le workflow
                Terrain → Montage → Habillage → Livraison → Diffusion.
            </p>
        </div>

        <form method="POST" action="{{ route('conducteurs.redaction.store') }}"
              class="bg-white rounded-2xl border border-outline/10 shadow-sm p-4 sm:p-8 space-y-8">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-1">
                    <label class="block text-sm font-semibold text-on-surface mb-2">Nom du conducteur <span class="text-error">*</span></label>
                    <input type="text" name="nom" required maxlength="255" minlength="3"
                           placeholder="Ex: JT 12h45 · Édition Spéciale / Réunion CSE / Événement..."
                           value="{{ old('nom') }}"
                           class="w-full px-4 py-3 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                    @error('nom')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-on-surface mb-2">Date d'édition <span class="text-error">*</span></label>
                    <input type="date" name="date_edition" required
                           value="{{ old('date_edition', $initialDateEdition) }}"
                           class="w-full px-4 py-3 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                    @error('date_edition')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="pt-6 border-t border-outline/10">
                <div x-data="presencePicker({{ (int) $totalJournalistes }})" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface mb-2">
                            <i class="fa-solid fa-users text-primary mr-1.5"></i>
                            Présents à la réunion de rédaction
                        </label>
                        <p class="text-sm text-on-surface-variant mb-3">
                            Sélectionnez les personnes <strong>internes</strong> (journalistes) présentes, puis indiquez
                            leur date &amp; heure d'arrivée. Vous pourrez ajouter un retardataire depuis la fiche du conducteur.
                        </p>

                        {{-- Part de présents sur l'ensemble des journalistes --}}
                        <div class="mb-4 p-3 rounded-lg border border-outline/15 bg-surface-container-lowest">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 text-sm">
                                    <i class="fa-solid fa-chart-pie text-primary"></i>
                                    <span class="text-on-surface-variant">
                                        <span class="font-mono font-bold text-primary" x-text="selection.length">0</span>
                                        <span class="opacity-60">/</span>
                                        <span class="font-mono font-semibold text-on-surface" x-text="totalJournalistes">0</span>
                                        journalistes présents
                                    </span>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full font-bold"
                                      :class="progressionPresences >= 60 ? 'bg-green-100 text-green-800' : (progressionPresences > 0 ? 'bg-primary/10 text-primary' : 'bg-surface-container text-on-surface-variant')"
                                      x-text="progressionPresences + ' %'">0 %</span>
                            </div>
                            <div class="mt-2 h-2 w-full bg-surface-container-high rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                     :class="progressionPresences >= 60 ? 'bg-green-500' : 'bg-primary'"
                                     :style="'width:' + progressionPresences + '%'"></div>
                            </div>
                        </div>

                        {{-- Recherche --}}
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-on-surface-variant"></i>
                            <input type="text"
                                   x-model="recherche"
                                   @focus="ouvert = true"
                                   @click.outside="ouvert = false"
                                   placeholder="Rechercher une personne (nom, fonction, pôle)…"
                                   class="w-full pl-9 pr-4 py-3 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                        </div>

                        {{-- Résultats de recherche --}}
                        <div x-show="ouvert && recherche.trim().length > 0"
                             x-transition
                             class="mt-2 max-h-64 overflow-y-auto border border-outline/20 rounded-lg bg-white shadow-sm">
                            <template x-for="j in resultats" :key="j.id">
                                <button type="button"
                                        @click="ajouter(j)"
                                        :disabled="dejaChoisi(j.id)"
                                        class="w-full text-left px-4 py-2.5 hover:bg-primary/5 transition-colors flex items-center justify-between gap-3 border-b border-outline/10 last:border-0 disabled:opacity-40 disabled:cursor-not-allowed">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-on-surface truncate" x-text="j.nom_complet"></div>
                                        <div class="text-xs text-on-surface-variant truncate">
                                            <span x-text="j.fonction"></span> · <span x-text="j.pole"></span>
                                        </div>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full shrink-0"
                                          :class="dejaChoisi(j.id) ? 'bg-surface-container text-on-surface-variant' : 'bg-primary/10 text-primary'">
                                        <span x-text="dejaChoisi(j.id) ? 'déjà ajouté' : '+ ajouter'"></span>
                                    </span>
                                </button>
                            </template>
                            <div x-show="resultats.length === 0" class="px-4 py-6 text-center text-sm text-on-surface-variant italic">
                                Aucune personne ne correspond.
                            </div>
                        </div>
                    </div>

                    {{-- Personnes sélectionnées --}}
                    <div x-show="selection.length > 0" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs uppercase tracking-wider text-on-surface-variant font-semibold">
                                Personnes présentes — <span x-text="selection.length + ' / ' + totalJournalistes"></span>
                            </span>
                            <button type="button" @click="toutVider()"
                                    class="text-xs text-error hover:underline inline-flex items-center gap-1 px-2 py-2 min-h-[44px]">
                                <i class="fa-solid fa-xmark"></i> Tout retirer
                            </button>
                        </div>

                        <template x-for="(ligne, idx) in selection" :key="ligne.journaliste_id">
                            <div class="flex flex-wrap items-center gap-3 p-3 rounded-lg border border-outline/15 bg-surface-container-lowest">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="w-7 h-7 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold shrink-0"
                                          x-text="idx + 1"></span>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-on-surface truncate" x-text="ligne.nom_complet"></div>
                                        <div class="text-xs text-on-surface-variant truncate">
                                            <span x-text="ligne.fonction"></span> · <span x-text="ligne.pole"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-on-surface-variant whitespace-nowrap">
                                        <i class="fa-regular fa-clock mr-1"></i>Arrivée
                                    </label>
                                    <input type="datetime-local"
                                           x-model="ligne.arrivee_at"
                                           :name="'presences[' + idx + '][arrivee_at]'"
                                           class="px-3 py-1.5 rounded-md border border-outline/20 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                                    <input type="hidden" :name="'presences[' + idx + '][journaliste_id]'" :value="ligne.journaliste_id">
                                    <button type="button" @click="retirer(ligne.journaliste_id)"
                                            class="w-11 h-11 rounded-md text-error hover:bg-error/10 transition-colors inline-flex items-center justify-center"
                                            title="Retirer cette personne">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div x-show="selection.length === 0"
                         class="rounded-lg border border-dashed border-outline/25 p-5 text-center">
                        <i class="fa-solid fa-user-group text-on-surface-variant/50 text-xl mb-2"></i>
                        <p class="text-sm text-on-surface-variant">
                            Aucune personne sélectionnée pour le moment.<br>
                            <span class="text-xs opacity-80">Utilisez la recherche ci-dessus pour ajouter les présents.</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-outline/10 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('conducteurs.redaction.index') }}"
                   class="px-5 py-2.5 text-sm text-on-surface-variant hover:bg-surface-container rounded-lg transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-container transition-colors shadow-sm">
                    <i class="fa-solid fa-check"></i> Créer le conducteur de rédaction
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
function presencePicker(totalJournalistes) {
    return {
        recherche: '',
        ouvert: false,
        journalistes: @json($journalistes),
        totalJournalistes: Math.max(0, +totalJournalistes || 0),
        selection: [],

        /** Part de présents sur l'ensemble des journalistes actifs (0-100). */
        get progressionPresences() {
            const total = +this.totalJournalistes || 0;
            if (total <= 0) return 0;
            return Math.min(100, Math.round(((this.selection || []).length * 100) / total));
        },

        get resultats() {
            const q = this.recherche.trim().toLowerCase();
            if (!q) return [];
            return this.journalistes.filter(j =>
                `${j.nom_complet || ''} ${j.fonction || ''} ${j.pole || ''}`.toLowerCase().includes(q)
            );
        },

        dejaChoisi(id) {
            return this.selection.some(l => +l.journaliste_id === +id);
        },

        ajouter(j) {
            if (this.dejaChoisi(j.id)) return;
            this.selection.push({
                journaliste_id: j.id,
                nom_complet:   j.nom_complet,
                fonction:      j.fonction,
                pole:          j.pole,
                arrivee_at:    this.maintenant(),
            });
            this.recherche = '';
            this.ouvert = false;
        },

        retirer(id) {
            this.selection = this.selection.filter(l => +l.journaliste_id !== +id);
        },

        toutVider() {
            this.selection = [];
        },

        maintenant() {
            const d = new Date();
            const p = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
        },
    };
}
</script>
@endpush
