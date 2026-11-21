import Alpine from 'alpinejs';

if (typeof window !== 'undefined') {
    window.Alpine = window.Alpine || Alpine;
}

export default function conducteurRedaction() {
    return {
        // ============ ÉTAT =============
        conducteurId: null,
        csrf: '',
        routes: {},
        sequences: [],
        dureeActuelle: '00:00',
        syncing: false,
        syncError: false,
        lastSyncAt: '',
        _syncTimer: null,

        modeReorg: false,
        ordreEnCours: false,
        modaleSequenceVisible: false,
        modaleChargement: false,
        modaleErreur: '',
        _modalInitialForm: null,
        _modalRequestId: 0,
        _modalReturnFocus: null,
        modaleSuppressionVisible: false,
        modeEdition: false,
        sequenceEnCoursId: null,
        envoiEnCours: false,
        suppressionTotaleEnCours: false,
        seqASupprimer: { id: null, titre: '' },

        // Invitation d'intervenants : liste ordonnée (le 1ᵉʳ = principal)
        inviteSelection: '',
        inviteSearch: '',
        inviteDropdownOpen: false,

        formSeq: {
            position: 1,
            title: '',
            type: 'SUJET',
            duration_minutes: 2,
            duration_seconds: 0,
            intervenant_ids: [],
            intervenants: [],
            fonction_text: '',
            journaliste_responsable_id: '',
            note_regie: '',
            angle_editorial: '',
            alerte_info: false,
        },

        toastVisible: false,
        toastType: 'success',
        toastMessage: '',
        _toastT: null,

        // ============ INIT ============
        init(cfg) {
            // Alpine appelle aussi `init()` automatiquement, sans argument.
            if (!cfg || typeof cfg !== 'object') return;
            this.conducteurId = cfg.conducteurId;
            this.csrf = cfg.csrfToken;
            this.routes = cfg.routes;
            this.sequences = cfg.sequences || [];
            this.dureeActuelle = cfg.dureeActuelle || '00:00';
            if (this._syncTimer) window.clearInterval(this._syncTimer);
            this._syncTimer = window.setInterval(() => {
                if (document.visibilityState === 'visible'
                    && !this.modaleSequenceVisible && !this.ordreEnCours
                    && !this.envoiEnCours && !this.suppressionTotaleEnCours) {
                    this.refreshFromServer(true);
                }
            }, 15000);
            this.refreshFromServer(true);
        },

        destroy() {
            if (this._syncTimer) window.clearInterval(this._syncTimer);
            if (this._toastT) window.clearTimeout(this._toastT);
            this._modalRequestId++;
        },

        async refreshFromServer(silent = true, force = false) {
            if (this.syncing || !this.routes.liveState) return false;
            if (!force && (this.modaleSequenceVisible || this.ordreEnCours
                || this.envoiEnCours || this.suppressionTotaleEnCours)) return false;

            this.syncing = true;
            try {
                const response = await fetch(this.routes.liveState, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error('Actualisation impossible (' + response.status + ').');
                const snapshot = await response.json();
                if (!Array.isArray(snapshot.chefSequences)) {
                    throw new Error('Réponse conducteur incomplète.');
                }
                if (force || (!this.modaleSequenceVisible && !this.ordreEnCours)) {
                    this.sequences = snapshot.chefSequences;
                    this.dureeActuelle = snapshot.dureeActuelle || this.dureeActuelle;
                }
                this.syncError = false;
                this.lastSyncAt = new Date().toLocaleTimeString('fr-FR', {
                    hour: '2-digit', minute: '2-digit',
                });
                if (!silent) this.showToast('Conducteur actualisé.', 'success');
                return true;
            } catch (error) {
                this.syncError = true;
                if (!silent) this.showToast(error.message || 'Connexion indisponible.', 'error');
                console.error('Actualisation conducteur :', error);
                return false;
            } finally {
                this.syncing = false;
            }
        },

        // ============ CRUD SÉQUENCE ============
        ouvrirModaleSequence(seqId = null) {
            if (this.envoiEnCours || this.suppressionTotaleEnCours) return;
            this.modeEdition = !!seqId;
            this.sequenceEnCoursId = seqId;
            this.envoiEnCours = false;
            this.inviteSelection = '';
            this.modaleErreur = '';
            this.modaleChargement = !!seqId;
            this._modalReturnFocus = document.activeElement;
            const requestId = ++this._modalRequestId;

            this.formSeq = {
                position: this.sequences.length + 1,
                title: '',
                type: 'SUJET',
                duration_minutes: 2,
                duration_seconds: 0,
                intervenant_ids: [],
                intervenants: [],
                fonction_text: '',
                journaliste_responsable_id: '',
                note_regie: '',
                angle_editorial: '',
                alerte_info: false,
            };

            if (seqId) {
                const payloadUrl = this.routes.seqPayloadBase.replace('_SEQ_', seqId);
                fetch(payloadUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                    .then(r => r.ok ? r.json() : null)
                    .then(json => {
                        if (requestId !== this._modalRequestId) return;
                        if (!json) throw new Error('Chargement impossible.');
                        this.formSeq = {
                            position: Number(json.position) || 1,
                            title: json.title || '',
                            type: json.type_value || 'SUJET',
                            duration_minutes: json.duration_minutes_floor ?? 2,
                            duration_seconds: json.duration_seconds_remain ?? 0,
                            intervenant_ids: (json.intervenant_ids || []).map(Number),
                            intervenants: json.intervenants || [],
                            fonction_text: json.fonction_text || '',
                            journaliste_responsable_id: json.journaliste_responsable_id || '',
                            note_regie: json.note_regie || '',
                            angle_editorial: json.angle_editorial || '',
                            alerte_info: !!json.alerte_info,
                        };
                        this._modalInitialForm = JSON.stringify(this.formSeq);
                    })
                    .catch(error => { if (requestId === this._modalRequestId) this.modaleErreur = error.message || 'Chargement impossible.'; })
                    .finally(() => {
                        if (requestId !== this._modalRequestId) return;
                        this.modaleChargement = false;
                        this.$nextTick(() => {
                            (this.modaleErreur
                                ? this.$refs.sequenceDialog
                                : this.$refs.sequenceDialog?.querySelector('input[name="title"]'))?.focus();
                        });
                    });
            }

            if (!seqId) this._modalInitialForm = JSON.stringify(this.formSeq);
            this.modaleSequenceVisible = true;
            this.$nextTick(() => {
                const control = !seqId ? this.$refs.sequenceDialog?.querySelector('input[name="title"]') : null;
                (control || this.$refs.sequenceDialog)?.focus();
            });
        },

        bloquerTabModale(event) {
            const dialog = this.$refs.sequenceDialog;
            if (!dialog || !this.modaleSequenceVisible) return;
            const items = Array.from(dialog.querySelectorAll(
                'button:not([disabled]), a[href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(el => el.getClientRects().length > 0);
            if (!items.length) {
                event.preventDefault();
                dialog.focus();
                return;
            }
            const first = items[0], last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        },

        fermerToutesModales(force = false) {
            if (this.envoiEnCours && !force) return;
            if (!force && this.modaleSequenceVisible && !this.modaleChargement && this._modalInitialForm
                && JSON.stringify(this.formSeq) !== this._modalInitialForm
                && !window.confirm('Abandonner les modifications non enregistrées ?')) return;
            this._modalRequestId++;
            this.modaleSequenceVisible = false;
            this.modaleSuppressionVisible = false;
            this.modaleErreur = '';
            this.modaleChargement = false;
            this._modalInitialForm = null;
            this.sequenceEnCoursId = null;
            const previous = this._modalReturnFocus;
            this._modalReturnFocus = null;
            this.$nextTick(() => {
                if (previous?.isConnected) previous.focus({ preventScroll: true });
            });
        },

                // ============ INVITÉS (1 ou plusieurs par sujet) ============
        ajouterInvite() {
            const id = Number(this.inviteSelection);
            if (!id) return;
            if (this.formSeq.intervenant_ids.includes(id)) {
                this.showToast('Cet invité est déjà dans le sujet.', 'info');
                return;
            }
            const isDebat = String(this.formSeq.type || '').toUpperCase() === 'DÉBAT';
            if (isDebat && this.formSeq.intervenant_ids.length >= 4) {
                this.showToast('Un débat ne peut comporter que 4 intervenants maximum.', 'error');
                return;
            }
            this.formSeq.intervenant_ids.push(id);
            this.inviteSelection = '';
            this.inviteSearch = '';
            this.inviteDropdownOpen = false;
            this.autoFillFonctionFromIntervenant();
        },

        retirerInvite(index) {
            this.formSeq.intervenant_ids.splice(index, 1);
        },

        deplacerInviteHaut(index) {
            if (index <= 0) return;
            const tmp = this.formSeq.intervenant_ids[index];
            this.formSeq.intervenant_ids.splice(index, 1);
            this.formSeq.intervenant_ids.splice(index - 1, 0, tmp);
        },

        deplacerInviteBas(index) {
            const liste = this.formSeq.intervenant_ids;
            if (index >= liste.length - 1) return;
            const tmp = liste[index];
            liste.splice(index, 1);
            liste.splice(index + 1, 0, tmp);
        },

        intervenantCorrespond(haystack, search) {
            const q = String(search || '').trim().toLowerCase();
            if (!q) return true;
            return String(haystack || '').toLowerCase().includes(q);
        },

        labelIntervenant(id) {
            const sel = document.querySelector('#invite-ajout-select option[value="' + id + '"]');
            if (sel && sel.value) return sel.textContent.trim();
            const connu = (this.formSeq.intervenants || []).find(i => Number(i.id) === Number(id));
            return connu && connu.label ? connu.label : 'Invité #' + id;
        },

        autoFillFonctionFromIntervenant() {
            // Pré-remplissage depuis l'invité principal (le 1ᵉʳ de la liste)
            const id = this.formSeq.intervenant_ids[0];
            if (!id) return;
            const sel = document.querySelector('#invite-ajout-select option[value="' + id + '"]');
            if (!sel) return;
            const f = sel.getAttribute('data-fonction');
            if (f && !this.formSeq.fonction_text) {
                this.formSeq.fonction_text = f;
            }
        },

        async soumettreModaleSequence() {
            if (this.envoiEnCours || this.modaleChargement) return;
            this.modaleErreur = '';
            const title = String(this.formSeq.title || '').trim();
            const minutes = Number(this.formSeq.duration_minutes);
            const seconds = Number(this.formSeq.duration_seconds);
            if (!title || title.length > 255) {
                this.modaleErreur = 'Le titre est obligatoire (255 caractères maximum).';
                return;
            }
            if (!Number.isInteger(minutes) || minutes < 0 || minutes > 600
                || !Number.isInteger(seconds) || seconds < 0 || seconds > 59
                || minutes * 60 + seconds < 1 || minutes * 60 + seconds > 36000) {
                this.modaleErreur = 'La durée doit être comprise entre 1 seconde et 600 minutes.';
                return;
            }
            if (!this.modeEdition && (!Number.isInteger(+this.formSeq.position)
                || +this.formSeq.position < 1 || +this.formSeq.position > this.sequences.length + 1)) {
                this.modaleErreur = 'Position invalide pour ce conducteur.';
                return;
            }
            if (['DEBAT', 'DÉBAT'].includes(String(this.formSeq.type || '').toUpperCase())
                && (this.formSeq.intervenant_ids.length < 2 || this.formSeq.intervenant_ids.length > 4)) {
                this.modaleErreur = 'Un débat exige entre 2 et 4 intervenants.';
                return;
            }
            this.formSeq.title = title;
            this.envoiEnCours = true;

            const formData = new FormData();
            Object.keys(this.formSeq).forEach(k => {
                const v = this.formSeq[k];
                // Invités gérés à part (JSON) + liste de détails hors formulaire
                if (k === 'intervenant_ids' || k === 'intervenants' || (k === 'position' && this.modeEdition)) return;
                if (k === 'alerte_info') { formData.append(k, v ? '1' : '0'); return; }
                if (v === null || v === undefined || v === '') return;
                formData.append(k, typeof v === 'number' ? String(v) : v);
            });
            // Toujours envoyé, même vide : « [] » = retirer tous les invités
            formData.append('intervenant_ids', JSON.stringify(this.formSeq.intervenant_ids || []));
            formData.append('conducteur_id', String(this.conducteurId));

            let url;
            let method = 'POST';
            if (this.modeEdition && this.sequenceEnCoursId) {
                url = this.routes.seqUpdateBase.replace('_SEQ_', this.sequenceEnCoursId);
                method = 'PATCH';
                formData.append('_method', 'PATCH');
            } else {
                url = this.routes.seqStore;
            }

            try {
                const resp = await fetch(url, {
                    method,
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        // JSON strict : jamais de redirection back() à suivre
                        // (ERR_TOO_MANY_REDIRECTS) + 422 de validation en JSON.
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                // Filet de sécurité : une redirection signale une anomalie
                // (session expirée, middleware…) → on la suit volontairement.
                if (resp.redirected) {
                    window.location.href = resp.url;
                    return;
                }

                const json = await resp.json().catch(() => null);

                if (resp.ok) {
                    this.fermerToutesModales(true);
                    const upToDate = await this.refreshFromServer(true, true);
                    this.showToast(
                        ((json && json.message) || (this.modeEdition ? 'Modifications enregistrées.' : 'Élément ajouté au conducteur.'))
                        + (upToDate ? '' : ' Utilisez « Actualiser » pour voir la liste à jour.'),
                        upToDate ? 'success' : 'info'
                    );
                    return;
                }

                // 422 (validation) / 403 (droits) / 419 (csrf) → message serveur
                if (json && json.errors) {
                    const premier = Object.values(json.errors)[0];
                    this.modaleErreur = Array.isArray(premier) ? premier[0] : String(premier);
                    this.showToast(this.modaleErreur, 'error');
                    return;
                }
                this.modaleErreur = (json && json.message) || 'Une erreur est survenue.';
                this.showToast(this.modaleErreur, 'error');
            } catch (e) {
                console.error(e);
                this.modaleErreur = 'Erreur réseau, veuillez réessayer.';
                this.showToast(this.modaleErreur, 'error');
            } finally {
                this.envoiEnCours = false;
            }
        },

        async supprimerSequence(id, titre) {
            if (this.envoiEnCours || this.suppressionTotaleEnCours) return;
            const confirmation = window.TvAlert?.confirmDelete
                ? await window.TvAlert.confirmDelete({
                    title: 'Supprimer cet élément ?',
                    text: `« ${titre || ''} » — cette action est irréversible. Les horaires des éléments suivants seront recalculés.`,
                })
                : { isConfirmed: confirm(`Supprimer « ${titre || ''} » ? Cette action est irréversible et recalculera les horaires des éléments suivants.`) };
            if (! confirmation.isConfirmed) return;
            this.seqASupprimer = { id, titre: titre || '' };
            await this.confirmerSuppression();
        },

        async confirmerSuppression() {
            if (!this.seqASupprimer.id || this.envoiEnCours) return;
            this.envoiEnCours = true;
            try {
                const url = this.routes.seqDestroyBase.replace('_SEQ_', this.seqASupprimer.id);
                const resp = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                if (resp.redirected) {
                    window.location.href = resp.url;
                    return;
                }
                const json = await resp.json().catch(() => null);
                if (resp.ok) {
                    const upToDate = await this.refreshFromServer(true, true);
                    this.showToast(
                        ((json && json.message) || `« ${this.seqASupprimer.titre} » supprimé.`)
                        + (upToDate ? '' : ' Utilisez « Actualiser ».'),
                        upToDate ? 'success' : 'info'
                    );
                    return;
                }
                this.showToast((json && json.message) || 'Impossible de supprimer.', 'error');
            } catch (e) {
                this.showToast('Erreur réseau.', 'error');
            } finally {
                this.envoiEnCours = false;
            }
        },

        async supprimerTout() {
            if (this.suppressionTotaleEnCours || this.envoiEnCours) return;
            const confirmation = window.TvAlert?.confirmDelete
                ? await window.TvAlert.confirmDelete({
                    title: 'Supprimer TOUS les éléments du conducteur ?',
                    text: 'Cette action est irréversible.',
                    confirmText: '<i class="fa-solid fa-trash mr-1.5"></i> Tout supprimer',
                })
                : { isConfirmed: confirm('Supprimer TOUS les éléments du conducteur ? Cette action est irréversible.') };
            if (! confirmation.isConfirmed) return;
            this.suppressionTotaleEnCours = true;
            let echecs = 0;
            let i = this.sequences.length - 1;
            const supprNext = async () => {
                if (i < 0) {
                    this.suppressionTotaleEnCours = false;
                    if (echecs > 0) {
                        this.showToast(`${echecs} élément(s) n'ont pas pu être supprimé(s). La page va être rechargée pour mettre à jour l'affichage.`, 'error');
                    } else {
                        this.showToast('Tous les éléments ont été supprimés.', 'success');
                    }
                    await this.refreshFromServer(true, true);
                    return;
                }
                const s = this.sequences[i];
                try {
                    const url = this.routes.seqDestroyBase.replace('_SEQ_', s.id);
                    const resp = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({})
                    });
                    if (! resp.ok) echecs++;
                } catch { echecs++; }
                i--;
                setTimeout(supprNext, 30);
            };
            supprNext();
        },

        // ============ RÉORGANISATION ============
        toggleModeReorganisation() {
            this.modeReorg = !this.modeReorg;
            if (!this.modeReorg && this.sequences.length > 1) {
                this.envoyerReorder();
            }
        },

        deplacerHaut(idx) {
            if (idx <= 0 || this.ordreEnCours) return;
            const tmp = this.sequences[idx];
            this.sequences.splice(idx, 1);
            this.sequences.splice(idx - 1, 0, tmp);
            this.envoyerReorder();
        },

        deplacerBas(idx) {
            if (idx >= this.sequences.length - 1 || this.ordreEnCours) return;
            const tmp = this.sequences[idx];
            this.sequences.splice(idx, 1);
            this.sequences.splice(idx + 1, 0, tmp);
            this.envoyerReorder();
        },

        async envoyerReorder() {
            if (this.ordreEnCours) return;
            const ids = this.sequences.map(s => s.id);
            this.ordreEnCours = true;
            try {
                const resp = await fetch(this.routes.reorder, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ordered_ids: ids })
                });
                const json = await resp.json().catch(() => null);
                if (!resp.ok || !json?.ok) {
                    throw new Error(json?.message || 'Impossible de réorganiser les séquences.');
                }
                if (resp.ok && json && json.ok) {
                    (json.sequences || []).forEach(serv => {
                        const idx = this.findSeqIndex(serv.id);
                        if (idx === -1) return;
                        this.sequences[idx].debut_hm = serv.heure_debut_calculee;
                        this.sequences[idx].fin_hm   = serv.heure_fin_calculee;
                        this.sequences[idx].duree_hm = serv.duree_hm;
                    });
                    if (json.duree_totale_hm) this.dureeActuelle = json.duree_totale_hm;
                    await this.refreshFromServer(true, true);
                    this.showToast('Ordre mis à jour.', 'success');
                }
            } catch (e) {
                this.showToast('Ordre non enregistré : ' + (e.message || 'erreur réseau'), 'error');
                await this.refreshFromServer(true, true);
                console.error('Reorder error', e);
            } finally {
                this.ordreEnCours = false;
            }
        },

        // ============ UTIL ============
        findSeqIndex(id) {
            return this.sequences.findIndex(s => Number(s.id) === Number(id));
        },

        showToast(msg, type = 'info', duree = 3500) {
            this.toastMessage = msg;
            this.toastType = type;
            this.toastVisible = true;
            if (this._toastT) clearTimeout(this._toastT);
            this._toastT = setTimeout(() => { this.toastVisible = false; }, duree);
        },
    };
}

// Exposer globalement (Blade x-data utilise window.* convention Alpine)
if (typeof window !== 'undefined') {
    window.conducteurRedaction = conducteurRedaction;
    if (window.Alpine) {
        window.Alpine.data('conducteurRedaction', conducteurRedaction);
    } else {
        document.addEventListener('alpine:init', () => {
            if (window.Alpine) window.Alpine.data('conducteurRedaction', conducteurRedaction);
        });
    }
}
