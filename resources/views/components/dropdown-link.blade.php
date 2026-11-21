<a role="menuitem" {{ $attributes->merge([
    'class' => 'block w-full px-4 py-2.5 text-start text-sm font-medium leading-5 text-on-surface transition-colors hover:bg-surface-container-low focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-secondary',
]) }}>{{ $slot }}</a>
