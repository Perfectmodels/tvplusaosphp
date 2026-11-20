@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\EmissionInteractionMessage> $interactionMessages */
    $token = (string) ($conducteur->public_interaction_token ?? '');
    $urlPublic = $token !== '' ? route('emission-interactions.show', $token) : null;
@endphp

<section id="tv-qr-public-card" class="h-full rounded-xl border border-outline/15 bg-white p-5 shadow-sm" aria-label="QR code et messages du public">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-on-surface">
            <i class="fa-solid fa-qrcode text-primary" aria-hidden="true"></i>
            QR code public
        </h3>
        <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">
            {{ $interactionMessagesCount }} message(s)
        </span>
    </div>

    @can('updateEnTete', $conducteur)
        @if($token !== '')
            <div class="flex flex-wrap items-start gap-4">
                <img src="{{ route('conducteurs.emission.interactions.qr', $conducteur) }}"
                     alt="QR code public de l’émission"
                     class="h-32 w-32 shrink-0 rounded-lg border border-outline/20 bg-white p-2">
                <div class="min-w-0 flex-1 space-y-2">
                    <a href="{{ $urlPublic }}" target="_blank" rel="noopener"
                       class="block break-all text-xs font-medium text-primary underline underline-offset-2">Ouvrir la page du public</a>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('conducteurs.emission.interactions.qr.download', $conducteur) }}"
                           class="inline-flex min-h-9 items-center gap-2 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-container hover:text-primary">
                            <i class="fa-solid fa-download" aria-hidden="true"></i> Télécharger
                        </a>
                        <form method="POST" action="{{ route('conducteurs.emission.interactions.generate-qr', $conducteur) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-outline/30 px-3 py-1.5 text-xs font-semibold text-on-surface hover:bg-surface-container">
                                <i class="fa-solid fa-rotate" aria-hidden="true"></i> Régénérer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('conducteurs.emission.interactions.generate-qr', $conducteur) }}">
                @csrf
                <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-container hover:text-primary">
                    <i class="fa-solid fa-qrcode" aria-hidden="true"></i> Générer le QR public
                </button>
            </form>
        @endif
    @endif

    <div class="mt-4 border-t border-outline/10 pt-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Derniers messages</p>
        @forelse($interactionMessages as $message)
            <div class="border-b border-outline/10 py-2 last:border-0">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2 text-xs">
                    <span class="font-semibold text-on-surface">{{ $message->nom ?: 'Téléspectateur' }}</span>
                    <time class="text-on-surface-variant">{{ $message->created_at->format('d/m H:i') }}</time>
                </div>
                <p class="mt-0.5 line-clamp-2 text-sm text-on-surface-variant">{{ $message->message }}</p>
            </div>
        @empty
            <p class="rounded-lg bg-surface-container-low px-3 py-2 text-xs text-on-surface-variant">Aucun message pour le moment.</p>
        @endforelse
    </div>
</section>
