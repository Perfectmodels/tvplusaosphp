import Alpine from 'alpinejs';

if (typeof window !== 'undefined') {
    window.Alpine = window.Alpine || Alpine;
}

export default function chefAntennePanel() {
    return {
        csrf: '',
        routes: {},
        sequences: [],
        activeId: 0,
        antenne: { startedAt: null, offsetSec: 0, endedAt: null },
        totalSec: 0,
        elapsedSec: 0,
        notes: '',
        notesDirty: false,
        saving: false,
        saved: false,
        saveError: '',
        syncState: 'pending',
        lastSyncAt: '',
        controlError: '',
        _syncInterval: null,
        _refreshing: false,
        _saveTimer: null,
        busy: false,
        _tick: null,
        prompterOpen: false,
        prompterPaused: false,
        prompterSpeed: 30,
        prompterPos: { x: null, y: null },
        _drag: null,
        _raf: null,
        _lastTs: 0,

        initialize(cfg) {
            this.csrf = cfg.csrf || '';
            this.routes = cfg.routes || {};
            this.refresh();
            this._tick = window.setInterval(() => this.computeElapsed(), 1000);
            // Synchronize the active sequence and antenna timer across workstations.
            this._syncInterval = window.setInterval(() => {
                if (!document.hidden && !this.busy) this.refresh();
            }, 10000);
        },

        destroy() {
            if (this._tick) window.clearInterval(this._tick);
            if (this._syncInterval) window.clearInterval(this._syncInterval);
            if (this._saveTimer) window.clearTimeout(this._saveTimer);
            this.stopPrompterLoop();
        },

        async refresh() {
            if (!this.routes.liveState || this._refreshing) return;
            this._refreshing = true;
            try {
                const response = await fetch(this.routes.liveState, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error(`État antenne indisponible (${response.status}).`);
                const data = await response.json();
                this.sequences = data.sequences || [];
                this.antenne = data.antenne || this.antenne;
                // Synchronisation stricte avec le conducteur : dès que le chrono
                // passe à la séquence suivante, le prompteur suit la même.
                if (Number(data.activeSequenceId || 0) > 0) {
                    this.activeId = Number(data.activeSequenceId);
                } else if (!this.activeId) {
                    this.activeId = Number(this.sequences[0]?.id || 0);
                }
                this.totalSec = this.sequences.reduce((sum, s) => sum + this.seqDuration(s), 0);
                this.computeElapsed();
                if (!this.notesDirty) this.syncNotes();
                this.notifyActive();
                this.syncState = 'connected';
                this.lastSyncAt = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            } catch (e) {
                this.syncState = 'offline';
                console.error('Chef antenne panel:', e);
            } finally {
                this._refreshing = false;
            }
        },

        seqDuration(s) {
            return Math.max(0, Number(s.duree_antenne_sec ?? s.duration_seconds ?? 120));
        },

        computeElapsed() {
            const a = this.antenne || {};
            if (!a.startedAt) {
                this.elapsedSec = Number(a.offsetSec || 0);
                return;
            }
            const fin = a.endedAt ? new Date(a.endedAt).getTime() : Date.now();
            const debut = new Date(a.startedAt).getTime();
            this.elapsedSec = Math.max(0, Number(a.offsetSec || 0) + Math.floor((fin - debut) / 1000));
        },

        formatSec(sec) {
            const total = Math.max(0, Number(sec) || 0);
            const h = Math.floor(total / 3600);
            const m = Math.floor((total % 3600) / 60);
            const s = total % 60;
            return h > 0
                ? `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
                : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        },

        progress() {
            if (this.totalSec <= 0) return 0;
            return Math.min(100, Math.round((this.elapsedSec / this.totalSec) * 100));
        },

        enMarche() {
            return !!this.antenne.startedAt && !this.antenne.endedAt;
        },

        terminee() {
            return !!this.antenne.endedAt;
        },

        courante() {
            const active = this.sequences.find((s) => Number(s.id) === Number(this.activeId));
            if (active) return active;
            // Tant que le chrono n'a pas désigné de séquence active, on travaille
            // sur la première : les notes restent enregistrables immédiatement.
            return this.sequences[0] || null;
        },

        selectSequence(seq) {
            if (!seq) return;
            if (this.notesDirty) {
                const previous = this.courante();
                const draft = this.notes;
                this.saveNotes();
                // Preserve the draft locally even if the network save fails.
                if (previous) previous.notes_presentateur = draft;
            }
            this.activeId = Number(seq.id);
            this.notesDirty = false;
            this.syncNotes();
            this.$nextTick(() => {
                const el = this.$refs.prompterTexte;
                if (el) el.scrollTop = 0;
            });
            this.notifyActive();
        },

        sequenceLabel(seq) {
            if (!seq) return 'Aucune séquence';
            return `#${seq.position || ''} · ${seq.title || 'Sans titre'}`;
        },

        notifyActive() {
            const seq = this.sequences.find((s) => Number(s.id) === Number(this.activeId));
            if (!seq) return;
            window.dispatchEvent(new CustomEvent('tv-sequence-active', {
                detail: {
                    id: Number(seq.id),
                    position: seq.position,
                    title: seq.title,
                    duree: seq.duree_hm || '',
                    running: this.enMarche(),
                },
            }));
        },

        syncNotes() {
            const seq = this.courante();
            this.notes = seq ? String(seq.notes_presentateur || '') : '';
        },

        async control(action) {
            if (this.busy || !this.routes[action]) return;
            this.busy = true;
            this.controlError = '';
            try {
                const response = await fetch(this.routes[action], {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: '{}',
                });
                if (!response.ok) {
                    const payload = await response.json().catch(() => null);
                    this.controlError = payload?.message || 'Impossible de piloter le chronomètre.';
                    return;
                }
                await this.refresh();
                if (action === 'start') this.ouvrirPrompter();
            } catch (e) {
                this.controlError = 'Connexion interrompue. Vérifiez votre accès avant de réessayer.';
                console.error('Chef antenne control:', e);
            } finally {
                this.busy = false;
            }
        },

        scheduleSave() {
            this.saved = false;
            this.saveError = '';
            if (this._saveTimer) window.clearTimeout(this._saveTimer);
            this._saveTimer = window.setTimeout(() => this.saveNotes(), 800);
        },

        async saveNotes() {
            const seq = this.courante();
            if (!seq || !this.routes.saveNotes) return;
            if (this.saving) {
                this._saveAgain = true;
                return;
            }
            const sentNotes = this.notes;
            if (this._saveTimer) window.clearTimeout(this._saveTimer);
            this.saving = true;
            this.saveError = '';
            try {
                const url = this.routes.saveNotes.replace('__SEQ__', String(seq.id));
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ notes_presentateur: sentNotes }),
                });
                if (response.ok) {
                    seq.notes_presentateur = sentNotes;
                    if (this.courante()?.id === seq.id && this.notes === sentNotes) {
                        this.notesDirty = false;
                        this.saved = true;
                    }
                    window.setTimeout(() => { this.saved = false; }, 2500);
                } else {
                    const json = await response.json().catch(() => null);
                    this.saveError = json?.message || 'Enregistrement impossible.';
                }
            } catch (e) {
                this.saveError = 'Enregistrement impossible — vérifiez la connexion.';
                console.error('Chef antenne notes:', e);
            } finally {
                this.saving = false;
                if (this._saveAgain && this.notesDirty) {
                    this._saveAgain = false;
                    this.scheduleSave();
                } else {
                    this._saveAgain = false;
                }
            }
        },

        notesCourantes() {
            const seq = this.courante();
            return seq ? String(seq.notes_presentateur || '') : '';
        },

        ouvrirPrompter() {
            this.prompterOpen = true;
            this.prompterPaused = false;
            this.$nextTick(() => this.startPrompterLoop());
        },

        fermerPrompter() {
            this.prompterOpen = false;
            this.stopPrompterLoop();
            this._drag = null;
        },

        startDrag(event) {
            if (event.target.closest('button, input, select, label, a')) return;
            const panel = this.$refs.prompterPanel;
            if (!panel) return;
            const rect = panel.getBoundingClientRect();
            this._drag = {
                offsetX: event.clientX - rect.left,
                offsetY: event.clientY - rect.top,
            };
            this.prompterPos = { x: rect.left, y: rect.top };
            event.preventDefault();
        },

        onDrag(event) {
            if (!this._drag) return;
            const panel = this.$refs.prompterPanel;
            if (!panel) return;
            const maxX = window.innerWidth - panel.offsetWidth - 8;
            const maxY = window.innerHeight - panel.offsetHeight - 8;
            this.prompterPos = {
                x: Math.max(8, Math.min(maxX, event.clientX - this._drag.offsetX)),
                y: Math.max(8, Math.min(maxY, event.clientY - this._drag.offsetY)),
            };
        },

        endDrag() {
            this._drag = null;
        },

        stylePrompter() {
            if (this.prompterPos.x === null || this.prompterPos.y === null) return '';
            return `left:${this.prompterPos.x}px;top:${this.prompterPos.y}px;right:auto;bottom:auto;`;
        },

        startPrompterLoop() {
            this.stopPrompterLoop();
            this._lastTs = 0;
            const step = (ts) => {
                if (!this.prompterOpen) return;
                if (!this._lastTs) this._lastTs = ts;
                const delta = Math.max(0, (ts - this._lastTs) / 1000);
                this._lastTs = ts;
                if (!this.prompterPaused) {
                    const el = this.$refs.prompterTexte;
                    if (el && el.scrollHeight > el.clientHeight) {
                        el.scrollTop += Number(this.prompterSpeed || 0) * delta;
                        if (el.scrollTop >= el.scrollHeight - el.clientHeight - 1) el.scrollTop = 0;
                    }
                }
                this._raf = window.requestAnimationFrame(step);
            };
            this._raf = window.requestAnimationFrame(step);
        },

        stopPrompterLoop() {
            if (this._raf) {
                window.cancelAnimationFrame(this._raf);
                this._raf = null;
            }
        },
    };
}

// Exposition globale : les vues Blade utilisent x-data="chefAntennePanel()".
if (typeof window !== 'undefined') {
    window.chefAntennePanel = chefAntennePanel;
    if (window.Alpine) {
        window.Alpine.data('chefAntennePanel', chefAntennePanel);
    } else {
        document.addEventListener('alpine:init', () => {
            if (window.Alpine) window.Alpine.data('chefAntennePanel', chefAntennePanel);
        });
    }
}
