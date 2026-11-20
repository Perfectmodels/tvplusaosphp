import QrScanner from 'qr-scanner';

const root = document.querySelector('[data-guest-qr-reader]');

if (root) {
    const video = document.getElementById('qr-video');
    const selectEmission = document.getElementById('emission-select');
    const startButton = document.getElementById('camera-start');
    const stopButton = document.getElementById('camera-stop');
    const switchButton = document.getElementById('camera-switch');
    const placeholder = document.getElementById('camera-placeholder');
    const status = document.getElementById('camera-status');
    const indicator = document.getElementById('camera-indicator');
    const manualForm = document.getElementById('manual-form');
    const manualInput = document.getElementById('manual-code');
    const photoInput = document.getElementById('photo-input');
    const photoButton = document.getElementById('photo-button');
    const message = document.getElementById('scan-message');
    const resumeContainer = document.getElementById('resume-container');
    const resumeButton = document.getElementById('scan-resume');
    const dialog = document.getElementById('checkin-dialog');
    const confirmButton = document.getElementById('checkin-confirm');
    const cancelButton = document.getElementById('checkin-cancel');
    const journeyList = document.getElementById('dialog-timeline');
    const journeyStage = document.getElementById('dialog-current-stage');
    const journeyFeedback = document.getElementById('dialog-feedback');
    const profileLink = document.getElementById('dialog-profile-link');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let qrScanner = null;
    let readerChanging = false;
    let cameraGeneration = 0;
    let cameraActive = false;
    let facingMode = 'environment';
    let busy = false;
    let pending = null;
    let journeyTimer = null;
    let journeyRequestInFlight = false;
    let journeyNotice = '';

    const setCameraStatus = (text, state = 'idle') => {
        if (status) status.textContent = text;
        if (indicator) {
            indicator.className = `h-2.5 w-2.5 rounded-full ${
                state === 'active' ? 'bg-emerald-400' : state === 'blocked' ? 'bg-red-400' : 'bg-slate-400'
            }`;
        }
    };

    const showMessage = (text, state = 'info') => {
        const styles = {
            info: 'border-blue-300 bg-blue-50 text-blue-900',
            success: 'border-emerald-300 bg-emerald-50 text-emerald-900',
            warning: 'border-amber-300 bg-amber-50 text-amber-950',
            error: 'border-red-300 bg-red-50 text-red-900',
        };
        message.textContent = text;
        message.className = `rounded-xl border p-4 text-sm ${styles[state] ?? styles.info}`;
    };

    const hideMessage = () => {
        message.classList.add('hidden');
        message.textContent = '';
    };

    const setPausedControls = () => {
        startButton?.classList.add('hidden');
        stopButton?.classList.add('hidden');
        resumeContainer?.classList.remove('hidden');
    };

    const setReadyControls = () => {
        startButton?.classList.remove('hidden');
        stopButton?.classList.add('hidden');
        resumeContainer?.classList.add('hidden');
    };

    const getEmissionId = () => selectEmission?.value || '';

    const setReaderChanging = (changing) => {
        readerChanging = changing;
        const unavailable = changing || !selectEmission || selectEmission.options.length <= 1;
        for (const button of [startButton, resumeButton, photoButton]) {
            if (button) button.disabled = unavailable;
        }
        if (switchButton) switchButton.disabled = changing || !cameraActive;
    };

    const releaseReader = () => {
        const scanner = qrScanner;
        qrScanner = null;
        cameraActive = false;
        if (scanner) {
            try {
                scanner.stop();
            } catch (_) {
                // Le flux est déjà arrêté.
            }
            try {
                scanner.destroy();
            } catch (_) {
                // Le scanner est déjà détruit.
            }
        }
        if (video) {
            video.srcObject = null;
        }
        if (switchButton) switchButton.disabled = true;
        placeholder?.classList.remove('hidden');
        setCameraStatus('Caméra inactive');
    };

    const stopScanner = () => {
        cameraGeneration++;
        return Promise.resolve().then(releaseReader);
    };

    /** Laisse le navigateur peindre l'état « vérification » avant la requête. */
    const nextPaint = () => new Promise((resolve) => {
        if (document.hidden) {
            resolve();
            return;
        }
        requestAnimationFrame(() => resolve());
    });

    const request = async (url, data) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(data),
        });
        const result = await response.json().catch(() => null);
        if (!result) {
            throw new Error('Réponse serveur illisible. Réessayez ou contactez un administrateur.');
        }
        return result;
    };

    const renderJourney = (parcours) => {
        journeyList.replaceChildren();
        journeyStage.textContent = parcours?.etape_courante_label
            ? `Prochaine étape · ${parcours.etape_courante_label}`
            : 'État indisponible';

        if (!Array.isArray(parcours?.etapes) || parcours.etapes.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'p-3 text-sm text-on-surface-variant';
            empty.textContent = 'Aucune étape de parcours disponible.';
            journeyList.append(empty);
            return;
        }

        parcours.etapes.forEach((etape) => {
            const item = document.createElement('li');
            item.className = 'flex items-center gap-3 px-3 py-2.5';

            const marker = document.createElement('span');
            marker.className = `flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs ${
                etape.fait ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-container-high text-on-surface-variant'
            }`;
            marker.setAttribute('aria-hidden', 'true');
            marker.innerHTML = etape.fait
                ? '<i class="fa-solid fa-check"></i>'
                : '<i class="fa-regular fa-circle"></i>';

            const details = document.createElement('div');
            details.className = 'min-w-0 flex-1';
            const label = document.createElement('p');
            label.className = `text-sm ${etape.fait ? 'font-semibold text-on-surface' : 'text-on-surface-variant'}`;
            label.textContent = etape.label || 'Étape';
            details.append(label);

            const time = document.createElement('time');
            time.className = 'shrink-0 text-xs tabular-nums text-on-surface-variant';
            if (etape.date) {
                time.dateTime = etape.date;
                time.textContent = etape.horodatage || new Intl.DateTimeFormat('fr-FR', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                }).format(new Date(etape.date));
            } else {
                time.textContent = 'À venir';
            }

            item.append(marker, details, time);
            journeyList.append(item);
        });
    };

    const refreshJourney = async () => {
        if (!pending?.guest?.parcours_id || journeyRequestInFlight) return;
        journeyRequestInFlight = true;
        try {
            const url = root.dataset.trackingUrlTemplate.replace(
                '__PARCOURS__',
                encodeURIComponent(String(pending.guest.parcours_id)),
            );
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.parcours) {
                throw new Error(result?.message || 'Le suivi du parcours est temporairement indisponible.');
            }
            renderJourney(result.parcours);
            journeyFeedback.textContent = journeyNotice;
        } catch (error) {
            journeyFeedback.textContent = error.message || 'Impossible d’actualiser le parcours.';
        } finally {
            journeyRequestInFlight = false;
        }
    };

    const startJourneyRefresh = () => {
        if (journeyTimer) window.clearInterval(journeyTimer);
        journeyTimer = window.setInterval(() => void refreshJourney(), 5000);
    };

    const resetDialog = () => {
        if (journeyTimer) window.clearInterval(journeyTimer);
        journeyTimer = null;
        journeyRequestInFlight = false;
        pending = null;
        busy = false;
        journeyNotice = '';
        confirmButton.hidden = false;
        confirmButton.disabled = false;
        cancelButton.disabled = false;
        cancelButton.textContent = 'Annuler';
        journeyFeedback.textContent = '';
        if (profileLink) { profileLink.hidden = true; profileLink.classList.add('hidden'); profileLink.removeAttribute('href'); }
        setCameraStatus('Scan suspendu');
        setPausedControls();
    };

    const addRecentAttempt = (result, guest, scannedAt, emissionName) => {
        const list = document.getElementById('recent-checkins');
        if (!list) return;
        const emptyState = list.querySelector('[data-empty-state]');
        emptyState?.remove();

        const statusLabels = {
            accepted: 'Entrée validée',
            already_recorded: 'Déjà enregistré',
            invalid: 'QR invalide',
            expired: 'QR expiré',
            cancelled: 'Annulé',
            pending: 'En attente',
        };
        const statusClasses = {
            accepted: 'bg-emerald-100 text-emerald-800',
            already_recorded: 'bg-amber-100 text-amber-900',
            invalid: 'bg-red-100 text-red-800',
            expired: 'bg-red-100 text-red-800',
            cancelled: 'bg-slate-100 text-slate-700',
            pending: 'bg-blue-100 text-blue-800',
        };
        const item = document.createElement('li');
        item.className = 'p-4';

        const top = document.createElement('div');
        top.className = 'flex items-start justify-between gap-3';
        const details = document.createElement('div');
        details.className = 'min-w-0';
        const name = document.createElement(guest?.fiche_url ? 'a' : 'p');
        name.className = 'truncate font-semibold text-on-surface';
        if (guest?.fiche_url) {
            name.href = guest.fiche_url;
            name.classList.add('block', 'text-primary', 'underline');
        }
        name.textContent = guest?.nom || 'QR non identifié';
        const emission = document.createElement('p');
        emission.className = 'mt-1 truncate text-sm text-on-surface-variant';
        emission.textContent = guest?.emission || emissionName || 'Émission indisponible';
        details.append(name, emission);

        const time = document.createElement('time');
        time.className = 'shrink-0 text-xs tabular-nums text-on-surface-variant';
        time.dateTime = scannedAt || '';
        time.textContent = scannedAt
            ? new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(scannedAt))
            : '—';
        top.append(details, time);

        const metadata = document.createElement('div');
        metadata.className = 'mt-2 flex flex-wrap items-center gap-2';
        const badge = document.createElement('span');
        badge.className = `rounded-full px-2.5 py-1 text-xs font-semibold ${statusClasses[result] ?? 'bg-slate-100 text-slate-700'}`;
        badge.textContent = statusLabels[result] ?? result;
        const operator = document.createElement('span');
        operator.className = 'text-xs text-on-surface-variant';
        operator.textContent = root.dataset.operatorName || 'Agent';
        metadata.append(badge, operator);

        item.append(top, metadata);
        list.prepend(item);
        list.querySelectorAll(':scope > li').forEach((entry, index) => {
            if (index >= 15) entry.remove();
        });
    };

    const updateCounts = (result) => {
        const scans = document.getElementById('today-scan-count');
        if (scans) scans.textContent = String(Number(scans.textContent) + 1);
        if (result === 'accepted') {
            const accepted = document.getElementById('today-accepted-count');
            if (accepted) accepted.textContent = String(Number(accepted.textContent) + 1);
        }
    };

    const submitLookup = async (code, scanMethod) => {
        if (busy) return;
        let emissionId = getEmissionId();
        if (!emissionId) {
            showMessage('Sélectionnez une émission avant de vérifier un QR.', 'warning');
            selectEmission?.focus();
            return;
        }

        // Verrouillage immédiat : la première détection arrête la caméra
        // avant toute requête, les détections suivantes sont ignorées.
        busy = true;
        cameraGeneration++;
        try {
            qrScanner?.stop();
        } catch (_) {
            // Le scanner est déjà arrêté.
        }
        await stopScanner();
        setPausedControls();
        setCameraStatus('QR détecté · vérification en cours');
        showMessage('Vérification de l’invitation…', 'info');
        await nextPaint();

        try {
            const lookup = () => request(root.dataset.lookupUrl, {
                code,
                emission_id: Number(emissionId),
                scan_method: scanMethod,
                intervenant_id: root.dataset.intervenantId ? Number(root.dataset.intervenantId) : null,
            });

            let result = await lookup();

            // Le QR connaît son émission : si la sélection ne correspond pas,
            // on l'ajuste automatiquement puis on rejoue la vérification.
            if (
                result?.result === 'invalid'
                && result?.suggested_emission_id
                && String(result.suggested_emission_id) !== String(emissionId)
                && selectEmission
            ) {
                const option = Array.from(selectEmission.options).find(
                    (item) => String(item.value) === String(result.suggested_emission_id),
                );
                if (option) {
                    selectEmission.value = String(result.suggested_emission_id);
                    emissionId = selectEmission.value;
                    showMessage(`Émission ajustée : ${option.textContent.trim()}. Nouvelle vérification…`, 'info');
                    result = await lookup();
                }
            }

            const emissionName = selectEmission?.selectedOptions[0]?.textContent?.trim() || '';
            const readOnly = result.result !== 'pending';
            if (result.result === 'pending' || (result.parcours && result.guest?.parcours_id)) {
                pending = {
                    attemptId: result.attempt_id,
                    token: readOnly ? null : code,
                    emissionId: Number(emissionId),
                    guest: result.guest,
                    scannedAt: result.scanned_at,
                    emissionName,
                    finalized: readOnly,
                };
                if (readOnly) {
                    addRecentAttempt(result.result, result.guest, result.scanned_at, emissionName);
                    updateCounts(result.result);
                    showMessage(
                        result.message || 'Le parcours de cet invité est affiché.',
                        result.result === 'already_recorded' ? 'warning' : 'error',
                    );
                    confirmButton.hidden = true;
                    cancelButton.textContent = 'Fermer';
                } else {
                    confirmButton.hidden = false;
                    cancelButton.textContent = 'Annuler';
                }
                document.getElementById('dialog-guest').textContent = result.guest?.nom || 'Invité';
                if (profileLink) {
                    profileLink.hidden = !result.guest?.fiche_url;
                    profileLink.classList.toggle('hidden', !result.guest?.fiche_url);
                    if (result.guest?.fiche_url) profileLink.href = result.guest.fiche_url;
                    else profileLink.removeAttribute('href');
                }
                document.getElementById('dialog-emission').textContent = result.guest?.emission || emissionName;
                document.getElementById('dialog-status').textContent = readOnly
                    ? (result.result === 'already_recorded' ? 'Déjà enregistré' : (result.guest?.statut_invitation || 'Statut indisponible'))
                    : (result.guest?.statut_invitation || 'Statut indisponible');
                const scheduled = result.guest?.heure_prevue
                    ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(result.guest.heure_prevue))
                    : 'Aucun horaire prévu';
                document.getElementById('dialog-time').textContent = scheduled;
                document.getElementById('dialog-scan-time').textContent = result.scanned_at
                    ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date(result.scanned_at))
                    : '—';
                renderJourney(result.parcours);
                journeyNotice = readOnly
                    ? result.message || 'Consultation du parcours en lecture seule.'
                    : 'Actualisation automatique pendant que cette fenêtre reste ouverte.';
                journeyFeedback.textContent = journeyNotice;
                hideMessage();
                dialog.showModal();
                startJourneyRefresh();
                return;
            }

            addRecentAttempt(result.result, result.guest, result.scanned_at, emissionName);
            updateCounts(result.result);
            showMessage(
                result.message || 'Le QR ne peut pas être validé.',
                result.result === 'already_recorded' ? 'warning' : 'error',
            );
            busy = false;
            setPausedControls();
            setCameraStatus('Scan suspendu');
        } catch (error) {
            showMessage(error.message || 'Impossible de vérifier le QR. Vérifiez la connexion puis réessayez.', 'error');
            busy = false;
            setPausedControls();
            setCameraStatus('Scan suspendu');
        }
    };

    const createScanner = () => new QrScanner(video, (result) => {
        const code = typeof result === 'string' ? result : result?.data;
        if (!code || busy || pending) return;
        void submitLookup(String(code), 'camera');
    }, {
        preferredCamera: facingMode,
        maxScansPerSecond: 8,
        highlightScanRegion: false,
        highlightCodeOutline: false,
        returnDetailedScanResult: true,
        onDecodeError: () => {},
    });

    const startCamera = async () => {
        if (busy || readerChanging) return;
        if (!getEmissionId()) {
            showMessage('Sélectionnez une émission avant de démarrer la caméra.', 'warning');
            selectEmission?.focus();
            return;
        }

        setReaderChanging(true);
        const generation = ++cameraGeneration;
        try {
            releaseReader();
            if (generation !== cameraGeneration || document.hidden) return;
            hideMessage();
            setCameraStatus('Ouverture de la caméra…');
            qrScanner = createScanner();
            await qrScanner.start();
            if (generation !== cameraGeneration || document.hidden) {
                releaseReader();
                return;
            }
            cameraActive = true;
            placeholder?.classList.add('hidden');
            startButton?.classList.add('hidden');
            resumeContainer?.classList.add('hidden');
            stopButton?.classList.remove('hidden');
            if (switchButton) switchButton.disabled = false;
            setCameraStatus('Caméra active · présentez un QR code', 'active');
        } catch (error) {
            releaseReader();
            const denied = /denied|notallowed|permission/i.test(String(error?.name || error?.message || error));
            const notFound = /notfound|not found|no camera|devices/i.test(String(error?.name || error?.message || error));
            setCameraStatus(denied ? 'Caméra bloquée' : 'Caméra indisponible', 'blocked');
            showMessage(
                denied
                    ? 'L’autorisation caméra a été refusée. Autorisez-la dans les réglages du navigateur ou utilisez une photo du QR code.'
                    : notFound
                        ? 'Aucune caméra détectée. Utilisez une photo du QR code ou la saisie manuelle.'
                        : `Impossible de démarrer la caméra : ${error?.message || error || 'périphérique indisponible.'}`,
                'error',
            );
            setReadyControls();
        } finally {
            setReaderChanging(false);
        }
    };

    const handlePhotoScan = async (file) => {
        if (!file || busy || readerChanging) return;
        if (!getEmissionId()) {
            showMessage('Sélectionnez une émission avant de vérifier un QR.', 'warning');
            selectEmission?.focus();
            return;
        }
        setReaderChanging(true);
        cameraGeneration++;
        releaseReader();
        try {
            setCameraStatus('Lecture de la photo…');
            const result = await QrScanner.scanImage(file, {
                returnDetailedScanResult: true,
                alsoTryWithoutScanRegion: true,
            });
            const code = result?.data;
            if (code) {
                busy = false;
                await submitLookup(String(code), 'photo');
            } else {
                showMessage('Aucun QR code détecté sur cette image.', 'error');
                setReadyControls();
            }
        } catch (_) {
            showMessage('Aucun QR code détecté sur cette image. Essayez une photo plus nette.', 'error');
            setReadyControls();
        } finally {
            setReaderChanging(false);
        }
    };

    const finishPending = async (cancel) => {
        if (!pending || pending.finalized) return;
        confirmButton.disabled = true;
        cancelButton.disabled = true;
        try {
            const result = cancel
                ? await request(root.dataset.cancelUrl, { attempt_id: pending.attemptId })
                : await request(root.dataset.confirmUrl, {
                    attempt_id: pending.attemptId,
                    token: pending.token,
                    emission_id: pending.emissionId,
                });
            addRecentAttempt(result.result, pending.guest, pending.scannedAt, pending.emissionName);
            updateCounts(result.result);
            if (result.parcours) renderJourney(result.parcours);

            if (cancel) {
                showMessage(result.message, 'info');
                dialog.close();
                return;
            }

            pending.finalized = true;
            confirmButton.hidden = true;
            cancelButton.textContent = 'Fermer';
            journeyNotice = result.result === 'accepted'
                ? `Arrivée validée à ${result.validated_at
                    ? new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(result.validated_at))
                    : 'l’instant'}. Le parcours continue de s’actualiser.`
                : result.message || 'La validation n’a pas été effectuée.';
            journeyFeedback.textContent = journeyNotice;
            showMessage(
                result.message,
                result.result === 'accepted' ? 'success' : result.result === 'already_recorded' ? 'warning' : 'error',
            );
            if (result.result !== 'accepted') {
                cancelButton.textContent = 'Fermer';
            }
        } catch (error) {
            showMessage(error.message || 'La demande a échoué. Réessayez sans quitter la confirmation.', 'error');
        } finally {
            confirmButton.disabled = false;
            cancelButton.disabled = false;
        }
    };

    startButton?.addEventListener('click', startCamera);
    stopButton?.addEventListener('click', async () => {
        await stopScanner();
        setReadyControls();
    });
    switchButton?.addEventListener('click', async () => {
        if (busy || readerChanging) return;
        facingMode = facingMode === 'environment' ? 'user' : 'environment';
        await startCamera();
    });
    resumeButton?.addEventListener('click', startCamera);
    manualForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (readerChanging) return;
        const code = manualInput?.value.trim();
        if (code) void submitLookup(code, 'manual');
    });
    photoButton?.addEventListener('click', () => photoInput?.click());
    photoInput?.addEventListener('change', async () => {
        const file = photoInput.files?.[0];
        photoInput.value = '';
        await handlePhotoScan(file);
    });
    confirmButton?.addEventListener('click', () => void finishPending(false));
    cancelButton?.addEventListener('click', () => {
        if (pending?.finalized) {
            dialog.close();
            return;
        }
        void finishPending(true);
    });
    dialog?.addEventListener('cancel', (event) => {
        if (pending?.finalized) return;
        event.preventDefault();
        void finishPending(true);
    });
    dialog?.addEventListener('close', resetDialog);
    selectEmission?.addEventListener('change', () => {
        if (selectEmission.value) hideMessage();
        if (qrScanner || readerChanging) {
            void stopScanner().then(setReadyControls);
        }
    });
    window.addEventListener('pagehide', () => {
        if (journeyTimer) window.clearInterval(journeyTimer);
        void stopScanner();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) void stopScanner().then(() => {
            if (!busy) setReadyControls();
        });
    });
}
