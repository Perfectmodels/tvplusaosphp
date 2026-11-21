@props([
    'title',
    'description' => null,
    'icon' => 'fa-inbox',
])

<div {{ $attributes->merge(['class' => 'ui-empty-state']) }} role="status">
    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-secondary/10 text-secondary" aria-hidden="true">
        <i class="fa-solid {{ $icon }} text-xl"></i>
    </div>
    <div class="max-w-md space-y-1">
        <h3 class="text-base font-bold text-on-surface">{{ $title }}</h3>
        @if($description)
            <p class="text-sm leading-relaxed text-on-surface-variant">{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="mt-2 flex flex-wrap items-center justify-center gap-2">{{ $actions }}</div>
    @endif
    {{ $slot }}
</div>
