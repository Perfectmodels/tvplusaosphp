@props(['messages' => null])

@if ($messages)
    <ul role="alert" aria-live="polite" {{ $attributes->merge(['class' => 'mt-1 space-y-1 text-sm text-error']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
