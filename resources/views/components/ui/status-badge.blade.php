@props(['variant' => 'neutral'])

@php
    $variants = [
        'neutral' => 'border-outline-variant bg-surface-container-low text-on-surface-variant',
        'info' => 'border-secondary/20 bg-secondary/10 text-secondary',
        'success' => 'border-success/20 bg-green-50 text-success',
        'warning' => 'border-warning/20 bg-amber-50 text-warning',
        'danger' => 'border-error/20 bg-error-container/60 text-on-error-container',
    ];
    $variantClass = $variants[$variant] ?? $variants['neutral'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold ' . $variantClass]) }}>
    {{ $slot }}
</span>
