@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Sequence> $sequences */
    $sequenceBulle = null;
    if ((int) ($conducteur->active_sequence_id ?? 0) > 0) {
        $active = $sequences->firstWhere('id', (int) $conducteur->active_sequence_id);
        if ($active) {
            $sequenceBulle = [
                'id' => (int) $active->id,
                'position' => $active->position,
                'title' => $active->title,
                'duree' => $active->duree_hm,
                'running' => (bool) $conducteur->antenne_en_marche,
            ];
        }
    }

    $messagesPublicsUrl = null;
    if ((string) $conducteur->type === 'emission' && (string) ($conducteur->public_interaction_token ?? '') !== '') {
        $messagesPublicsUrl = route('conducteurs.emission.interactions.index', $conducteur);
    }
    $dernierMessagePublicId = (int) (($interactionMessages ?? collect())->max('id') ?? 0);
@endphp

<script>
    window.__tvBullesConducteur = {
        initial: @json($sequenceBulle),
        publicMessagesUrl: @json($messagesPublicsUrl),
        lastPublicMessageId: {{ $dernierMessagePublicId }},
        publicMessagesAnchor: 'tv-qr-public-card',
    };
</script>

<script>
    (() => {
        const stack = document.getElementById('tv-notifications');
        if (!stack) return;

        const cfg = window.__tvBullesConducteur || {};
        let sequenceBubble = null;

        const creer = (html) => {
            const template = document.createElement('template');
            template.innerHTML = html.trim();
            return template.content.firstElementChild;
        };

        /* ------------------------------------------------------------------
         * 1. Bulle « Message en cours » : suit exactement la séquence active
         *    du conducteur et du prompteur (événement tv-sequence-active).
         * ---------------------------------------------------------------- */
        const afficherSequence = (sequence) => {
            if (!sequence || !sequence.id) {
                if (sequenceBubble) {
                    sequenceBubble.remove();
                    sequenceBubble = null;
                }
                return;
            }

            if (!sequenceBubble) {
                sequenceBubble = creer(`
                    <article class="tv-notif-bubble tv-bulle-sequence" aria-label="Message en cours">
                        <div class="flex items-center gap-2 px-4 pt-3">
                            <span class="tv-bulle-live-dot" aria-hidden="true"></span>
                            <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-300">Message en cours</span>
                            <span data-duree class="ml-auto rounded bg-white/10 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-200"></span>
                        </div>
                        <p data-titre class="px-4 pb-1 pt-1 text-sm font-bold leading-snug text-white"></p>
                        <p data-meta class="px-4 pb-3 text-[11px] leading-snug text-slate-300"></p>
                    </article>
                `);
                stack.appendChild(sequenceBubble);
            }

            const enDirect = !!sequence.running;
            sequenceBubble.classList.toggle('is-live', enDirect);
            sequenceBubble.querySelector('[data-duree]').textContent = sequence.duree || '';
            sequenceBubble.querySelector('[data-titre]').textContent =
                `#${sequence.position ?? ''} · ${sequence.title || 'Sans titre'}`.replace(/^#· /, '#');
            sequenceBubble.querySelector('[data-meta]').textContent = enDirect
                ? 'À l’antenne · synchronisé avec le prompteur'
                : 'Séquence sélectionnée · en attente de diffusion';
        };

        window.addEventListener('tv-sequence-active', (event) => {
            afficherSequence(event.detail || null);
        });

        if (cfg.initial) {
            afficherSequence(cfg.initial);
        }

        /* ------------------------------------------------------------------
         * 2. Bulles « Message du public » : le dernier message reçu s'affiche
         *    en pop-up, puis s'efface seul. Le panneau QR reste la référence.
         * ---------------------------------------------------------------- */
        if (!cfg.publicMessagesUrl) return;

        let dernierId = Number(cfg.lastPublicMessageId || 0);
        let messagePublicBubble = null;
        let minuteur = null;

        const retirerMessagePublic = () => {
            if (minuteur) {
                window.clearTimeout(minuteur);
                minuteur = null;
            }
            if (messagePublicBubble) {
                messagePublicBubble.remove();
                messagePublicBubble = null;
            }
        };

        const afficherMessagePublic = (message, nombre) => {
            retirerMessagePublic();

            messagePublicBubble = creer(`
                <article class="tv-notif-bubble tv-bulle-public" role="status" aria-label="Nouveau message du public">
                    <div class="flex items-start gap-2.5 px-4 pb-3 pt-3">
                        <i class="fa-solid fa-comment-dots mt-0.5 shrink-0 text-blue-600" aria-hidden="true"></i>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-blue-700">Message du public</span>
                                <span data-nombre class="rounded-full bg-blue-600/10 px-1.5 py-0.5 text-[10px] font-bold text-blue-700"></span>
                            </div>
                            <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-xs">
                                <strong data-auteur class="font-bold text-on-surface"></strong>
                                <time data-heure class="text-on-surface-variant"></time>
                            </p>
                            <p data-texte class="mt-1 whitespace-pre-line break-words text-[13px] leading-relaxed text-on-surface-variant"></p>
                            <button type="button" data-voir
                                    class="mt-2 inline-flex min-h-8 items-center gap-1.5 rounded-md bg-blue-600 px-2.5 py-1 text-[11px] font-semibold text-white transition-colors hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                <i class="fa-solid fa-arrow-down text-[10px]" aria-hidden="true"></i>
                                Voir les messages
                            </button>
                        </div>
                        <button type="button" data-fermer
                                class="-mr-1 -mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-on-surface-variant transition-colors hover:bg-surface-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                aria-label="Fermer le message du public">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            `);

            const nombreLabel = Number(nombre) > 1 ? `+${nombre}` : '';
            messagePublicBubble.querySelector('[data-nombre]').textContent = nombreLabel;
            messagePublicBubble.querySelector('[data-nombre]').hidden = nombreLabel === '';
            messagePublicBubble.querySelector('[data-auteur]').textContent = message.name || 'Téléspectateur';
            messagePublicBubble.querySelector('[data-heure]').textContent = message.created_at_label || '';
            messagePublicBubble.querySelector('[data-texte]').textContent = message.message || '';

            messagePublicBubble.querySelector('[data-fermer]').addEventListener('click', retirerMessagePublic);
            messagePublicBubble.querySelector('[data-voir]').addEventListener('click', () => {
                document.getElementById(cfg.publicMessagesAnchor)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                retirerMessagePublic();
            });

            messagePublicBubble.addEventListener('mouseenter', () => {
                if (minuteur) {
                    window.clearTimeout(minuteur);
                    minuteur = null;
                }
            });
            messagePublicBubble.addEventListener('mouseleave', () => {
                minuteur = window.setTimeout(retirerMessagePublic, 6000);
            });

            stack.appendChild(messagePublicBubble);
            minuteur = window.setTimeout(retirerMessagePublic, 22000);

            window.dispatchEvent(new CustomEvent('tv-public-message', { detail: message }));
        };

        const actualiserMessagesPublics = async () => {
            if (document.visibilityState !== 'visible') return;

            try {
                const response = await fetch(cfg.publicMessagesUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!response.ok) return;

                const data = await response.json();
                const messages = Array.isArray(data.messages) ? data.messages : [];
                if (messages.length === 0) return;

                const maximum = messages.reduce((max, message) => Math.max(max, Number(message.id) || 0), 0);
                if (dernierId === 0) {
                    dernierId = maximum;
                    return;
                }

                const nouveaux = messages
                    .filter((message) => Number(message.id) > dernierId)
                    .sort((a, b) => Number(a.id) - Number(b.id));

                if (nouveaux.length > 0) {
                    dernierId = Number(nouveaux[nouveaux.length - 1].id);
                    afficherMessagePublic(nouveaux[nouveaux.length - 1], nouveaux.length);
                } else if (maximum > dernierId) {
                    dernierId = maximum;
                }
            } catch (error) {
                // Silencieux : la bulle réessaiera au prochain cycle.
            }
        };

        window.setInterval(actualiserMessagesPublics, 10000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') actualiserMessagesPublics();
        });
    })();
</script>
