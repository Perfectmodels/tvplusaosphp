<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-outline-variant bg-white px-4 py-2.5 text-sm font-semibold normal-case text-on-surface transition-colors hover:bg-surface-container-low focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary disabled:cursor-not-allowed disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>
