@props(['active' => false])

@php
    $classes = ($active ?? false)
        ? 'block w-full border-l-4 border-secondary bg-secondary/10 px-4 py-3 text-start text-base font-semibold text-primary focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-secondary'
        : 'block w-full border-l-4 border-transparent px-4 py-3 text-start text-base font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-secondary';
@endphp

<a @if($active) aria-current="page" @endif {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
