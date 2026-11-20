@php
    $bulkFormId = 'bulk-'.$resource;
    $bulkAction = $actionLabel ?? ($resource === 'sources-veille' ? 'Désactiver' : ($resource === 'sujets-veille' ? 'Archiver' : 'Supprimer'));
@endphp
<form id="{{ $bulkFormId }}" action="{{ route('bulk.destroy', $resource) }}" method="POST" data-bulk-delete-form data-bulk-action="{{ $bulkAction }}"
      data-confirm-delete="{{ $bulkAction }} la sélection ?" data-confirm-delete-label="{{ $bulkAction }}"
      data-confirm-delete-text="{{ $explanation ?? 'Seuls les éléments cochés sur cette page seront supprimés. Vérifiez votre sélection avant de confirmer.' }}">
    @csrf
    @isset($parentId)<input type="hidden" name="parent_id" value="{{ $parentId }}">@endisset
    <div data-bulk-ids></div>
    <div data-bulk-bar hidden class="bulk-selection-bar mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-error/20 bg-error/5 px-4 py-3">
        <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-3 text-sm font-medium text-primary">
            <input type="checkbox" data-bulk-all class="h-5 w-5 rounded border-outline-variant text-primary">
            Tout sélectionner sur cette page
        </label>
        <div class="flex flex-wrap items-center gap-3">
            <span data-bulk-count class="text-sm text-on-surface-variant" role="status" aria-live="polite">0 sélectionné</span>
            <button type="button" data-bulk-clear class="min-h-[44px] rounded-lg px-3 text-sm font-semibold text-primary" disabled>Annuler la sélection</button>
            <button type="submit" data-bulk-submit disabled class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-error-container px-4 text-sm font-semibold text-error disabled:cursor-not-allowed disabled:opacity-40">
                <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ $resource === 'sources-veille' ? 'pause_circle' : ($resource === 'sujets-veille' ? 'archive' : 'delete') }}</span>
                {{ $bulkAction }} la sélection
            </button>
        </div>
    </div>
</form>
