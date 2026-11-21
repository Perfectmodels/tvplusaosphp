@props(['disabled' => false, 'invalid' => false])

<input
    @disabled($disabled)
    @if($invalid) aria-invalid="true" @endif
    {{ $attributes->merge([
        'class' => 'w-full min-h-11 rounded-lg border border-outline-variant bg-white px-3.5 py-2.5 text-base text-on-surface shadow-sm transition-colors placeholder:text-on-surface-variant focus:border-secondary focus:ring-2 focus:ring-secondary/20 disabled:cursor-not-allowed disabled:bg-surface-container disabled:opacity-70',
    ]) }}
>
