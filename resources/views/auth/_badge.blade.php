{{--
    Badge circulaire décoratif des pages d'authentification.
    Usage : @include('auth._badge', ['icon' => 'lock'])
--}}
<div class="flex justify-center mb-5">
    <div class="relative w-24 h-24 flex items-center justify-center">
        <div class="absolute inset-0 rounded-full border" style="border-color: var(--tv-line);"></div>
        <div class="absolute inset-2.5 rounded-full border opacity-80" style="border-color: var(--tv-line);"></div>

        <span class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1 w-1.5 h-1.5 rounded-full" style="background: var(--tv-blue);"></span>
        <span class="absolute bottom-0 left-1/2 -translate-x-1/2 translate-y-1 w-1.5 h-1.5 rounded-full" style="background: var(--tv-blue);"></span>
        <span class="absolute left-0 top-1/2 -translate-x-1 -translate-y-1/2 w-1.5 h-1.5 rounded-full" style="background: var(--tv-blue);"></span>
        <span class="absolute right-0 top-1/2 translate-x-1 -translate-y-1/2 w-1.5 h-1.5 rounded-full" style="background: var(--tv-blue);"></span>

        <div class="relative w-11 h-11 rounded-xl bg-white/80 border flex items-center justify-center z-10"
             style="border-color: var(--tv-line); color: var(--tv-navy-deep);">
            <span class="material-symbols-outlined filled text-[24px]">{{ $icon ?? 'lock' }}</span>
        </div>
    </div>
</div>
