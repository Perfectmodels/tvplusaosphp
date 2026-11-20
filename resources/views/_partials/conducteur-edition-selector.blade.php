<section class="mb-5 rounded-xl border border-outline-variant bg-white p-4 sm:p-5" aria-label="Choisir une date d’édition">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-primary">{{ $sansDateEdition ? 'Sans date d’édition — À classer' : ($dateEdition ? 'Édition du '.$dateEdition->translatedFormat('l j F Y') : 'Dates d’édition') }}</h2>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $conducteurs->count() }} conducteur(s) · une page par date d’édition</p>
        </div>
        <form method="GET" action="{{ request()->url() }}" class="flex flex-wrap items-end gap-3">
            @foreach(($editionFilters ?? []) as $filterName => $filterValue)<input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">@endforeach
            <div><label for="edition-date" class="mb-1 block text-xs font-semibold text-on-surface-variant">Date d’édition</label><input id="edition-date" type="date" name="date" value="{{ $dateEdition?->toDateString() ?? today()->toDateString() }}" required class="min-h-[44px] rounded-lg border border-outline-variant px-3 text-sm"></div>
            <button type="submit" class="min-h-[44px] rounded-lg bg-primary px-4 text-sm font-semibold text-white">Afficher cette date</button>
            <a href="{{ request()->url() }}?{{ http_build_query(array_merge($editionFilters ?? [], ['date' => today()->toDateString()])) }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-outline-variant px-4 text-sm font-semibold text-primary">Aujourd’hui</a>
        </form>
    </div>
</section>
