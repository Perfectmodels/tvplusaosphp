@if(($allDates ?? false) && $conducteurs->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination de tous les conducteurs">
        <p class="text-sm text-on-surface-variant">
            Conducteurs {{ $conducteurs->firstItem() }}–{{ $conducteurs->lastItem() }}
            sur {{ number_format($conducteurs->total(), 0, ',', ' ') }}
        </p>
        <div class="flex flex-wrap items-center gap-2">
            @if($conducteurs->previousPageUrl())
                <a href="{{ $conducteurs->previousPageUrl() }}" rel="prev"
                   class="ui-action ui-action--outline">Précédents</a>
            @endif
            @for($overviewPage = max(1, $conducteurs->currentPage() - 2); $overviewPage <= min($conducteurs->lastPage(), $conducteurs->currentPage() + 2); $overviewPage++)
                <a href="{{ $conducteurs->url($overviewPage) }}"
                   @if($overviewPage === $conducteurs->currentPage()) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border px-3 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary',
                       'border-primary bg-primary text-white' => $overviewPage === $conducteurs->currentPage(),
                       'border-outline-variant bg-white text-primary' => $overviewPage !== $conducteurs->currentPage(),
                   ])>{{ $overviewPage }}</a>
            @endfor
            @if($conducteurs->nextPageUrl())
                <a href="{{ $conducteurs->nextPageUrl() }}" rel="next"
                   class="ui-action ui-action--outline">Suivants</a>
            @endif
        </div>
    </nav>
@endif
