<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-transparent bg-error px-4 py-2.5 text-sm font-semibold normal-case text-on-error transition-colors hover:bg-on-error-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-error disabled:cursor-not-allowed disabled:opacity-50',
]) }}>
    {{ $slot }}
</button>
