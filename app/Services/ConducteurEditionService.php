<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conducteur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ConducteurEditionService
{
    /**
     * @param  Builder<Conducteur>  $query
     * @param  array<string, int|string>  $editionFilters
     * @return array<string, mixed>
     */
    public function index(Builder $query, Request $request, array $editionFilters = []): array
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $requestedDate = $validated['date'] ?? null;
        $page = (int) ($validated['page'] ?? 1);

        if ($requestedDate !== null || ! $request->has('page')) {
            $target = $requestedDate
                ?? (clone $query)->whereDate('date_edition', '<=', today()->toDateString())->max('date_edition')
                ?? (clone $query)->min('date_edition');

            if ($target !== null) {
                $page = (clone $query)->whereDate('date_edition', '>', $target)->distinct()->count('date_edition') + 1;
            }
        }

        $hasUndated = (clone $query)->whereNull('date_edition')->exists();
        $totalDates = (clone $query)->distinct()->count('date_edition') + (int) $hasUndated;
        $page = min($page, max(1, $totalDates));
        $dates = (clone $query)->reorder()
            ->select('date_edition')
            ->distinct()
            ->orderByDesc('date_edition')
            ->skip($page - 1)->take(1)->get();
        $editions = new LengthAwarePaginator($dates, $totalDates, 1, $page, ['path' => $request->url(), 'query' => $editionFilters]);

        $selected = $requestedDate ?? $editions->first()?->date_edition;
        $dateEdition = $selected !== null ? Carbon::parse($selected)->startOfDay() : null;
        $sansDateEdition = $requestedDate === null && $editions->first() !== null && $selected === null;
        $conducteurs = (clone $query)->reorder()
            ->with(['emission', 'presentateur', 'sequences'])
            ->when($dateEdition, fn (Builder $builder) => $builder->whereDate('date_edition', $dateEdition->toDateString()), fn (Builder $builder) => $sansDateEdition ? $builder->whereNull('date_edition') : $builder->whereRaw('1 = 0'))
            ->orderBy('id')
            ->get();

        return compact('conducteurs', 'editions', 'dateEdition', 'sansDateEdition', 'editionFilters');
    }
}
