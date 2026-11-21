@props(['active' => false])

@php
    $classes = ($active ?? false)
        ? 'inline-flex min-h-11 items-center border-b-2 border-secondary px-2 text-sm font-semibold text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary'
        : 'inline-flex min-h-11 items-center border-b-2 border-transparent px-2 text-sm font-medium text-on-surface-variant transition-colors hover:border-outline hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary';
@endphp

<a @if($active) aria-current="page" @endif {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
