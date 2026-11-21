<section class="mb-5 rounded-2xl border border-outline-variant bg-white p-4 shadow-sm sm:p-5"
         aria-label="Navigation des conducteurs et filtres">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-wider text-secondary">
                {{ ($allDates ?? false) ? 'Vue générale des conducteurs' : 'Organisation par date' }}
            </p>
            <h2 class="mt-1 text-lg font-bold text-primary">
                @if($allDates ?? false)
                    Tous les conducteurs
                @else
                    {{ $sansDateEdition ? 'Sans date d’édition — À classer' : ($dateEdition ? 'Édition du '.$dateEdition->translatedFormat('l j F Y') : 'Dates d’édition') }}
                @endif
            </h2>
            <p class="mt-1 text-sm text-on-surface-variant">
                @if($allDates ?? false)
                    {{ number_format($conducteurs->total(), 0, ',', ' ') }} résultat(s), toutes dates confondues · 12 par page
                @else
                    {{ $conducteurs->count() }} conducteur(s) · une page par date d’édition
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($allDates ?? false)
                <a href="{{ request()->url() }}?{{ http_build_query($editionFilters ?? []) }}"
                   class="ui-action ui-action--outline">
                    <i class="fa-solid fa-calendar-day" aria-hidden="true"></i> Par date
                </a>
            @else
                <a href="{{ request()->url() }}?{{ http_build_query(array_merge($editionFilters ?? [], ['mode' => 'all'])) }}"
                   class="ui-action ui-action--primary">
                    <i class="fa-solid fa-list" aria-hidden="true"></i> Tous les conducteurs
                </a>
            @endif
        </div>
    </div>

    @if($allDates ?? false)
        <form method="GET" action="{{ request()->url() }}"
              class="mt-5 grid grid-cols-1 gap-3 border-t border-outline-variant/60 pt-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(12rem,16rem)_auto]">
            @foreach(($editionFilters ?? []) as $filterName => $filterValue)
                <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
            @endforeach
            <input type="hidden" name="mode" value="all">
            <div>
                <label for="conducteurs-query" class="mb-1 block text-xs font-semibold text-on-surface-variant">
                    Rechercher un conducteur ou une émission
                </label>
                <input id="conducteurs-query" name="q" type="search" maxlength="100"
                       value="{{ $search ?? '' }}" placeholder="Nom du conducteur, émission, identifiant…"
                       class="ui-field" autocomplete="off">
            </div>
            <div>
                <label for="conducteurs-status" class="mb-1 block text-xs font-semibold text-on-surface-variant">Statut</label>
                <select id="conducteurs-status" name="status" class="ui-field">
                    <option value="">Tous les statuts</option>
                    @foreach(\App\Enums\StatutConducteurEnum::cases() as $statut)
                        <option value="{{ $statut->value }}" @selected(($status ?? '') === $statut->value)>{{ $statut->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <button type="submit" class="ui-action ui-action--primary">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Filtrer
                </button>
                <a href="{{ request()->url() }}?{{ http_build_query(array_merge($editionFilters ?? [], ['mode' => 'all'])) }}"
                   class="ui-action ui-action--outline" aria-label="Effacer les filtres">Réinitialiser</a>
            </div>
        </form>
    @else
        <details id="edition-filter-panel" class="group mt-4 border-t border-outline-variant/60 pt-3" {{ request()->filled('date') ? 'open' : '' }}>
            <summary id="edition-filter-toggle"
                     class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-2 text-sm font-semibold text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary">
                <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                Choisir une autre date
                <i class="fa-solid fa-chevron-down text-xs transition-transform group-open:rotate-180" aria-hidden="true"></i>
            </summary>
            <form id="edition-filter-form" action="{{ request()->url() }}" method="GET"
                  class="mt-3 flex flex-wrap items-end gap-3">
                @foreach(($editionFilters ?? []) as $filterName => $filterValue)
                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                @endforeach
                <div>
                    <label for="edition-filter-date" class="mb-1 block text-xs font-semibold text-on-surface-variant">Date d’édition</label>
                    <input id="edition-filter-date" type="date" name="date"
                           value="{{ $dateEdition?->toDateString() ?? today()->toDateString() }}"
                           required class="ui-field min-w-[190px]">
                </div>
                <button type="submit" class="ui-action ui-action--primary">Afficher cette date</button>
                <a href="{{ request()->url() }}?{{ http_build_query(array_merge($editionFilters ?? [], ['date' => today()->toDateString()])) }}"
                   class="ui-action ui-action--outline">Aujourd’hui</a>
            </form>
        </details>
    @endif
</section>
