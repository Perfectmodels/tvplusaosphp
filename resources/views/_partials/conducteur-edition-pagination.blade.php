@if($editions->total() > 0)
    <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination des dates d’édition">
        <p class="text-sm text-on-surface-variant">Page {{ $editions->currentPage() }} sur {{ $editions->lastPage() }} dates d’édition</p>
        <div class="flex flex-wrap items-center gap-2">
            @if($editions->previousPageUrl())<a rel="prev" href="{{ $editions->previousPageUrl() }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-outline-variant bg-white px-3 text-sm">← Date plus récente</a>@endif
            @for($editionPage = max(1, $editions->currentPage() - 2); $editionPage <= min($editions->lastPage(), $editions->currentPage() + 2); $editionPage++)
                <a href="{{ $editions->url($editionPage) }}" @if($editionPage === $editions->currentPage()) aria-current="page" @endif @class(['inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border px-3 text-sm', 'bg-primary text-white border-primary' => $editionPage === $editions->currentPage(), 'bg-white text-primary border-outline-variant' => $editionPage !== $editions->currentPage()])>{{ $editionPage }}</a>
            @endfor
            @if($editions->nextPageUrl())<a rel="next" href="{{ $editions->nextPageUrl() }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-outline-variant bg-white px-3 text-sm">Date plus ancienne →</a>@endif
        </div>
    </nav>
@endif
