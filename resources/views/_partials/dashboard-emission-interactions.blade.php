@php
    $interactionDashboard = $publicInteractionMessages ?? ['authorized' => false, 'count' => 0, 'messages' => collect()];
    $interactionMessages = $interactionDashboard['messages'] ?? collect();
@endphp

@if($interactionDashboard['authorized'] ?? false)
    <section class="ed-card mb-3"
             data-dashboard-interaction-messages
             data-endpoint="{{ route('dashboard.emission-interactions') }}"
             aria-labelledby="dashboard-interactions-title">
        <div class="ed-card-heading">
            <div>
                <h2 id="dashboard-interactions-title">Messages du public</h2>
                <p class="mt-1 text-xs text-on-surface-variant">Questions reçues via les QR codes des émissions.</p>
            </div>
            <span data-dashboard-message-count class="ed-card-count">{{ $interactionDashboard['count'] }}</span>
        </div>

        <div data-dashboard-message-list class="space-y-1">
            @foreach($interactionMessages as $interactionMessage)
                <a data-dashboard-message
                   href="{{ route('conducteurs.emission.show', $interactionMessage->conducteur) }}"
                   class="flex items-start gap-3 rounded-lg px-3 py-2.5 text-on-surface transition-colors hover:bg-surface-container-low focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <i class="fa-solid fa-comment-dots mt-1 text-primary" aria-hidden="true"></i>
                    <span class="min-w-0 flex-1">
                        <span class="block break-words text-sm font-medium">{{ $interactionMessage->message }}</span>
                        <span class="mt-1 block text-xs text-on-surface-variant">
                            {{ $interactionMessage->nom ?: 'Téléspectateur' }}
                            @if($interactionMessage->destinataire) · pour {{ $interactionMessage->destinataire }}@endif
                            · {{ $interactionMessage->conducteur->emission?->nom ?? 'Émission' }}
                        </span>
                    </span>
                    <time class="shrink-0 text-xs text-on-surface-variant"
                          datetime="{{ $interactionMessage->created_at->toIso8601String() }}">
                        {{ $interactionMessage->created_at->format('d/m H:i') }}
                    </time>
                </a>
            @endforeach
        </div>

        <p data-dashboard-message-empty
           @class([
               'rounded-lg bg-surface-container-low px-3 py-4 text-sm text-on-surface-variant',
               'hidden' => $interactionMessages->isNotEmpty(),
           ])>
            Aucun message reçu pour le moment.
        </p>
        <p data-dashboard-message-status class="sr-only" aria-live="polite"></p>
    </section>

    @push('scripts')
        <script>
            (() => {
                const panel = document.querySelector('[data-dashboard-interaction-messages]');
                if (!panel) return;

                const list = panel.querySelector('[data-dashboard-message-list]');
                const emptyState = panel.querySelector('[data-dashboard-message-empty]');
                const count = panel.querySelector('[data-dashboard-message-count]');
                const status = panel.querySelector('[data-dashboard-message-status]');

                function renderMessages(messages) {
                    list.replaceChildren();

                    messages.forEach((message) => {
                        const link = document.createElement('a');
                        link.href = message.url;
                        link.className = 'flex items-start gap-3 rounded-lg px-3 py-2.5 text-on-surface transition-colors hover:bg-surface-container-low focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

                        const icon = document.createElement('i');
                        icon.className = 'fa-solid fa-comment-dots mt-1 text-primary';
                        icon.setAttribute('aria-hidden', 'true');

                        const body = document.createElement('span');
                        body.className = 'min-w-0 flex-1';

                        const text = document.createElement('span');
                        text.className = 'block break-words text-sm font-medium';
                        text.textContent = message.message;

                        const meta = document.createElement('span');
                        meta.className = 'mt-1 block text-xs text-on-surface-variant';
                        meta.textContent = `${message.name || 'Téléspectateur'}${message.recipient ? ` · pour ${message.recipient}` : ''} · ${message.emission}`;

                        const time = document.createElement('time');
                        time.className = 'shrink-0 text-xs text-on-surface-variant';
                        time.dateTime = message.created_at || '';
                        time.textContent = message.created_at_label || '';

                        body.append(text, meta);
                        link.append(icon, body, time);
                        list.append(link);
                    });

                    list.hidden = messages.length === 0;
                    emptyState.hidden = messages.length > 0;
                }

                async function refreshMessages() {
                    if (document.visibilityState === 'visible') {
                        try {
                            const response = await fetch(panel.dataset.endpoint, {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            });
                            if (!response.ok) {
                                throw new Error(`Actualisation impossible (${response.status}).`);
                            }

                            const data = await response.json();
                            count.textContent = String(data.count);
                            renderMessages(data.messages);
                            status.textContent = 'Les messages du public sont actualisés.';
                        } catch (error) {
                            console.error('Erreur d’actualisation des messages du public :', error);
                            status.textContent = 'Les messages du public n’ont pas pu être actualisés.';
                        }
                    }

                    window.setTimeout(refreshMessages, 10000);
                }

                window.setTimeout(refreshMessages, 10000);
            })();
        </script>
    @endpush
@endif
