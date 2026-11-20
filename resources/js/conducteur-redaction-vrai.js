import Alpine from 'alpinejs';

export default function conducteurRedactionVrai() {
    return {
        // ============== STATE ==============
        conducteurId: 0,
        csrfToken: '',
        routes: {
            index: '',
            chefEdition: '',
            presentateur: '',
            workflowNextBase: '',
            storeSujet: '',
            updateSujetBase: '',
            destroySujetBase: '',
            reorder: '',
            liveState: '',
        },
        canCrudRedac: false,
        canReorderRedac: false,
        defaultNextPos: 1,
        typeOptions: [],
        jrOptions: [],
        presenceTotal: 0,
        presenceTotalJournalistes: 0,
        emissionOptions: [],
        emissionSearch: '',
        jrSearch: '',
        jrDropdownOpen: false,
        progression: 0,
        sujets: [],

        showModal: false,
        modalMode: 'create',
        modalTitle: '',
        modalSubtitle: '',
        modalSubtitleCreate: '',
        modalSubtitleEdit: '',
        modalOrdreHint: '',
        modalOrdreHintCreate: '',
        modalOrdreHintEdit: '',
        modalSubmitLabel: '',
        editingId: null,
        sujetForm: {
            position: 1,
            dureeMinutes: 2,
            dureeSecondes: 0,
            dureeTotaleHM: '02:00',
            title: '',
            angle_editorial: '',
            type_value: 'Sujet',
            journaliste_responsable_id: null,
        },
        modalErrs: {},
        isSavingSujet: false,
        showToast: false,
        toastText: '',
        toastKind: 'info',
        _toastT: null,
        _liveRefreshTimer: null,
        _realtimeChannel: null,

        // ============== INIT ==============
        init(cfg) {
            // Alpine appelle aussi `init()` automatiquement, SANS argument.
            // On ignore ce premier appel et on attend le `x-init` explicite.
            if (!cfg || typeof cfg !== 'object') return;

            this.conducteurId = cfg.conducteurId || 0;
            this.csrfToken = cfg.csrfToken || '';
            this.routes = Object.assign(this.routes, cfg.routes || {});
            this.canCrudRedac = !!cfg.canCrudRedac;
            this.defaultNextPos = (cfg.defaultNextPos && +cfg.defaultNextPos > 0) ? +cfg.defaultNextPos : 1;
            this.typeOptions = Array.isArray(cfg.typeOptions) && cfg.typeOptions.length ? cfg.typeOptions : [
                { value: 'Sujet', label: 'Sujet' },
                { value: 'Generique', label: 'Générique' },
                { value: 'Reportage', label: 'Reportage' },
                { value: 'Debat', label: 'Débat' },
                { value: 'Chronique', label: 'Chronique' },
                { value: 'Interview', label: 'Interview' },
                { value: 'Conclusion', label: 'Conclusion' },
                { value: 'Pub', label: 'Publicité' },
            ];
            this.jrOptions = Array.isArray(cfg.jrOptions) ? cfg.jrOptions : [];
            this.presenceTotal = Number.isFinite(+cfg.presenceTotal) ? +cfg.presenceTotal : 0;
            this.presenceTotalJournalistes = Number.isFinite(+cfg.presenceTotalJournalistes) ? +cfg.presenceTotalJournalistes : 0;
            this.emissionOptions = Array.isArray(cfg.emissionOptions) ? cfg.emissionOptions : [];
            this.progression = (cfg.progression && Number.isFinite(+cfg.progression)) ? +cfg.progression : 0;
            this.sujets = Array.isArray(cfg.sujets) ? cfg.sujets : [];

            const firstType = this.typeOptions.find(t => t && t.value)?.value;
            if (firstType) this.sujetForm.type_value = firstType;

            this.modalTitle = 'Ajouter un sujet de rédaction';
            this.modalSubtitleCreate = 'Ajoutez un sujet avec son angle éditorial, son type et son journaliste responsable. Le workflow Terrain→Diffusion est monopass strict (progression unique).';
            this.modalSubtitleEdit = 'Modifiez les informations éditoriales du sujet. Cliquez sur les badges du tableau pour avancer les étapes.';
            this.modalSubtitle = this.modalSubtitleCreate;
            this.modalOrdreHintCreate = 'Ordre 1 = tout début de l\'émission, sujet prioritaire. Par défaut : dernière position + 1.';
            this.modalOrdreHintEdit = 'Changer la position réorganise automatiquement les autres sujets.';
            this.modalOrdreHint = this.modalOrdreHintCreate;
            this.modalSubmitLabel = 'Ajouter le sujet';

            this._restaurerApresRechargement();
            this.subscribeToLiveUpdates();
        },

        // ============== HELPERS ==============
        destroy() {
            if (this._liveRefreshTimer) clearTimeout(this._liveRefreshTimer);
            if (this._realtimeChannel && window.Echo) {
                window.Echo.leave(`conducteur.${this.conducteurId}`);
            }
        },
        subscribeToLiveUpdates() {
            if (!window.Echo || !this.conducteurId || !this.routes.liveState) return;

            this._realtimeChannel = window.Echo
                .private(`conducteur.${this.conducteurId}`)
                .listen('.conducteur.changed', (event) => {
                    if (+event.conducteur_id !== +this.conducteurId) return;
                    if (this._liveRefreshTimer) clearTimeout(this._liveRefreshTimer);
                    this._liveRefreshTimer = setTimeout(() => this.refreshLiveState(), 300);
                });
            this.refreshLiveState();
        },
        filteredEmissionOptions() {
            const query = String(this.emissionSearch || '').trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr');
            return this.emissionOptions.filter((emission) => {
                const label = String(emission?.label || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr');
                return !query || label.includes(query) || Number(emission?.value) === Number(this.sujetForm.emission_id);
            });
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
                this.sujets = Array.isArray(snapshot.sujets) ? snapshot.sujets : [];
                this.progression = Number.isFinite(+snapshot.progression) ? +snapshot.progression : this.progression;
                return true;
            } catch (error) {
                console.error('[redaction realtime]', error);
                return false;
            }
        },
        get jrFiltered() {
            const opts = Array.isArray(this.jrOptions) ? this.jrOptions : [];
            const q = String(this.jrSearch || '').trim().toLowerCase();
            if (!q) return opts;
            return opts.filter(j => {
                const hay = [String(j?.label || ''), String(j?.fonction || '')].join(' ').toLowerCase();
                return hay.includes(q);
            });
        },
        jrInitiales(label) {
            let s = String(label || 'JV').trim();
            if (!s) return 'JV';
            s = s.replace(/\([^)]*\)/g, '').trim();
            const parts = s.split(/[\s\-]+/).filter(Boolean);
            const out = (parts[0]?.[0] || '') + (parts[1]?.[0] || (parts[0]?.[1] || ''));
            return (out || 'JV').toUpperCase().slice(0, 2);
        },
        jrPick(j) {
            if (!j) {
                this.sujetForm.journaliste_responsable_id = null;
                this.jrSearch = '';
            } else {
                this.sujetForm.journaliste_responsable_id = +j.value;
                this.jrSearch = String(j.label || '');
            }
            this.jrDropdownOpen = false;
        },
        jrClear() {
            this.jrSearch = '';
            this.sujetForm.journaliste_responsable_id = null;
            this.jrDropdownOpen = true;
        },
        get selectedJr() {
            const id = this.sujetForm.journaliste_responsable_id ? +this.sujetForm.journaliste_responsable_id : null;
            if (!id) return null;
            const arr = Array.isArray(this.jrOptions) ? this.jrOptions : [];
            for (const j of arr) if (j && +j.value === id) return j;
            return null;
        },

        /** Aucun journaliste présent à la réunion → sélecteur vide. */
        get jrVide() {
            return !Array.isArray(this.jrOptions) || this.jrOptions.length === 0;
        },

        /**
         * La valeur courante est conservée mais n'est plus dans les choix
         * (la personne n'est plus présente à la réunion de rédaction).
         */
        get selectedJrHorsListe() {
            const id = this.sujetForm.journaliste_responsable_id ? +this.sujetForm.journaliste_responsable_id : null;
            return !!id && !this.selectedJr;
        },
        jrOpenSelect() {
            this.jrSearch = '';
            this.jrDropdownOpen = true;
            setTimeout(() => {
                const el = document.getElementById('jr-search-input');
                if (el) try { el.focus({ preventScroll: true }); } catch {}
            }, 0);
        },
        findSujetIndex(id) {
            const n = +id;
            for (let i = 0; i < this.sujets.length; i++) if (+this.sujets[i].id === n) return i;
            return -1;
        },
        calcDureeHM(min, sec) {
            const m = Math.max(0, Number.isFinite(+min) ? +min : 0);
            const s = Math.max(0, Math.min(59, Number.isFinite(+sec) ? +sec : 0));
            const totalSec = m * 60 + s;
            const mm = String(Math.floor(totalSec / 60)).padStart(2,'0');
            const ss = String(totalSec % 60).padStart(2,'0');
            return `${mm}:${ss}`;
        },
        majDureeLive() {
            this.sujetForm.dureeTotaleHM = this.calcDureeHM(this.sujetForm.dureeMinutes, this.sujetForm.dureeSecondes);
        },
        /**
         * Une validation d'étape déclenche un rechargement (voir clicWorkflow).
         * On rétablit ici la position de lecture et le message de confirmation,
         * sinon la page remonterait en haut et le toast se perdrait.
         */
        _restaurerApresRechargement() {
            try {
                const brutScroll = sessionStorage.getItem('cond_redac_scroll');
                sessionStorage.removeItem('cond_redac_scroll');
                if (brutScroll !== null && brutScroll !== '' && Number.isFinite(+brutScroll)) {
                    const y = +brutScroll;
                    const placer = () => { try { window.scrollTo(0, y); } catch (e) {} };
                    placer();
                    requestAnimationFrame(placer);
                    setTimeout(placer, 150); // après le rendu des lignes Alpine
                }

                const brutToast = sessionStorage.getItem('cond_redac_toast');
                sessionStorage.removeItem('cond_redac_toast');
                if (brutToast) {
                    const info = JSON.parse(brutToast);
                    if (info && info.t) this.toast(String(info.t), info.k || 'success');
                }
            } catch (e) { /* sessionStorage indisponible : on continue sans restauration */ }
        },
        toast(text, kind = 'info', duree = 3500) {
            this.toastText = String(text);
            this.toastKind = kind === 'success' || kind === 'error' ? kind : 'info';
            this.showToast = true;
            if (this._toastT) clearTimeout(this._toastT);
            this._toastT = setTimeout(() => { this.showToast = false; }, +duree || 3500);
        },

        // ============== WORKFLOW MONOPASS 5 ÉTAPES ==============
        async clicWorkflow(sujet, etape) {
            if (!sujet || !etape) return;
            if (!sujet.can_workflow || !sujet.can_workflow['statut_'+etape]) {
                this.toast(`Étape "${etape}" non activable. Terminez d'abord l'étape précédente (monopass strict).`, 'error');
                return;
            }
            const url = String(this.routes.workflowNextBase || '').replace('_SEQ_', encodeURIComponent(String(sujet.id)));
            if (!url || url.includes('_SEQ_')) {
                this.toast('Route workflow introuvable.', 'error');
                return;
            }
            try {
                const resp = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ etape: String(etape) }),
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok || !json.ok) {
                    this.toast(json.message || `Impossible de passer "${etape}" à l'étape suivante.`, 'error');
                    return;
                }
                if (await this.refreshLiveState()) {
                    this.toast(`Étape "${etape}" validée ✓ — "${sujet.title || ''}"`, 'success');
                } else {
                    this.toast('Étape validée, mais la vue ne peut pas être actualisée. Rechargement…', 'info');
                    window.location.reload();
                }
                return;
            } catch (err) {
                console.error('[workflow clic]', err);
                this.toast('Erreur réseau, veuillez réessayer.', 'error');
            }
        },

        // ============== MODALE CRUD SUJET ==============
        _resetSujetForm(mode) {
            this.modalMode = mode === 'edit' ? 'edit' : 'create';
            this.modalErrs = {};
            this.isSavingSujet = false;
            this.emissionSearch = '';
            const ft = this.typeOptions.find(t => t && t.value)?.value;
            if (mode === 'edit') {
                this.modalTitle = 'Modifier le sujet de rédaction';
                this.modalSubtitle = this.modalSubtitleEdit;
                this.modalOrdreHint = this.modalOrdreHintEdit;
                this.modalSubmitLabel = 'Enregistrer les modifications';
            } else {
                this.editingId = null;
                this.modalTitle = 'Ajouter un sujet de rédaction';
                this.modalSubtitle = this.modalSubtitleCreate;
                this.modalOrdreHint = this.modalOrdreHintCreate;
                this.modalSubmitLabel = 'Ajouter le sujet';
                this.sujetForm = {
                    position: this.defaultNextPos,
                    dureeMinutes: 2,
                    dureeSecondes: 0,
                    dureeTotaleHM: '02:00',
                    title: '',
                    angle_editorial: '',
                    type_value: ft || 'Sujet',
                    journaliste_responsable_id: null,
                    emission_id: null,
                };
                this.jrSearch = '';
                this.jrDropdownOpen = false;
                this.majDureeLive();
            }
        },
        openCreateSujetModal() {
            if (!this.canCrudRedac) { this.toast('Vous n\'avez pas les droits pour ajouter un sujet.', 'error'); return; }
            this._resetSujetForm('create');
            this.showModal = true;
        },
        openEditSujetModal(s) {
            if (!this.canCrudRedac) { this.toast('Vous n\'avez pas les droits pour modifier ce sujet.', 'error'); return; }
            if (!s) return;
            this._resetSujetForm('edit');
            this.editingId = +s.id;
            const dur = Number.isFinite(+s.duration_seconds) ? Math.max(0, +s.duration_seconds) : 120;
            const mm = Math.floor(dur / 60), ss = dur % 60;
            this.sujetForm.position = Number.isFinite(+s.position) ? +s.position : (this.sujets.length + 1);
            this.sujetForm.dureeMinutes = mm;
            this.sujetForm.dureeSecondes = ss;
            this.sujetForm.dureeTotaleHM = this.calcDureeHM(mm, ss);
            this.sujetForm.title = String(s.title || '');
            this.sujetForm.angle_editorial = String(s.angle_editorial || '');
            this.sujetForm.type_value = String(s.type_value || this.typeOptions[0]?.value || 'Sujet');
            this.sujetForm.journaliste_responsable_id = s.journaliste_responsable_id ? +s.journaliste_responsable_id : null;
            this.sujetForm.emission_id = s.emission_id ? +s.emission_id : null;
            const jrLabel = s.journaliste_nom
                ? String(s.journaliste_nom).replace(/<[^>]*>/g, '').trim()
                : '';
            this.jrSearch = jrLabel;
            this.jrDropdownOpen = false;
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.jrDropdownOpen = false;
            this.jrSearch = '';
            this.emissionSearch = '';
            if (this._toastT) clearTimeout(this._toastT);
            setTimeout(() => { this.modalErrs = {}; this.isSavingSujet = false; }, 250);
        },
        _validateSujetForm() {
            const errs = {};
            const t = (this.sujetForm.title || '').trim();
            if (!t) errs.title = 'Le titre/sujet est obligatoire (min. 3 caractères).';
            else if (t.length < 3) errs.title = 'Le titre est trop court (3 caractères minimum).';
            if (!this.sujetForm.type_value) errs.type_value = 'Type de sujet obligatoire (SUJET, REPORTAGE, etc).';
            const dur = ((+this.sujetForm.dureeMinutes || 0) * 60) + ((+this.sujetForm.dureeSecondes || 0) * 1);
            if (!Number.isFinite(+this.sujetForm.position) || +this.sujetForm.position < 1) errs.position = 'Position doit être ≥ 1.';
            if (dur < 0) errs.dureeMinutes = 'Durée invalide.';
            this.modalErrs = errs;
            return Object.keys(errs).length === 0;
        },
        async saveSujet() {
            if (!this.canCrudRedac) { this.toast('Accès refusé.', 'error'); return; }
            if (!this._validateSujetForm()) return;
            this.isSavingSujet = true;

            const body = {
                position: +this.sujetForm.position || 1,
                title: String(this.sujetForm.title || '').trim(),
                angle_editorial: this.sujetForm.angle_editorial ? String(this.sujetForm.angle_editorial).trim() : null,
                type: String(this.sujetForm.type_value),
                journaliste_responsable_id: this.sujetForm.journaliste_responsable_id ? +this.sujetForm.journaliste_responsable_id : null,
                emission_id: this.sujetForm.emission_id ? +this.sujetForm.emission_id : null,
                duration_seconds: ((+this.sujetForm.dureeMinutes || 0) * 60) + ((+this.sujetForm.dureeSecondes || 0) * 1),
            };

            let url;
            let method;
            if (this.modalMode === 'edit' && this.editingId) {
                method = 'PATCH';
                url = String(this.routes.updateSujetBase || '').replace('_SEQ_', encodeURIComponent(String(this.editingId)));
            } else {
                method = 'POST';
                url = this.routes.storeSujet || '';
            }
            if (!url) { this.isSavingSujet = false; this.toast('Route CRUD sujet introuvable.', 'error'); return; }

            try {
                const resp = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok || !json.ok) {
                    if (json.errors && typeof json.errors === 'object') {
                        const errs = {};
                        Object.keys(json.errors).forEach(k => {
                            const msgs = Array.isArray(json.errors[k]) ? json.errors[k] : [String(json.errors[k])];
                            errs[k] = msgs.join(' · ');
                        });
                        this.modalErrs = errs;
                    }
                    this.toast(json.message || 'Impossible d\'enregistrer ce sujet.', 'error');
                    this.isSavingSujet = false;
                    return;
                }
                const seq = json.sequence;
                if (this.modalMode === 'edit' && this.editingId) {
                    const idx = this.findSujetIndex(this.editingId);
                    if (idx !== -1) this.sujets.splice(idx, 1, seq);
                    this.toast(`Sujet "${seq.title || ''}" mis à jour.`, 'success');
                } else {
                    if (seq.position !== undefined && Number.isFinite(+seq.position)) {
                        const targetIdx = Math.min(this.sujets.length, Math.max(0, (+seq.position || 1) - 1));
                        this.sujets.splice(targetIdx, 0, seq);
                    } else {
                        this.sujets.unshift(seq);
                    }
                    this.defaultNextPos = +this.defaultNextPos + 1;
                    this.toast(`Sujet "${seq.title || ''}" ajouté au conducteur.`, 'success');
                }
                this.isSavingSujet = false;
                this.closeModal();
            } catch (err) {
                console.error('[redaction save sujet]', err);
                this.isSavingSujet = false;
                this.toast('Erreur réseau, veuillez réessayer.', 'error');
            }
        },
        async confirmDeleteSujet(s) {
            if (!this.canCrudRedac) { this.toast('Vous n\'avez pas les droits pour supprimer ce sujet.', 'error'); return; }
            if (!s) return;
            const label = s.title ? `"${s.title}"` : 'ce sujet';
            const confirmation = window.TvAlert?.confirmDelete
                ? await window.TvAlert.confirmDelete({
                    title: `Supprimer le sujet ${label} ?`,
                    text: 'Le workflow et les heures associées seront perdus.',
                })
                : { isConfirmed: window.confirm(`Supprimer définitivement le sujet ${label} ?\nLe workflow et les heures associées seront perdus.`) };
            if (! confirmation.isConfirmed) return;
            const url = String(this.routes.destroySujetBase || '').replace('_SEQ_', encodeURIComponent(String(s.id)));
            if (!url || url.includes('_SEQ_')) { this.toast('Route suppression introuvable.', 'error'); return; }
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            }).then(r => r.json().catch(()=>({}))).then(json => {
                if (!json.ok) { this.toast(json.message || 'Impossible de supprimer.', 'error'); return; }
                const idx = this.findSujetIndex(s.id);
                if (idx !== -1) {
                    this.sujets.splice(idx, 1);
                    this.sujets.forEach((elt, i) => { elt.position = i + 1; });
                }
                this.toast(`Sujet ${label} supprimé.`, 'success');
            }).catch(err => { console.error(err); this.toast('Erreur réseau suppression.', 'error'); });
        },
    };
}

// Global registration — pattern ROBUSTE (identique à conducteur-redaction.js) :
// on tente immédiatement, puis on ré-enregistre sur `alpine:init` si Alpine
// n'était pas encore prêt au moment de l'évaluation du module (hoisting ESM).
if (typeof window !== 'undefined') {
    window.conducteurRedactionVrai = conducteurRedactionVrai;
    window.presencePanel = presencePanel;

    const registerRedactionVrai = () => {
        if (window.Alpine && typeof window.Alpine.data === 'function') {
            window.Alpine.data('conducteurRedactionVrai', conducteurRedactionVrai);
            window.Alpine.data('presencePanel', presencePanel);
        }
    };
    registerRedactionVrai();
    document.addEventListener('alpine:init', registerRedactionVrai);
}

// =====================================================================
//  PRÉSENCES À LA RÉUNION DE RÉDACTION (personnes internes)
// =====================================================================

function presencePanel(cfg) {
    const routes = (cfg && cfg.routes) || {};
    return {
        presences:   (cfg && cfg.presences)   || [],
        journalistes:(cfg && cfg.journalistes) || [],
        gestion:     !!(cfg && cfg.gestion),
        conducteurId:(cfg && cfg.conducteurId) || 0,
        totalJournalistes: Math.max(0, +((cfg && cfg.totalJournalistes) || 0)),
        routes,
        csrf:        (cfg && cfg.csrf) || '',
        conferenceStartedAt: cfg.conferenceStartedAt || null,
        conferenceForm: '',
        conferenceBusy: false,
        conferenceDirty: false,
        mutationVersion: 0,
        pointageBusy: false,
        refreshBusy: false,
        timezone: cfg.timezone || 'Africa/Libreville',

        formOuvert: false,
        modeEdition: false,
        editingId: null,
        chargement: false,
        form: { recherche: '', listeOuverte: false, journaliste_id: null, nom_complet: '', arrivee_at: '' },

        // ---- Révision de la liste complète ----
        modalRevision: false,
        brouillon: [],
        revisionRecherche: '',
        revisionListeOuverte: false,
        chargementRevision: false,

        // ---- Pont événementiel avec l'en-tête de la fiche ----
        init() {
            try {
                this._onReviser = () => {
                    try {
                        if (this.gestion) this.ouvrirRevision();
                    } catch (_e) {}
                };
                document.addEventListener('presence:reviser', this._onReviser);
            } catch (_e) {}
            this.conferenceForm = this.conferenceStartedAt ? this.toLocalInput(this.conferenceStartedAt) : '';
            this._presenceTimer = window.setInterval(() => this.rafraichirPresences(), 15000);
            this.emitTotal();
        },

        destroy() {
            window.clearInterval(this._presenceTimer);
            try {
                if (this._onReviser) document.removeEventListener('presence:reviser', this._onReviser);
            } catch (_e) {}
        },

        /** Notifie l'en-tête du nombre courant de présents + le vivier total. */
        emitTotal() {
            try {
                document.dispatchEvent(new CustomEvent('presence:changed', {
                    detail: {
                        total: (this.presences || []).length,
                        totalJournalistes: +this.totalJournalistes || 0,
                    },
                }));
            } catch (_e) {}
        },

        // ---------- Computed ----------
        get resultats() {
            const q = (this.form.recherche || '').trim().toLowerCase();
            if (!q) return [];
            return this.journalistes.filter(j =>
                `${j.nom_complet || ''} ${j.fonction || ''} ${j.pole || ''}`.toLowerCase().includes(q)
            );
        },

        dejaPresent(id) {
            return this.presences.some(p => +p.journaliste_id === +id);
        },

        /** Part de présents sur l'ensemble des journalistes actifs (0-100). */
        get progressionPresences() {
            const total = +this.totalJournalistes || 0;
            if (total <= 0) return 0;
            return Math.min(100, Math.round(((this.presences || []).length * 100) / total));
        },

        get pointageCounts() {
            const aLHeure = this.presences.filter(p => p.ponctualite === 'a_l_heure').length;
            const enRetard = this.presences.filter(p => p.ponctualite === 'en_retard').length;
            return { aLHeure, enRetard, aPointer: this.presences.length - aLHeure - enRetard };
        },

        get conferenceLabel() {
            if (!this.conferenceStartedAt) return 'Début de conférence non renseigné';
            return new Intl.DateTimeFormat('fr-FR', { timeZone: this.timezone, dateStyle: 'short', timeStyle: 'short' }).format(new Date(this.conferenceStartedAt));
        },

        async enregistrerDebut(maintenant = false) {
            if (this.conferenceBusy || (!maintenant && !this.conferenceForm)) return;
            this.mutationVersion++;
            this.conferenceBusy = true;
            try {
                const resp = await fetch(this.routes.conference, {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ conference_started_at: maintenant ? new Date().toISOString() : this.conferenceForm }),
                });
                const json = await resp.json();
                if (!resp.ok || !json.ok) throw new Error(json.message || 'Début de conférence non enregistré.');
                this.conferenceStartedAt = json.conference_started_at;
                this.conferenceForm = this.toLocalInput(this.conferenceStartedAt);
                this.conferenceDirty = false;
                this.toast(json.message, 'success');
            } catch (err) {
                this.toast(err.message || 'Enregistrement impossible.', 'error');
            } finally { this.conferenceBusy = false; }
        },

        async pointer(presence, ponctualite) {
            if (this.pointageBusy || !this.gestion || !this.conferenceStartedAt) return;
            this.mutationVersion++;
            this.pointageBusy = true;
            try {
                const resp = await fetch(this.routes.ponctualite.replace('__PRESENCE__', String(presence.id)), {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ ponctualite }),
                });
                const json = await resp.json();
                if (!resp.ok || !json.ok) throw new Error(json.message || 'Pointage non enregistré.');
                this.presences = this.presences.map(p => +p.id === +presence.id ? json.presence : p);
            } catch (err) {
                this.toast(err.message || 'Pointage impossible.', 'error');
            } finally { this.pointageBusy = false; }
        },

        async rafraichirPresences() {
            if (document.hidden || this.refreshBusy || this.pointageBusy || this.conferenceBusy || this.chargement || this.chargementRevision || this.formOuvert || this.modalRevision) return;
            const version = this.mutationVersion;
            this.refreshBusy = true;
            try {
                const resp = await fetch(this.routes.state, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } });
                if (!resp.ok || resp.redirected) return;
                const json = await resp.json();
                if (!json.ok || version !== this.mutationVersion) return;
                this.presences = json.presences;
                if (this.conferenceStartedAt !== json.conference_started_at) {
                    this.conferenceStartedAt = json.conference_started_at;
                    if (!this.conferenceDirty) this.conferenceForm = this.conferenceStartedAt ? this.toLocalInput(this.conferenceStartedAt) : '';
                }
                this.emitTotal();
            } catch (_) {
                // La liste courante reste visible pendant une interruption réseau.
            } finally { this.refreshBusy = false; }
        },

        // ---------- Formulaire ----------
        ouvrirFormAjout() {
            this.modeEdition = false;
            this.editingId = null;
            this.form = {
                recherche: '', listeOuverte: false,
                journaliste_id: null, nom_complet: '',
                arrivee_at: this.toLocalInput(new Date()),
            };
            this.formOuvert = true;
        },

        ouvrirFormEdition(p) {
            this.modeEdition = true;
            this.editingId = p.id;
            this.form = {
                recherche: p.nom_complet, listeOuverte: false,
                journaliste_id: p.journaliste_id, nom_complet: p.nom_complet,
                arrivee_at: this.toLocalInput(p.arrivee_at),
            };
            this.formOuvert = true;
        },

        fermerForm() {
            this.formOuvert = false;
            this.modeEdition = false;
            this.editingId = null;
        },

        choisir(j) {
            this.form.journaliste_id = j.id;
            this.form.nom_complet = j.nom_complet;
            this.form.recherche = j.nom_complet;
            this.form.listeOuverte = false;
        },

        toLocalInput(v) {
            const d = v instanceof Date ? v : new Date(v);
            if (isNaN(d.getTime())) return '';
            const parts = new Intl.DateTimeFormat('en-CA', {
                timeZone: this.timezone, year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
            }).formatToParts(d);
            const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
            return `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}`;
        },

        // ---------- Révision de la liste complète ----------
        get revisionResultats() {
            const q = (this.revisionRecherche || '').trim().toLowerCase();
            if (!q) return [];
            return this.journalistes.filter(j =>
                `${j.nom_complet || ''} ${j.fonction || ''} ${j.pole || ''}`.toLowerCase().includes(q)
            );
        },

        revisionDejaLa(journalisteId) {
            return this.brouillon.some(l => +l.journaliste_id === +journalisteId);
        },

        revisionAjouter(j) {
            if (this.revisionDejaLa(j.id)) return;
            this.brouillon.push({
                journaliste_id: j.id,
                nom_complet:   j.nom_complet,
                fonction:      j.fonction,
                pole:          j.pole,
                arrivee_at:    this.toLocalInput(new Date()),
            });
            this.revisionRecherche = '';
            this.revisionListeOuverte = false;
        },

        revisionRetirer(journalisteId) {
            this.brouillon = this.brouillon.filter(l => +l.journaliste_id !== +journalisteId);
        },

        /** Ouvre le modal avec une COPIE DE TRAVAIL de la liste actuelle. */
        ouvrirRevision() {
            this.brouillon = this.presences.map(p => ({
                journaliste_id: p.journaliste_id,
                nom_complet:    p.nom_complet,
                fonction:       p.fonction,
                pole:           p.pole,
                arrivee_at:     this.toLocalInput(p.arrivee_at),
            }));
            this.revisionRecherche = '';
            this.revisionListeOuverte = false;
            this.modalRevision = true;
        },

        fermerRevision() {
            this.modalRevision = false;
            this.brouillon = [];
            this.revisionRecherche = '';
            this.revisionListeOuverte = false;
        },

        /**
         * Enregistre la liste RÉVISÉE.
         * Le serveur réconcilie : ajoute les nouveaux, met à jour les heures,
         * supprime ceux qui ne sont plus dans la liste.
         */
        async enregistrerRevision() {
            if (this.chargementRevision) return;
            const url = this.routes.reconcile;
            if (!url) return;

            this.mutationVersion++;
            this.chargementRevision = true;
            try {
                const resp = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        presences: this.brouillon.map(l => ({
                            journaliste_id: l.journaliste_id,
                            arrivee_at:    l.arrivee_at,
                        })),
                    }),
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok || !json.ok) {
                    throw new Error(json.message || `Erreur ${resp.status}`);
                }

                this.presences = Array.isArray(json.presences) ? json.presences : [];
                this.emitTotal();
                this.toast(json.message || 'Liste révisée.', 'success');
                this.fermerRevision();
            } catch (err) {
                console.error('Erreur révision présences:', err);
                this.toast(err?.message || 'Erreur lors de l\'enregistrement de la liste.', 'error');
            } finally {
                this.chargementRevision = false;
            }
        },

        // ---------- Actions ----------
        async enregistrer() {
            if (!this.form.journaliste_id || !this.form.arrivee_at || this.chargement) return;

            const enEdition = this.modeEdition && this.editingId;
            const url = enEdition
                ? (this.routes.update || '').replace('__PRESENCE__', String(this.editingId))
                : this.routes.store;
            if (!url) return;

            this.mutationVersion++;
            this.chargement = true;
            try {
                const resp = await fetch(url, {
                    method: enEdition ? 'PATCH' : 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        journaliste_id: this.form.journaliste_id,
                        arrivee_at: this.form.arrivee_at,
                    }),
                });
                const json = await resp.json().catch(() => ({}));

                if (!resp.ok || !json.ok) {
                    throw new Error(json.message || `Erreur ${resp.status}`);
                }

                if (enEdition) {
                    const maj = json.presence;
                    this.presences = this.presences
                        .map(p => (+p.id === +this.editingId ? { ...p, ...maj } : p))
                        .sort((a, b) => (Date.parse(a.arrivee_at) || 0) - (Date.parse(b.arrivee_at) || 0));
                    this.toast(json.message || 'Heure d\'arrivée mise à jour.', 'success');
                } else {
                    this.presences = [...this.presences, json.presence]
                        .sort((a, b) => (Date.parse(a.arrivee_at) || 0) - (Date.parse(b.arrivee_at) || 0));
                    this.toast(json.message || 'Présence ajoutée.', 'success');
                }

                this.fermerForm();
                this.emitTotal();
            } catch (err) {
                console.error('Erreur présence:', err);
                this.toast(err?.message || 'Erreur lors de l\'enregistrement.', 'error');
            } finally {
                this.chargement = false;
            }
        },

        async supprimer(p) {
            const url = (this.routes.destroy || '').replace('__PRESENCE__', String(p.id));
            if (!url) return;

            const confirmation = window.TvAlert?.confirmDelete
                ? await window.TvAlert.confirmDelete({
                    title: `Retirer ${p.nom_complet} des présents ?`,
                    text: 'Cette action est réversible (vous pourrez la ré-ajouter).',
                    confirmText: '<i class="fa-solid fa-user-minus mr-1.5"></i> Retirer',
                })
                : { isConfirmed: window.confirm(`Retirer ${p.nom_complet} de la liste des présents ?\n\nCette action est réversible (vous pourrez la ré-ajouter).`) };
            if (! confirmation.isConfirmed) return;
            this.mutationVersion++;

            try {
                const resp = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                });
                const json = await resp.json().catch(() => ({}));
                if (!resp.ok || !json.ok) {
                    throw new Error(json.message || `Erreur ${resp.status}`);
                }

                this.presences = this.presences.filter(x => +x.id !== +p.id);
                this.emitTotal();
                this.toast(json.message || 'Présence retirée.', 'success');
            } catch (err) {
                console.error('Erreur suppression présence:', err);
                this.toast(err?.message || 'Erreur lors de la suppression.', 'error');
            }
        },

        toast(message, type = 'info') {
            try {
                if (window.TvAlert && typeof window.TvAlert.success === 'function' && type === 'success') {
                    window.TvAlert.success(message, { title: 'Présences', timer: 1800, toast: true });
                    return;
                }
                if (window.TvAlert && typeof window.TvAlert.error === 'function' && type === 'error') {
                    window.TvAlert.error(message);
                    return;
                }
            } catch (_e) {}
            if (type === 'error') window.alert(message);
        },
    };
}
