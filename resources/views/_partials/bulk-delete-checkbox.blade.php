@if(!($record instanceof \App\Models\User) || (int) $record->id !== (int) auth()->id())
@can($ability ?? 'delete', $record)
    <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 text-sm text-primary" data-bulk-choice>
        <input type="checkbox" data-bulk-item form="bulk-{{ $resource }}" value="{{ $record->id }}" class="h-5 w-5 rounded border-outline-variant text-primary" aria-label="Sélectionner {{ $label }}">
        <span class="sr-only">Sélectionner {{ $label }}</span>
    </label>
@endcan
@endif
