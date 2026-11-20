@auth
    <script>
        window.__tvNotificationsGlobal = {
            etatUrl: @json(route('notifications.etat')),
        };
    </script>

    <script>
        (() => {
            const stack = document.getElementById('tv-notifications');
            if (!stack || !window.__tvNotificationsGlobal) return;

            const cfg = window.__tvNotificationsGlobal;
            let sequenceBubble = null;
            let dernierIdentifiant = null;
            let messagePublicBubble = null;
            let minuteurPublic = null;

            const creer = (html) => {
                const template = document.createElement('template');
                template.innerHTML = html.trim();
                return template.content.firstElementChild;
            };

            const retirerMessagePublic = () => {
                if (minuteurPublic) {
                    window.clearTimeout(minuteurPublic);
                    minuteurPublic = null;
                }
                if (messagePublicBubble) {
                    messagePublicBubble.remove();
                    messagePublicBubble = null;
                }
            };

            const afficherSequence = (live) => {
                if (!live || !live.sequence || !live.sequence.id) {
                    if (sequenceBubble) {
                        sequenceBubble.remove();
                        sequenceBubble = null;
                    }
                    return;
                }

                if (!sequenceBubble) {
                    sequenceBubble = creer(`
                        <a class="tv-notif-bubble tv-bulle-sequence is-live block no-underline"
                           href="#" aria-label="Message en cours">
                            <div class="flex items-center gap-2 px-4 pt-3">
                                <span class="tv-bulle-live-dot" aria-hidden="true"></span>
                                <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-300">Message en cours</span>
                                <span data-duree class="ml-auto rounded bg-white/10 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-200"></span>
                            </div>
                            <p data-emission class="px-4 pt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-sky-300"></p>
                            <p data-titre class="px-4 pb-1 text-sm font-bold leading-snug text-white"></p>
                            <p data-meta class="px-4 pb-3 text-[11px] leading-snug text-slate-300">À l’antenne · cliquez pour ouvrir le conducteur</p>
                        </a>
                    `);
                    stack.appendChild(sequenceBubble);
                }

                sequenceBubble.href = live.url || '#';
                const enDirect = live.running !== false;
                sequenceBubble.classList.toggle('is-live', enDirect);
                sequenceBubble.querySelector('[data-duree]').textContent = live.sequence.duree || '';
                sequenceBubble.querySelector('[data-emission]').textContent = live.emission || '';
                sequenceBubble.querySelector('[data-titre]').textContent =
                    `#${live.sequence.position ?? ''} · ${live.sequence.title || 'Sans titre'}`.replace(/^#· /, '#');
                sequenceBubble.querySelector('[data-meta]').textContent = enDirect
                    ? 'À l’antenne · cliquez pour ouvrir le conducteur'
                    : 'En pause · cliquez pour ouvrir le conducteur';
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
                                    <span data-emission class="min-w-0 truncate text-[10px] font-semibold text-blue-700/80"></span>
                                </div>
                                <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-xs">
                                    <strong data-auteur class="font-bold text-on-surface"></strong>
                                    <time data-heure class="text-on-surface-variant"></time>
                                </p>
                                <p data-texte class="mt-1 whitespace-pre-line break-words text-[13px] leading-relaxed text-on-surface-variant"></p>
                                <button type="button" data-voir
                                        class="mt-2 inline-flex min-h-8 items-center gap-1.5 rounded-md bg-blue-600 px-2.5 py-1 text-[11px] font-semibold text-white transition-colors hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                    <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
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
                messagePublicBubble.querySelector('[data-emission]').textContent = message.emission || '';
                messagePublicBubble.querySelector('[data-emission]').hidden = !message.emission;
                messagePublicBubble.querySelector('[data-auteur]').textContent = message.name || 'Téléspectateur';
                messagePublicBubble.querySelector('[data-heure]').textContent = message.created_at_label || '';
                messagePublicBubble.querySelector('[data-texte]').textContent = message.message || '';
                messagePublicBubble.querySelector('[data-fermer]').addEventListener('click', retirerMessagePublic);

                messagePublicBubble.querySelector('[data-voir]').addEventListener('click', () => {
                    if (message.url) {
                        window.location.href = message.url;
                        return;
                    }
                    retirerMessagePublic();
                });

                messagePublicBubble.addEventListener('mouseenter', () => {
                    if (minuteurPublic) {
                        window.clearTimeout(minuteurPublic);
                        minuteurPublic = null;
                    }
                });
                messagePublicBubble.addEventListener('mouseleave', () => {
                    minuteurPublic = window.setTimeout(retirerMessagePublic, 6000);
                });

                stack.appendChild(messagePublicBubble);
                minuteurPublic = window.setTimeout(retirerMessagePublic, 22000);
            };

            const actualiser = async () => {
                if (document.visibilityState !== 'visible') return;

                try {
                    const url = new URL(cfg.etatUrl, window.location.origin);
                    if (dernierIdentifiant !== null) {
                        url.searchParams.set('after', String(dernierIdentifiant));
                    }

                    const response = await fetch(url.toString(), {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;

                    const data = await response.json();
                    afficherSequence(data.live);

                    const publicData = data.public || {};
                    const messages = Array.isArray(publicData.messages) ? publicData.messages : [];

                    if (dernierIdentifiant === null) {
                        dernierIdentifiant = Number(publicData.last_id || 0);
                    } else if (messages.length > 0) {
                        afficherMessagePublic(messages[messages.length - 1], messages.length);
                        dernierIdentifiant = Math.max(
                            dernierIdentifiant,
                            Number(messages[messages.length - 1].id) || 0
                        );
                    }

                    dernierIdentifiant = Math.max(dernierIdentifiant, Number(publicData.last_id) || 0);
                } catch (error) {
                    // Silencieux : nouvel essai au prochain cycle.
                }
            };

            const demarrer = () => {
                // La vue conducteur possède déjà ses propres bulles locales :
                // on évite toute duplication sur cette page.
                if (window.__tvBullesConducteur) return;

                actualiser();
                window.setInterval(actualiser, 15000);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') actualiser();
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', demarrer, { once: true });
            } else {
                demarrer();
            }
        })();
    </script>
@endauth
