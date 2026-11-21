@props(['label' => 'Tableau de données'])

<div role="region" tabindex="0" aria-label="{{ $label }}"
     {{ $attributes->merge(['class' => 'ui-table-frame focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary']) }}>
    {{ $slot }}
</div>
