import Alpine from 'alpinejs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Link from '@tiptap/extension-link';

function debounce(fn, wait) {
    let t = null;
    return function debounced(...args) {
        if (t) clearTimeout(t);
        t = setTimeout(() => { t = null; fn.apply(this, args); }, wait);
    };
}

function formatTimeHHMMSS(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

export default function conducteurPresentateur() {
    return {
        // ============== STATE ==============
        conducteurId: 0,
        csrfToken: '',
        routes: {
            saveNotes: '', storeSeq: '', updateSeq: '', destroySeq: '', reorderSeq: '',
            indexCp: '', redaction: '',
            liveState: '',
            antenneStart: '', antennePause: '', antenneReset: '', antenneSequence: '',
        },

        sequences: [],
        activeSequenceId: 0,
        dureeTotaleHM: '00:00',
        filter: '',

        // CRUD CP sequences
        canCrudCp: false,
        canDeleteCp: false,
        canReorderCp: false,
        reorderEnCours: false,
        defaultNextPosition: 1,
        defaultHeureDebut: '12:30',
        defaultHeureFin: '12:32',
        typeOptions: [],
        showModal: false,
        modalMode: 'create', // 'create' | 'edit'
        modalTitle: '',
        modalSubtitle: '',
        modalSubtitleCreate: '',
        modalSubtitleEdit: '',
        modalOrdreHint: '',
        modalOrdreHintCreate: '',
        modalOrdreHintEdit: '',
        modalSubmitLabel: '',
        editingId: null,
        seqForm: {
            position: 1,
            heure_debut: '12:30',
            heure_fin: '12:32',
            type: 'Sujet',
            title: '',
            summary: '',
            intervenant_ids: [],
        },
        dureeSeqFormHM: '--:--',
        modalErrors: [],
        isSavingSeq: false,
        deletionBusy: false,
        antenneBusy: false,

        // Intervenants (modal CP)
        intervenantsCp: [],
        inviteSearchCp: '',
        inviteDropdownOpenCp: false,
        inviteSelectionCp: null,

        isFullscreen: false,
        panelVisible: true,
        prompteurMode: false,
        prompteurScrolling: false,
        prompteurSpeed: 3,        // 1..10
        prompteurFontSize: 24,   // px
        _prompteurTimer: null,

        current: null,
        editor: null,
        tiptapButtons: [],
        notesDirty: false,
        notesLastSavedAt: null,
        isSaving: false,

        // ---- Notes (onglets : présentateur / perso privé) ----
        notesTab: 'presentateur',        // 'presentateur' | 'perso'
        notesPersoDraft: '',
        notesPersoDirty: false,
        _editorSeqId: '',                // id de la séquence actuellement chargée dans l'éditeur

        // ---- CHRONO ANTENNE : UN SEUL COMPTE À REBOURS POUR TOUTE L'ÉMISSION ----
        canPiloterAntenne: false,
        antenneStartedAt: null,          // ISO du départ du segment en cours
        antenneOffsetSec: 0,             // temps cumulé avant le segment en cours (pauses)
        antenneEndedAt: null,            // ISO de fin d'émission (fige le chrono)
        chronoNow: Date.now(),           // horloge interne, rafraîchie toutes les secondes
        _chronoTimer: null,
        _antenneDernierIndex: -1,        // index déjà signalé au serveur (anti-doublon)
        _antenneFinEnvoyee: false,
        antenneAlerte: null,             // { id, position, title } : flash au dépassement
        _antenneAlerteTimer: null,
        _liveRefreshTimer: null,
        _livePollTimer: null,
        _realtimeChannel: null,

        autoSaveTimeout: null,
        autoSaveDebounced: null,

        // ============== INIT ==============
        initialize(payload) {
            this.conducteurId = payload.conducteurId;
            this.activeSequenceId = payload.activeSequenceId || 0;
            this.csrfToken = payload.csrfToken;
            this.routes = payload.routes || {
                saveNotes: '', storeSeq: '', updateSeq: '', destroySeq: '', reorderSeq: '',
                indexCp: '', redaction: '',
                liveState: '',
                antenneStart: '', antennePause: '', antenneReset: '', antenneSequence: '',
            };
            this.dureeTotaleHM = payload.dureeTotaleHM || '00:00';
            this.sequences = Array.isArray(payload.sequences) ? payload.sequences : [];
            this.canCrudCp     = !!payload.canCrudCp;
            this.canDeleteCp   = !!payload.canDeleteCp;
            this.canReorderCp  = !!payload.canReorderCp;
            this.canPiloterAntenne = !!payload.canPiloterAntenne;

            // ---- État du chrono émission ----
            const antenne = payload.antenne || {};
            this.antenneStartedAt = antenne.startedAt || null;
            this.antenneOffsetSec = +(antenne.offsetSec || 0);
            this.antenneEndedAt   = antenne.endedAt || null;
            this.defaultNextPosition = (payload.defaultNextPosition && Number.isFinite(+payload.defaultNextPosition) && +payload.defaultNextPosition > 0)
                ? +payload.defaultNextPosition : Math.max(1, this.sequences.length + 1);
            this.defaultHeureDebut = payload.defaultHeureDebut ? String(payload.defaultHeureDebut) : '12:30';
            this.defaultHeureFin   = payload.defaultHeureFin   ? String(payload.defaultHeureFin)   : '12:32';
            this.typeOptions       = Array.isArray(payload.typeOptions) && payload.typeOptions.length ? payload.typeOptions : [
                { value: 'Sujet',      label: 'Sujet',      badge_class: 'bg-slate-100 text-slate-700' },
                { value: 'Generique',  label: 'Générique',  badge_class: 'bg-indigo-100 text-indigo-700' },
                { value: 'Reportage',  label: 'Reportage',  badge_class: 'bg-red-100 text-red-700' },
                { value: 'Debat',      label: 'Débat',      badge_class: 'bg-orange-100 text-orange-700' },
                { value: 'Chronique',  label: 'Chronique',  badge_class: 'bg-amber-100 text-amber-800' },
                { value: 'Interview',  label: 'Interview',  badge_class: 'bg-sky-100 text-sky-700' },
                { value: 'Conclusion', label: 'Conclusion', badge_class: 'bg-emerald-100 text-emerald-700' },
                { value: 'Pub',        label: 'Publicité',  badge_class: 'bg-yellow-100 text-yellow-800' },
            ];
            this.intervenantsCp = Array.isArray(payload.intervenantsCp) ? payload.intervenantsCp : [];
            // Premier type pour préremplissage
            const firstType = this.typeOptions.find(t => t && t.value)?.value;
            if (firstType) this.seqForm.type = firstType;

            // Valeurs statiques affichage modale (pour éviter exprès Alpine `modalMode === ?` dans template x-if)
            this.modalTitle           = 'Ajouter une séquence';
            this.modalSubtitleCreate  = 'Cette séquence sera ajoutée au conducteur courant. Les détails de production (angle, invités, traitement) seront précisés depuis la vue Rédaction.';
            this.modalSubtitleEdit    = 'Modification rapide de la séquence dans le déroulement.';
            this.modalSubtitle        = this.modalSubtitleCreate;
            this.modalOrdreHintCreate = 'Par défaut : dernière position + 1.';
            this.modalOrdreHintEdit   = 'Modifier l\'ordre réorganise automatiquement les autres.';
            this.modalOrdreHint       = this.modalOrdreHintCreate;
            this.modalSubmitLabel     = 'Ajouter la séquence';

            // Si activeSequenceId mais current pas chargé, on preload
            if (this.activeSequenceId) {
                const found = this.sequences.find((s) => +s.id === +this.activeSequenceId);
                if (found) this.current = found;
            }

            // Boutons TipTap Mini (6 outils prototype + séparateur)
            this.tiptapButtons = [
                { action: 'bold',          icon: 'fa-solid fa-bold',       title: 'Gras (Ctrl+B)' },
                { action: 'italic',        icon: 'fa-solid fa-italic',     title: 'Italique (Ctrl+I)' },
                { action: 'separator',     icon: 'fa-solid fa-minus',      title: 'Insérer un séparateur horizontal' },
                { action: 'bulletList',    icon: 'fa-solid fa-list-ul',    title: 'Liste à puces' },
                { action: 'orderedList',   icon: 'fa-solid fa-list-ol',    title: 'Liste numérotée' },
                { action: 'link',          icon: 'fa-solid fa-link',       title: 'Insérer un lien' },
            ];

            this.autoSaveDebounced = debounce(() => this.saveNotes(true), 8000);
            this.$watch('current', () => {
                this.syncTipTapFromCurrent();
                if (!this.notesPersoDirty) this.syncNotesPersoFromCurrent();
            });
            this.setupTipTap();
            this._editorSeqId = this.current ? String(this.current.id) : '';
            this.startChronoTicker();
            // On cale l'index déjà signalé : au rechargement, aucune bascule parasite.
            this._antenneDernierIndex = this.sequences.length ? this.indexCourant() : -1;

            // Sync initial Tiptap content if already a sequence selected
            this.$nextTick(() => this.syncTipTapFromCurrent());

            // Réconciliation : si la page a été rechargée EN PLEINE ÉMISSION, on
            // réaligne l'état serveur (séquence active) sur le chrono recalculé.
            this.$nextTick(() => {
                if (this.antenneEnMarche() && this.canPiloterAntenne) {
                    const courant = this.sequenceCourante();
                    if (courant && +courant.id !== +this.activeSequenceId) {
                        this._antenneFetch(this.routes.antenneSequence, { sequence_id: courant.id });
                    }
                }
            });
            this.subscribeToLiveUpdates();

            // Garde beforeunload : flusher l'auto-save si des modifications sont en attente.
            if (typeof window !== 'undefined') {
                window.addEventListener('beforeunload', () => {
                    if (this.notesDirty || this.notesPersoDirty) {
                        try { this.saveNotes(true); } catch (_e) {}
                    }
                });
            }
        },

        destroy() {
            if (this._liveRefreshTimer) clearTimeout(this._liveRefreshTimer);
            if (this._realtimeChannel && window.Echo) {
                window.Echo.leave(`conducteur.${this.conducteurId}`);
            }
        },

        subscribeToLiveUpdates() {
            if (!this.conducteurId || !this.routes.liveState) return;

            if (window.Echo) {
                this._realtimeChannel = window.Echo
                    .private(`conducteur.${this.conducteurId}`)
                    .listen('.conducteur.changed', (event) => {
                        if (+event.conducteur_id !== +this.conducteurId) return;
                        if (this._liveRefreshTimer) clearTimeout(this._liveRefreshTimer);
                        this._liveRefreshTimer = setTimeout(() => this.refreshLiveState(), 300);
                    });
                this.refreshLiveState();
                return;
            }

            // Secours : polling toutes les 10 s si Reverb/Echo est indisponible.
            if (this._livePollTimer) clearInterval(this._livePollTimer);
            this._livePollTimer = setInterval(() => {
                if (document.visibilityState !== 'visible') return;
                this.refreshLiveState();
            }, 10000);
            this.refreshLiveState();
        },

        async refreshLiveState() {
            if (!this.routes.liveState) return false;
            try {
                const response = await fetch(this.routes.liveState, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error(`Actualisation impossible (${response.status}).`);

                const snapshot = await response.json();
                const previousCurrent = this.current;
                const currentId = previousCurrent ? +previousCurrent.id : 0;
                const previousById = new Map(this.sequences.map((sequence) => [+sequence.id, sequence]));

                this.sequences = (snapshot.sequences || []).map((incoming) => {
                    const previous = previousById.get(+incoming.id) || {};
                    const sequence = { ...previous, ...incoming };
                    if (this.notesDirty && +incoming.id === currentId) {
                        sequence.notes_presentateur = previous.notes_presentateur;
                    }
                    if (this.notesPersoDirty && +incoming.id === currentId) {
                        sequence.notes_perso = previous.notes_perso;
                    }
                    return sequence;
                });

                if (previousCurrent) {
                    const refreshedCurrent = this.sequences.find((sequence) => +sequence.id === currentId);
                    this.current = refreshedCurrent || (
                        this.notesDirty || this.notesPersoDirty ? previousCurrent : null
                    );
                    if (refreshedCurrent && !this.notesDirty
                        && refreshedCurrent.notes_presentateur !== previousCurrent.notes_presentateur) {
                        this.setupTipTap(refreshedCurrent.notes_presentateur || '');
                        this._editorSeqId = String(refreshedCurrent.id);
                    }
                }

                this.activeSequenceId = +(snapshot.activeSequenceId || 0);
                this.antenneStartedAt = snapshot.antenne?.startedAt || null;
                this.antenneOffsetSec = +(snapshot.antenne?.offsetSec || 0);
                this.antenneEndedAt = snapshot.antenne?.endedAt || null;
                this.dureeTotaleHM = snapshot.dureeTotaleHM || this.dureeTotaleHM;
                this.chronoNow = Date.now();
                this._antenneDernierIndex = this.antenneEnMarche() ? this.indexCourant() : -1;
                this._antenneFinEnvoyee = !!this.antenneEndedAt;

                return true;
            } catch (error) {
                console.error('[CP realtime]', error);
                return false;
            }
        },

        // ============== COMPUTED ==============
        get visibleSequences() {
            const q = (this.filter || '').trim();
            if (!q) return this.sequences;
            const ql = q.toLowerCase();
            return this.sequences.filter((s) => {
                const hay = `${s.position} ${s.title} ${s.intervenant_soustitre || ''} ${s.notes_preview || ''} ${s.angle || ''} ${s.journaliste_nom || ''}`.toLowerCase();
                return hay.includes(ql);
            });
        },

        get canEditCurrentNotes() {
            return !!(this.current && this.current.can_edit_notes);
        },

        get canEditCurrentNotesPerso() {
            return !!(this.current && this.current.can_edit_notes_perso);
        },

        // ============== CHRONO ANTENNE (émission entière) ==============
        /**
         * UN SEUL compte à rebours pour toute l'émission. Le présentateur le
         * démarre une fois ; il décompte ensuite les séquences les unes après
         * les autres, dans l'ordre, à partir de l'horloge interne `chronoNow`
         * (rafraîchie 1×/s).
         */

        /** Durée réellement décomptée pour une séquence (60 s si non renseignée). */
        dureeSeq(s) {
            if (!s) return 0;
            const duree = +(s.duree_antenne_sec || 0);
            if (duree > 0) return duree;
            const fallback = +(s.duration_seconds || 0);
            return fallback > 0 ? fallback : 60;
        },

        /** Durée planifiée totale de l'émission = somme des durées des séquences. */
        antenneTotalSec() {
            return (this.sequences || []).reduce((total, s) => total + this.dureeSeq(s), 0);
        },

        /** Le chrono de l'émission tourne-t-il en ce moment ? */
        antenneEnMarche() {
            return !!this.antenneStartedAt && !this.antenneEndedAt;
        },

        /** Le chrono a-t-il déjà été démarré (même s'il est en pause) ? */
        antenneDemarree() {
            return !!this.antenneStartedAt || +(this.antenneOffsetSec || 0) > 0 || !!this.antenneEndedAt;
        },

        antenneTerminee() {
            return !!this.antenneEndedAt;
        },

        /** Secondes écoulées depuis le départ de l'émission (pauses exclues). */
        antenneEcouleSec() {
            const offset = Math.max(0, +(this.antenneOffsetSec || 0));
            if (! this.antenneStartedAt) return offset;
            const start = Date.parse(this.antenneStartedAt);
            if (! Number.isFinite(start)) return offset;
            return offset + Math.max(0, Math.floor((this.chronoNow - start) / 1000));
        },

        /** Progression 0-100 du chrono de l'émission. */
        antenneProgression() {
            const total = this.antenneTotalSec();
            if (total <= 0) return 0;
            return Math.min(100, Math.round((this.antenneEcouleSec() / total) * 100));
        },

        /** Libellé du bouton principal : Démarrer / Reprendre / Redémarrer. */
        antenneBoutonLabel() {
            if (this.antenneTerminee()) return 'Redémarrer';
            if (this.antenneDemarree()) return 'Reprendre';
            return 'Démarrer';
        },

        /** Secondes cumulées AVANT la séquence d'index i (i = 0 → 0). */
        offsetSeqIndex(i) {
            let acc = 0;
            for (let k = 0; k < i && k < this.sequences.length; k++) {
                acc += this.dureeSeq(this.sequences[k]);
            }
            return acc;
        },

        /** Index (0-based) de la séquence actuellement à l'antenne. */
        indexCourant() {
            if (! this.sequences.length) return -1;
            const ecoule = this.antenneEcouleSec();
            let acc = 0;
            for (let i = 0; i < this.sequences.length; i++) {
                acc += this.dureeSeq(this.sequences[i]);
                if (ecoule < acc) return i;
            }
            return this.sequences.length - 1;
        },

        /** Séquence actuellement à l'antenne (objet) ou null. */
        sequenceCourante() {
            const i = this.indexCourant();
            return i >= 0 ? this.sequences[i] : null;
        },

        seqIndex(s) {
            if (! s) return -1;
            return this.sequences.findIndex((x) => +x.id === +s.id);
        },

        /** Temps écoulé À L'INTÉRIEUR de cette séquence (borné à sa durée). */
        seqEcouleSec(s) {
            const i = this.seqIndex(s);
            if (i < 0) return 0;
            const dans = this.antenneEcouleSec() - this.offsetSeqIndex(i);
            return Math.max(0, Math.min(this.dureeSeq(s), dans));
        },

        /** Temps restant sur cette séquence (0 si elle est terminée). */
        seqRestantSec(s) {
            const i = this.seqIndex(s);
            if (i < 0) return 0;
            const dans = this.antenneEcouleSec() - this.offsetSeqIndex(i);
            return Math.max(0, this.dureeSeq(s) - dans);
        },

        seqProgression(s) {
            const duree = this.dureeSeq(s);
            if (duree <= 0) return 0;
            return Math.min(100, Math.round((this.seqEcouleSec(s) / duree) * 100));
        },

        /** État de la séquence dans le déroulé : 'avenir' | 'encours' | 'termine'. */
        seqEtat(s) {
            if (! this.antenneDemarree()) return 'avenir';
            const i = this.seqIndex(s);
            const courant = this.indexCourant();
            if (i < 0 || courant < 0) return 'avenir';
            if (i < courant) return 'termine';
            if (i > courant) return 'avenir';
            return this.antenneTerminee() ? 'termine' : 'encours';
        },

        seqEtatLabel(s) {
            return { avenir: 'Prêt', encours: 'En cours', termine: 'Terminée' }[this.seqEtat(s)] || 'Prêt';
        },

        /** La séquence est-elle celle tout juste dépassée (flash rouge) ? */
        alerteActive(s) {
            return !!this.antenneAlerte && !!s && +this.antenneAlerte.id === +s.id;
        },

        formatSec(sec) {
            const total = Math.max(0, Math.floor(+sec || 0));
            const h = Math.floor(total / 3600);
            const m = Math.floor((total % 3600) / 60);
            const s = total % 60;
            const pad = (n) => String(n).padStart(2, '0');
            // Même formatage que ConducteurCalculatorService::formatDuree()
            return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
        },

        /** Démarre l'horloge interne et surveille la bascule automatique. */
        startChronoTicker() {
            if (this._chronoTimer) clearInterval(this._chronoTimer);
            this.chronoNow = Date.now();
            this._chronoTimer = setInterval(() => {
                this.chronoNow = Date.now();
                this.verifierAvanceAntenne();
            }, 1000);
        },

        /**
         * Bascule automatiquement sur la séquence suivante dès que la durée
         * allouée à la séquence en cours est écoulée, et fige le chrono à la
         * fin de l'émission. Une seule écriture serveur par bascule.
         */
        async verifierAvanceAntenne() {
            if (! this.antenneEnMarche()) return;
            const total = this.antenneTotalSec();
            if (total <= 0) return;
            const ecoule = this.antenneEcouleSec();

            // ---- Fin d'émission : on fige le chrono sur la durée totale ----
            if (ecoule >= total) {
                if (! this._antenneFinEnvoyee) {
                    this._antenneFinEnvoyee = true;
                    const derniere = this.sequences[this.sequences.length - 1];
                    if (derniere && this.canPiloterAntenne) {
                        const json = await this._antenneFetch(this.routes.antenneSequence, { sequence_id: derniere.id, fin: true });
                        if (! json) this._antenneFinEnvoyee = false;   // on réessaiera au tick suivant
                    } else {
                        this.antenneStartedAt = null;
                        this.antenneOffsetSec = total;
                        this.antenneEndedAt = new Date().toISOString();
                    }
                }
                return;
            }

            // ---- Bascule séquence par séquence ----
            const index = this.indexCourant();
            if (index < 0 || index === this._antenneDernierIndex) return;

            const precedent = this._antenneDernierIndex;
            const cible = this.sequences[index];
            this._antenneDernierIndex = index;

            if (precedent >= 0) {
                this.declencherAlerte(this.sequences[precedent]);
            }

            if (this.canPiloterAntenne) {
                await this._antenneFetch(this.routes.antenneSequence, { sequence_id: cible.id });
            }

            // Le panneau suit la séquence à l'antenne (sauf pendant une saisie en cours).
            // On passe par selectSequence() pour annuler l'autosave en attente et
            // recharger proprement l'éditeur de notes sur la nouvelle séquence.
            if (cible && ! this.notesDirty && ! this.notesPersoDirty && (! this.current || +this.current.id !== +cible.id)) {
                this.selectSequence(cible);
            }
        },

        /** Flash rouge + toast au moment où une séquence est dépassée. */
        declencherAlerte(seq) {
            if (! seq) return;
            this.antenneAlerte = { id: +seq.id, position: +seq.position, title: String(seq.title || '') };
            if (this._antenneAlerteTimer) clearTimeout(this._antenneAlerteTimer);
            this._antenneAlerteTimer = setTimeout(() => { this.antenneAlerte = null; }, 6000);
            this.toast(`⏰ Séquence #${seq.position} terminée — passage à la suivante.`, 'info');
        },

        degagerAlerte() {
            if (this._antenneAlerteTimer) clearTimeout(this._antenneAlerteTimer);
            this.antenneAlerte = null;
        },

        async demarrerAntenne() {
            if (this.antenneBusy) return;
            this.antenneBusy = true;
            try {
                const json = await this._antenneFetch(this.routes.antenneStart, {});
                if (json) {
                    this._antenneFinEnvoyee = false;
                    this._antenneDernierIndex = this.indexCourant();
                    this.toast('▶ Chrono de l\'émission démarré.', 'success');
                }
            } finally {
                this.antenneBusy = false;
            }
        },

        async pauseAntenne() {
            if (this.antenneBusy) return;
            this.antenneBusy = true;
            try {
                const json = await this._antenneFetch(this.routes.antennePause, {});
                if (json) this.toast('⏸ Chrono en pause.', 'info');
            } finally {
                this.antenneBusy = false;
            }
        },

        async reinitialiserAntenne() {
            if (this.antenneBusy) return;
            const confirmation = window.TvAlert?.confirm
                ? await window.TvAlert.confirm({
                    title: 'Réinitialiser le chrono de l\'émission ?',
                    text: 'Toutes les séquences reviendront à « Prêt ».',
                    icon: 'warning',
                    confirmText: 'Réinitialiser',
                })
                : { isConfirmed: window.confirm('Réinitialiser le chrono de l\'émission ?\n\nToutes les séquences reviendront à « Prêt ».') };
            if (! confirmation.isConfirmed) return;
            this.antenneBusy = true;
            try {
                const json = await this._antenneFetch(this.routes.antenneReset, {});
                if (json) {
                    this._antenneFinEnvoyee = false;
                    this._antenneDernierIndex = -1;
                    this.degagerAlerte();
                    this.toast('↺ Chrono réinitialisé.', 'success');
                }
            } finally {
                this.antenneBusy = false;
            }
        },

        async _antenneFetch(url, body = {}) {
            if (! url) return null;
            try {
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const json = await resp.json().catch(() => ({}));
                if (! resp.ok || ! json.ok) {
                    throw new Error(json.message || `Erreur ${resp.status}`);
                }
                this.appliquerEtatAntenne(json);
                return json;
            } catch (err) {
                console.error('Erreur chrono antenne :', err);
                this.toast(err?.message || 'Impossible de piloter le chrono de l\'émission.', 'error');
                return null;
            }
        },

        /** Applique l'état renvoyé par le serveur (chrono + statuts des séquences). */
        appliquerEtatAntenne(json) {
            if (! json) return;
            const antenne = json.antenne || {};
            this.antenneStartedAt = antenne.started_at ?? null;
            this.antenneOffsetSec = +(antenne.offset_sec || 0);
            this.antenneEndedAt   = antenne.ended_at ?? null;

            if (Array.isArray(json.statuts)) {
                const parId = new Map(json.statuts.map((s) => [String(s.id), s]));
                this.sequences = this.sequences.map((s) => {
                    const maj = parId.get(String(s.id));
                    return maj ? { ...s, ...maj } : s;
                });
                if (this.current) {
                    const maj = parId.get(String(this.current.id));
                    if (maj) this.current = { ...this.current, ...maj };
                }
            }

            if (json.active_id !== undefined && json.active_id !== null) {
                this.activeSequenceId = +json.active_id;
            }
        },

        activePosition() {
            const s = this.sequences.find((x) => +x.id === +this.activeSequenceId);
            return s ? s.position : 0;
        },

        // ============== CLASSES DYNAMIQUES ==============
        rowActiveClass(s) {
            if (!s) return '';
            const classes = [];
            if (typeof s.row_class === 'string' && s.row_class) classes.push(s.row_class);

            const i = this.seqIndex(s);
            const aLantenne = this.antenneDemarree() && ! this.antenneTerminee()
                && i >= 0 && i === this.indexCourant();

            if (aLantenne) {
                classes.push('bg-primary-fixed/25');
            } else if (+s.id === +this.activeSequenceId) {
                classes.push('bg-primary-fixed/20');
            }

            if (this.alerteActive(s)) classes.push('bg-error/10');

            return classes.join(' ');
        },

        panelRightClass() {
            const classes = [];
            if (! this.panelVisible) {
                classes.push('hidden');
            }
            if (this.isFullscreen) {
                classes.push('fixed', 'inset-0', '!w-screen', '!h-screen', 'z-[999]', '!shadow-2xl');
            } else {
                classes.push('w-full', 'lg:w-[500px]', 'relative');
            }
            return classes.join(' ');
        },

        tiptapActiveClass(action) {
            if (!this.editor || action === 'separator' || action === 'link') {
                return 'text-on-surface-variant border border-transparent';
            }
            try {
                const isActive = this.editor.isActive(action);
                return isActive
                    ? 'bg-primary text-white shadow-sm border border-primary'
                    : 'text-on-surface-variant border border-transparent';
            } catch (_e) {
                return 'text-on-surface-variant border border-transparent';
            }
        },

        // ============== HELPERS CRUD SÉQUENCES ==============
        _nextPosition() {
            const max = this.sequences.reduce((m, s) => Math.max(m, Number.isFinite(+s.position) ? +s.position : 0), 0);
            return Math.max(1, max + 1);
        },

        _timeToSec(hms) {
            const parts = String(hms ?? '').split(':').map(Number);
            if ((parts.length !== 2 && parts.length !== 3) || parts.some(n => !Number.isInteger(n))
                || parts[0] < 0 || parts[0] > 23 || parts[1] < 0 || parts[1] > 59
                || (parts.length === 3 && (parts[2] < 0 || parts[2] > 59))) return NaN;
            return parts[0] * 3600 + parts[1] * 60 + (parts[2] ?? 0);
        },

        _secToHM(sec) {
            sec = Math.max(0, sec | 0);
            const h = Math.floor(sec / 3600);
            const m = Math.floor((sec % 3600) / 60);
            const s = sec % 60;
            const pad = n => String(n).padStart(2, '0');
            if (h > 0) return `${pad(h)}:${pad(m)}:${pad(s)}`;
            return `${pad(m)}:${pad(s)}`;
        },

        calculDureeSeqForm() {
            const start = this.seqForm?.heure_debut;
            const end   = this.seqForm?.heure_fin;
            let sSec = this._timeToSec(start);
            let eSec = this._timeToSec(end);
            if (!Number.isFinite(sSec) || !Number.isFinite(eSec)) {
                this.dureeSeqFormHM = '--:--';
                return 0;
            }
            if (eSec < sSec) eSec += 86400; // passage à minuit
            const diff = Math.max(0, eSec - sSec);
            this.dureeSeqFormHM = this._secToHM(diff);
            return diff;
        },

        _resetSeqForm(mode = 'create') {
            this.modalMode = mode === 'edit' ? 'edit' : 'create';
            this.editingId = mode === 'edit' ? (this.editingId ?? null) : null;
            this.modalErrors = [];
            this.isSavingSeq = false;
            if (mode === 'create') {
                this.modalTitle       = 'Ajouter une séquence';
                this.modalSubtitle    = this.modalSubtitleCreate;
                this.modalOrdreHint   = this.modalOrdreHintCreate;
                this.modalSubmitLabel = 'Ajouter la séquence';
                this.seqForm = {
                    position:    this._nextPosition(),
                    heure_debut: this.defaultHeureDebut || '12:30',
                    heure_fin:   this.defaultHeureFin   || '12:32',
                    type:        this.typeOptions.find(t => t && t.value)?.value || 'Sujet',
                    title:       '',
                    summary:     '',
                    intervenant_ids: [],
                };
                this.calculDureeSeqForm();
            } else {
                this.modalTitle       = 'Modifier la séquence';
                this.modalSubtitle    = this.modalSubtitleEdit;
                this.modalOrdreHint   = this.modalOrdreHintEdit;
                this.modalSubmitLabel = 'Enregistrer';
            }
        },

        openCreateModal() {
            if (! this.canCrudCp) {
                this.toast('⚠️ Vous n\'êtes pas autorisé à ajouter de séquence ici.', 'error');
                return;
            }
            this._resetSeqForm('create');
            this._modalInitialForm = JSON.stringify(this.seqForm);
            this.showModal = true;
        },

        openEditModal(seq) {
            if (!seq) return;
            if (! (this.canCrudCp && seq.can_crud_cp)) {
                this.toast('⚠️ Modification non autorisée.', 'error');
                return;
            }
            this.editingId = +seq.id;
            this._resetSeqForm('edit');
            this.editingId = +seq.id;
            this.seqForm = {
                position:    Number.isFinite(+seq.position) ? +seq.position : 1,
                heure_debut: (seq.heure_debut_calculee && String(seq.heure_debut_calculee).length >= 5) ? String(seq.heure_debut_calculee).slice(0, 5) : (this.defaultHeureDebut || '12:30'),
                heure_fin:   (seq.heure_fin_calculee   && String(seq.heure_fin_calculee).length   >= 5) ? String(seq.heure_fin_calculee).slice(0,   5) : (this.defaultHeureFin   || '12:32'),
                type:        seq.type || this.typeOptions.find(t => t && t.value)?.value || 'Sujet',
                title:       String(seq.title || ''),
                summary:     String(seq.summary || ''),
                intervenant_ids: Array.isArray(seq.intervenant_ids) ? seq.intervenant_ids.slice() : [],
            };
            this.calculDureeSeqForm();
            this._modalInitialForm = JSON.stringify(this.seqForm);
            this.showModal = true;
        },

        closeModal(force = false) {
            if (this.isSavingSeq && !force) return;
            if (!force && this._modalInitialForm
                && JSON.stringify(this.seqForm) !== this._modalInitialForm
                && !window.confirm('Abandonner les modifications non enregistrées ?')) return;
            this.showModal = false;
            this._modalInitialForm = null;
            setTimeout(() => { this.editingId = null; this.modalErrors = []; }, 250);
        },

        _validateSeqForm() {
            const errs = [];
            const f = this.seqForm || {};
            if (! (Number.isFinite(+f.position) && +f.position >= 1)) errs.push('Ordre invalide.');
            const debSec = this._timeToSec(f.heure_debut);
            const finSec = this._timeToSec(f.heure_fin);
            if (!Number.isFinite(debSec)) errs.push('Heure de début invalide (HH:MM).');
            if (!Number.isFinite(finSec)) errs.push('Heure de fin invalide (HH:MM).');
            if (Number.isFinite(debSec) && Number.isFinite(finSec) && debSec === finSec) {
                errs.push('Les heures de début et de fin doivent être différentes.');
            }
            if (! f.type || String(f.type).trim() === '') errs.push('Type est requis.');
            if (! f.title || String(f.title).trim().length < 2) errs.push('Titre / sujet est requis (min 2 caractères).');
            if (String(f.title || '').length > 255) errs.push('Titre trop long (255 max).');
            if (String(f.summary || '').length > 5000) errs.push('Résumé trop long (5 000 max).');
            if (['DEBAT', 'DÉBAT'].includes(String(f.type || '').toUpperCase())) {
                const n = (f.intervenant_ids || []).length;
                if (n < 2 || n > 4) errs.push('Un débat exige entre 2 et 4 intervenants.');
            }
            this.modalErrors = errs;
            return errs.length === 0;
        },

        _upsertLocalSequence(nouvelle, mode) {
            if (!nouvelle || !nouvelle.id) return false;
            if (mode === 'create') {
                this.sequences.push(nouvelle);
            } else {
                this.sequences = this.sequences.map(s => +s.id === +nouvelle.id ? { ...s, ...nouvelle } : s);
            }
            this.sequences = this.sequences.slice().sort((a, b) => {
                const ap = Number.isFinite(+a.position) ? +a.position : 9999;
                const bp = Number.isFinite(+b.position) ? +b.position : 9999;
                return ap - bp;
            });
            // Si c'est current on met à jour
            if (this.current && +this.current.id === +nouvelle.id) {
                this.current = { ...this.current, ...nouvelle };
                this.syncTipTapFromCurrent();
            }
            return true;
        },

        _removeLocalSequence(deletedId, newActiveSeqId = null) {
            this.sequences = this.sequences.filter(s => +s.id !== +deletedId);
            // Réordonner positions localement si besoin pas nécessaire (serveur renvoie déjà trié next tick)
            if (this.current && +this.current.id === +deletedId) {
                this.current = null;
            }
            if (+this.activeSequenceId === +deletedId) {
                this.activeSequenceId = newActiveSeqId ? +newActiveSeqId : 0;
            }
        },

        async saveSequence() {
            if (this.isSavingSeq) return;
            if (! this._validateSeqForm()) {
                this.toast('⚠️ Formulaire invalide (voir messages).', 'error');
                return;
            }
            const mode = this.modalMode === 'edit' ? 'update' : 'store';
            const url = mode === 'update'
                ? (this.routes.updateSeq || '').replace('__SEQ__', String(this.editingId || 0))
                : (this.routes.storeSeq || '');
            if (!url) { this.toast('Route manquante.', 'error'); return; }
            this.isSavingSeq = true;

            const body = {
                position:     Number.isFinite(+this.seqForm.position) ? +this.seqForm.position : null,
                heure_debut:  String(this.seqForm.heure_debut || ''),
                heure_fin:    String(this.seqForm.heure_fin   || ''),
                type:         String(this.seqForm.type        || 'Sujet'),
                title:        String(this.seqForm.title       || '').trim(),
                summary:      String(this.seqForm.summary     || '').trim() || null,
                intervenant_ids: Array.isArray(this.seqForm.intervenant_ids) ? this.seqForm.intervenant_ids.slice() : [],
            };
            try {
                const resp = await fetch(url, {
                    method: mode === 'update' ? 'PATCH' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(body),
                });
                const json = await resp.json().catch(() => ({}));
                if (resp.status === 403) {
                    this.modalErrors = [json?.message || 'Non autorisé.'];
                    this.toast('⚠️ Non autorisé.', 'error');
                    return;
                }
                if (!resp.ok || !json || !json.ok) {
                    const msgs = json?.errors ? (Array.isArray(json.errors) ? json.errors : Object.values(json.errors).flat()) : (json?.message ? [json.message] : ['Erreur serveur.']);
                    this.modalErrors = msgs;
                    this.toast(msgs[0], 'error');
                    return;
                }
                const nouvelle = json.sequence;
                this._upsertLocalSequence(nouvelle, mode);
                this.toast(json?.message || (mode === 'store' ? '✅ Séquence créée.' : '✅ Séquence modifiée.'), 'success');
                this.closeModal(true);
                this.$nextTick(() => this.calculDureeSeqForm());
            } catch (e) {
                this.modalErrors = ['Erreur réseau.'];
                this.toast('Erreur réseau enregistrement séquence.', 'error');
            } finally {
                this.isSavingSeq = false;
            }
        },

        async deleteSequence(seq) {
            if (!seq) return;
            if (this.deletionBusy) return;
            if (! (this.canDeleteCp && seq.can_delete_cp)) {
                this.toast('⚠️ Suppression non autorisée.', 'error');
                return;
            }
            const label = `#${seq.position} — ${seq.title || ''}`.slice(0, 80);
            const confirmation = window.TvAlert?.confirmDelete
                ? await window.TvAlert.confirmDelete({
                    title: 'Supprimer cette séquence ?',
                    text: label,
                })
                : { isConfirmed: window.confirm(`Confirmer la suppression de la séquence ?\n\n${label}`) };
            if (! confirmation.isConfirmed) return;
            const url = (this.routes.destroySeq || '').replace('__SEQ__', String(seq.id));
            if (!url) { this.toast('Route manquante.', 'error'); return; }
            this.deletionBusy = true;
            try {
                const resp = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ sequence_id: seq.id }),
                });
                const json = await resp.json().catch(() => ({}));
                if (resp.status === 403 || !json || !json.ok) {
                    this.toast(json?.message || '⚠️ Suppression impossible.', 'error');
                    return;
                }
                this._removeLocalSequence(json.deletedId || seq.id, json.activeSequenceId || null);
                this.toast(json?.message || '✅ Séquence supprimée.', 'success');
            } catch (e) {
                this.toast('Erreur réseau.', 'error');
            } finally {
                this.deletionBusy = false;
            }
        },

        // ============== ACTIONS : Réordonnancement ==============
        deplacerCpHaut(index) {
            if (index <= 0 || this.reorderEnCours) return;
            const tmp = this.sequences[index];
            this.sequences.splice(index, 1);
            this.sequences.splice(index - 1, 0, tmp);
            this.sequences.forEach((s, i) => { s.position = i + 1; });
            this.envoyerReorderCp();
        },

        deplacerCpBas(index) {
            if (index >= this.sequences.length - 1 || this.reorderEnCours) return;
            const tmp = this.sequences[index];
            this.sequences.splice(index, 1);
            this.sequences.splice(index + 1, 0, tmp);
            this.sequences.forEach((s, i) => { s.position = i + 1; });
            this.envoyerReorderCp();
        },

        async envoyerReorderCp() {
            if (this.reorderEnCours || !this.routes.reorderSeq) return;
            this.reorderEnCours = true;
            try {
                const resp = await fetch(this.routes.reorderSeq, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ordered_ids: this.sequences.map(s => s.id) }),
                });
                if (!resp.ok) throw new Error('reorder failed');
                const json = await resp.json().catch(() => null);
                (json?.sequences || []).forEach(serv => {
                    const seq = this.sequences.find(s => Number(s.id) === Number(serv.id));
                    if (!seq) return;
                    seq.position = serv.position;
                    if (serv.heure_debut_calculee) seq.heure_debut_hm = serv.heure_debut_calculee;
                    if (serv.heure_fin_calculee) seq.heure_fin_hm = serv.heure_fin_calculee;
                    if (serv.duree_hm) seq.duree_hm = serv.duree_hm;
                });
                if (json?.duree_totale_hm) this.dureeTotaleHM = json.duree_totale_hm;
                this.toast('Ordre mis à jour.', 'success');
            } catch (e) {
                this.toast("Impossible d'enregistrer l'ordre.", 'error');
                this.refreshLiveState();
            } finally {
                this.reorderEnCours = false;
            }
        },

        // ============== ACTIONS : Sélection / Activation ==============
        selectSequence(seq) {
            if (!seq) return;

            this.panelVisible = true;

            // ⚠️ On change de séquence : on flush la sauvegarde en attente pour
            //    éviter de perdre la note de la séquence courante.
            if (this.notesDirty || this.notesPersoDirty) {
                this.saveNotes(true);
            }
            if (this.autoSaveTimeout) { clearTimeout(this.autoSaveTimeout); this.autoSaveTimeout = null; }

            this.activeSequenceId = +seq.id;
            this.current = seq;

            // Rechargement immédiat et non conditionnel des brouillons
            this.notesDirty = false;
            this.notesPersoDirty = false;
            this.syncTipTapFromCurrent();
            this.syncNotesPersoFromCurrent();
        },

        closePanel() {
            // Masque le panneau de notes ; la séquence sélectionnée reste active
            // (le bouton « Afficher le panneau » le remet en place, ou le re-clic sur une ligne).
            this.panelVisible = false;
            this.isFullscreen = false;
        },

        toggleFullscreen() {
            this.isFullscreen = !this.isFullscreen;
            this.$nextTick(() => {
                if (this.editor) {
                    try { this.editor.view.focus(); } catch (_e) {}
                }
            });
        },

        // ============== PROMPTEUR ==============
        nextSequence() {
            if (!this.current || !Array.isArray(this.sequences)) return null;
            const idx = this.sequences.findIndex(s => +s.id === +this.current.id);
            if (idx < 0 || idx >= this.sequences.length - 1) return null;
            return this.sequences[idx + 1];
        },

        togglePrompteur() {
            this.prompteurMode = !this.prompteurMode;
            this.prompteurScrolling = false;
            this.stopPrompteurScroll();
            if (this.prompteurMode) {
                this.isFullscreen = true;
                this.$nextTick(() => this.resetPrompteurScroll());
            } else {
                this.isFullscreen = false;
            }
        },
        resetPrompteurScroll() {
            const el = document.getElementById('prompteur-scroll');
            if (el) el.scrollTop = 0;
        },
        startPrompteurScroll() {
            this.prompteurScrolling = true;
            this.stopPrompteurScroll();
            this._prompteurTimer = setInterval(() => {
                const el = document.getElementById('prompteur-scroll');
                if (!el) return;
                el.scrollTop += this.prompteurSpeed;
                if (el.scrollTop + el.clientHeight >= el.scrollHeight) {
                    this.stopPrompteurScroll();
                }
            }, 50);
        },
        stopPrompteurScroll() {
            if (this._prompteurTimer) { clearInterval(this._prompteurTimer); this._prompteurTimer = null; }
            this.prompteurScrolling = false;
        },
        setPrompteurSpeed(v) {
            this.prompteurSpeed = Math.max(1, Math.min(10, +v || 1));
            if (this.prompteurScrolling) { this.startPrompteurScroll(); }
        },
        setPrompteurFontSize(v) {
            this.prompteurFontSize = Math.max(16, Math.min(48, +v || 24));
        },

        // ============== TIPTAP ==============
        /**
         * @param {string|null} content HTML à charger. `null` = prendre celui de la séquence courante.
         */
        setupTipTap(content = null) {
            const mount = document.getElementById('tiptap-cp-mount');
            if (!mount) return false;
            if (this.editor) {
                try { this.editor.destroy(); } catch (_e) {}
                this.editor = null;
            }

            const placeholderText = mount.getAttribute('data-placeholder') || '';
            const initialHTML = content !== null
                ? String(content || '')
                : (this.current && this.current.notes_presentateur ? String(this.current.notes_presentateur) : '');

            try {
                this.editor = new Editor({
                    element: mount,
                    extensions: [
                        StarterKit.configure({
                            heading: { levels: [2, 3] },
                            listItem: { HTMLAttributes: {} },
                        }),
                        Placeholder.configure({ placeholder: placeholderText }),
                        Link.configure({ openOnClick: false, autolink: true }),
                    ],
                    editorProps: {
                        attributes: {
                            class: 'outline-none min-h-[260px]',
                        },
                    },
                    content: initialHTML || '',
                    onUpdate: ({ editor }) => {
                        this.notesDirty = true;
                        if (this.autoSaveDebounced) this.autoSaveDebounced();
                    },
                });
                return true;
            } catch (e) {
                mount.setAttribute('contenteditable', 'true');
                mount.innerHTML = initialHTML || '';
                mount.addEventListener('input', () => {
                    this.notesDirty = true;
                    if (this.autoSaveDebounced) this.autoSaveDebounced();
                });
                return true;
            }
        },

        /** Recharge le brouillon « notes perso » depuis la séquence courante. */
        syncNotesPersoFromCurrent() {
            this.notesPersoDraft = this.current ? String(this.current.notes_perso || '') : '';
            this.notesPersoDirty = false;
        },

        syncTipTapFromCurrent() {
            const html = this.current ? (String(this.current.notes_presentateur || '')) : '';
            const seqId = this.current ? String(this.current.id) : '';
            const memeSequence = seqId !== '' && seqId === this._editorSeqId;

            // Même séquence : on ne touche à rien, sinon on écraserait la frappe en cours.
            if (memeSequence) return;

            // ⚠️ RECHARGEMENT DÉTERMINISTE : on RECONSTRUIT l'éditeur avec le HTML de la
            //    séquence visée. C'est la seule garantie que le contenu affiché appartient
            //    bien à la séquence sélectionnée : setContent() peut être normalisé ou
            //    ignoré par TipTap et laisser l'ancien texte visible (cf. bug des notes
            //    identiques d'une séquence à l'autre).
            this.setupTipTap(html || '');
            this._editorSeqId = seqId;
            this.notesDirty = false;
            this.notesPersoDraft = this.current ? String(this.current.notes_perso || '') : '';
            this.notesPersoDirty = false;
        },

        intervenantCorrespondCp(haystack, search) {
            const q = String(search || '').trim().toLowerCase();
            if (!q) return true;
            return String(haystack || '').toLowerCase().includes(q);
        },

        ajouterInviteCp() {
            const id = Number(this.inviteSelectionCp);
            if (!id) return;
            if (!Array.isArray(this.seqForm.intervenant_ids)) this.seqForm.intervenant_ids = [];
            if (this.seqForm.intervenant_ids.includes(id)) {
                this.toast('Cet invité est déjà dans la séquence.', 'info');
                return;
            }
            const isDebat = String(this.seqForm.type || '').toUpperCase() === 'DÉBAT';
            if (isDebat && this.seqForm.intervenant_ids.length >= 4) {
                this.toast('Un débat ne peut comporter que 4 intervenants maximum.', 'error');
                return;
            }
            this.seqForm.intervenant_ids.push(id);
            this.inviteSelectionCp = null;
            this.inviteSearchCp = '';
            this.inviteDropdownOpenCp = false;
        },

        retirerInviteCp(index) {
            if (!Array.isArray(this.seqForm.intervenant_ids)) return;
            this.seqForm.intervenant_ids.splice(index, 1);
        },

        labelIntervenantCp(id) {
            const found = (this.intervenantsCp || []).find(i => +i.id === +id);
            return found ? found.label : ('Invité #' + id);
        },

        tiptapToolbarAction(action) {
            if (!this.editor || ! this.canEditCurrentNotes) return;
            const chain = this.editor.chain().focus();

            switch (action) {
                case 'bold': chain.toggleBold().run(); return;
                case 'italic': chain.toggleItalic().run(); return;
                case 'bulletList': chain.toggleBulletList().run(); return;
                case 'orderedList': chain.toggleOrderedList().run(); return;
                case 'separator': {
                    chain.setHorizontalRule().run(); return;
                }
                case 'link': {
                    const previous = this.editor.getAttributes('link').href;
                    const url = window.prompt('URL du lien :', previous || 'https://');
                    if (url === null) return;
                    if (url === '') { chain.extendMarkRange('link').unsetLink().run(); return; }
                    try { chain.setLink({ href: url, target: '_blank', rel: 'noopener noreferrer' }).run(); } catch (_e) {}
                    return;
                }
                default: return;
            }
        },

        tiptapClearFormat() {
            if (!this.editor || ! this.canEditCurrentNotes) return;
            try {
                this.editor.chain().focus()
                    .unsetAllMarks()
                    .clearNodes()
                    .run();
            } catch (_e) {}
        },

        scheduleAutoSave() {
            // Déjà géré via debounced dans onUpdate, mais double sécurité si input direct event
            if (this.autoSaveDebounced) this.autoSaveDebounced();
        },

        autoSaveIfDirty() {
            if (this.notesDirty) this.saveNotes(true);
        },

        // ============== SAVE NOTES ==============
        async saveNotes(isAuto = false) {
            if (!this.current) return false;
            if (this.isSaving) return false;

            // Garde-fou : on n'écrit JAMAIS dans une autre séquence que celle affichée.
            const seqIdVise = String(this.current.id);
            if (this._editorSeqId && this._editorSeqId !== seqIdVise) {
                return false;   // la séquence a changé pendant la frappe -> on abandonne
            }

            // ---- Contenu « Notes présentateur » (WYSIWYG) ----
            const content = this.editor
                ? this.editor.getHTML()
                : (document.getElementById('tiptap-cp-mount')?.innerHTML || '');
            const toSendNotes = String(content || '').replace(/^(<p><\/p>\s*)+$/i, '').trim() || null;

            // ---- Contenu « Notes perso » (privé) ----
            const toSendPerso = String(this.notesPersoDraft || '').trim() || null;

            // Un champ n'est envoyé que si l'utilisateur a LE droit correspondant
            const peutNotes  = this.canEditCurrentNotes;
            const peutPerso  = this.canEditCurrentNotesPerso;

            const payload = {};
            if (peutNotes) payload.notes_presentateur = toSendNotes;
            if (peutPerso) payload.notes_perso        = toSendPerso;

            if (Object.keys(payload).length === 0) return false;

            // Rien de modifié ? (hors auto-save)
            const notesChanged  = peutNotes  && (this.notesDirty      || toSendNotes !== (this.current.notes_presentateur || null));
            const persoChanged  = peutPerso  && (this.notesPersoDirty || toSendPerso !== (this.current.notes_perso || null));

            if (!isAuto && !notesChanged && !persoChanged) return false;
            if (isAuto && !this.notesDirty && !this.notesPersoDirty) return false;

            this.isSaving = true;
            const url = (this.routes.saveNotes || '').replace('__SEQ__', String(this.current.id));
            if (!url) { this.isSaving = false; return false; }

            try {
                const resp = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payload),
                    keepalive: true,
                });
                const json = await resp.json().catch(() => ({}));
                if (json && json.ok) {
                    // La séquence a-t-elle changé pendant la requête ?
                    if (!this.current || String(this.current.id) !== seqIdVise) {
                        return true;   // on n'écrase pas l'affichage de la nouvelle séquence
                    }

                    this.notesDirty = false;
                    this.notesPersoDirty = false;
                    this.notesLastSavedAt = json.saved_at || formatTimeHHMMSS(new Date());
                    const seqId = +this.current.id;
                    const newPreview = json.notes_preview || '';
                    const newPerso   = json.notes_perso !== undefined ? json.notes_perso : (payload.notes_perso ?? this.current.notes_perso);
                    this.sequences = this.sequences.map((s) => {
                        if (+s.id === seqId) {
                            return {
                                ...s,
                                notes_presentateur: payload.notes_presentateur ?? s.notes_presentateur,
                                notes_perso:        newPerso,
                                notes_preview:      newPreview,
                            };
                        }
                        return s;
                    });
                    if (this.current && +this.current.id === seqId) {
                        this.current = {
                            ...this.current,
                            notes_presentateur: payload.notes_presentateur ?? this.current.notes_presentateur,
                            notes_perso:        newPerso,
                            notes_preview:      newPreview,
                        };
                    }
                    this.toast('Notes enregistrées ✅', 'success');
                    return true;
                }
                if (resp.status === 403) {
                    this.toast('⚠️ Vous n\'êtes pas autorisé à modifier ces notes.', 'error');
                } else if (json && json.message) {
                    this.toast(json.message, 'error');
                } else {
                    this.toast('Erreur enregistrement notes.', 'error');
                }
                return false;
            } catch (e) {
                this.toast('Erreur réseau : impossible d\'enregistrer.', 'error');
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        toast(message, type = 'info') {
            // Pas de dépendance externe → affichage in-page via alert fallback élégant
            try {
                const toastDiv = document.createElement('div');
                const isError = type === 'error';
                toastDiv.className = [
                    'fixed bottom-6 right-6 z-[9999] max-w-md px-4 py-3 rounded-lg shadow-xl text-sm font-medium transition-all duration-300 translate-y-4 opacity-0 flex items-center gap-2',
                    isError ? 'bg-danger text-white' : 'bg-green-600 text-white',
                ].join(' ');
                toastDiv.innerHTML = `
                    <i class="fa-solid ${isError ? 'fa-triangle-exclamation' : 'fa-circle-check'}"></i>
                    <span>${String(message).replace(/[<>]/g, '')}</span>
                `;
                document.body.appendChild(toastDiv);
                requestAnimationFrame(() => { toastDiv.classList.remove('translate-y-4', 'opacity-0'); });
                setTimeout(() => {
                    toastDiv.classList.add('translate-y-4', 'opacity-0');
                    setTimeout(() => toastDiv.remove(), 350);
                }, 4200);
            } catch (_e) {}
        },

        // Exposed pour compatibilité test
        _cleanup() {
            if (this.autoSaveTimeout) clearTimeout(this.autoSaveTimeout);
            if (this._chronoTimer) { clearInterval(this._chronoTimer); this._chronoTimer = null; }
            if (this._antenneAlerteTimer) { clearTimeout(this._antenneAlerteTimer); this._antenneAlerteTimer = null; }
            if (this.editor) { try { this.editor.destroy(); } catch (_e) { this.editor = null; } }
        },
    };
}

// Global registration
if (typeof window !== 'undefined') {
    window.conducteurPresentateur = conducteurPresentateur;
    const register = () => {
        if (window.Alpine) {
            window.Alpine.data('conducteurPresentateur', conducteurPresentateur);
        }
    };
    register();
    document.addEventListener('alpine:init', register);
}
