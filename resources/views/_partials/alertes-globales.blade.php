@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\AlerteInfo> $alertes */
    $alertes = $alertesGlobalesActives ?? collect();
    $peutClore = auth()->user()?->hasAnyRole(['admin', 'superadmin']) ?? false;
@endphp

@foreach($alertes as $alerte)
    @php
        $niveau = $alerte->niveau;
    @endphp
    <article role="alert"
             data-alerte-id="{{ $alerte->id }}"
             class="tv-notif-bubble tv-notif-alerte tv-alerte-clignotante">
        <div class="flex items-start gap-2.5">
            <span class="tv-alerte-dot mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-red-700" aria-hidden="true"></span>
            <i class="{{ $niveau->icon() }} mt-0.5 shrink-0 text-red-700" aria-hidden="true"></i>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <strong class="text-sm font-bold leading-snug">{{ $alerte->titre }}</strong>
                    <span class="rounded-full bg-red-700/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-800">
                        {{ $niveau->label() }}
                    </span>
                </div>
                <p class="mt-1 whitespace-pre-line break-words text-[13px] leading-relaxed text-red-900/90">{{ $alerte->message }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if($alerte->expire_at)
                        <span class="text-[11px] font-medium text-red-800/80">Jusqu’à {{ $alerte->expire_at->format('d/m H:i') }}</span>
                    @endif
                    @if($peutClore)
                        <form method="POST" action="{{ route('alertes-info.destroy', $alerte) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex min-h-8 items-center gap-1.5 rounded-md border border-red-700/30 bg-white/70 px-2.5 py-1 text-[11px] font-semibold text-red-800 transition-colors hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
                                    aria-label="Retirer l’alerte {{ $alerte->titre }} pour tout le monde">
                                <i class="fa-solid fa-trash-can text-[10px]" aria-hidden="true"></i>
                                Retirer
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <button type="button"
                    data-alerte-hide
                    class="-mr-1 -mt-1 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-red-800/70 transition-colors hover:bg-red-700/10 hover:text-red-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
                    aria-label="Masquer cette alerte sur cette page">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </article>
@endforeach

@if($alertes->isNotEmpty())
    <script>
        (() => {
            const stack = document.getElementById('tv-notifications');
            if (!stack) return;

            stack.querySelectorAll('[data-alerte-id]').forEach((bubble) => {
                bubble.querySelectorAll('[data-alerte-hide]').forEach((button) => {
                    button.addEventListener('click', () => {
                        bubble.style.transition = 'opacity .18s ease, transform .18s ease';
                        bubble.style.opacity = '0';
                        bubble.style.transform = 'translateY(-6px)';
                        window.setTimeout(() => bubble.remove(), 180);
                    });
                });
            });
        })();
    </script>
@endif
