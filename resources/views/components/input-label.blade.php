@props(['value' => null, 'required' => false])

<label {{ $attributes->merge(['class' => 'mb-1.5 block text-sm font-semibold text-on-surface-variant']) }}>
    {{ $value ?? $slot }}
    @if($required)
        <span aria-hidden="true" class="ml-0.5 text-error">*</span>
        <span class="sr-only">(obligatoire)</span>
    @endif
</label>
