<?php

namespace App\Http\Controllers;

use App\Enums\PoleRubriqueEnum;
use App\Models\SourceVeille;
use App\Rules\UrlPublique;
use App\Services\FormOptionsService;
use App\Services\VeilleAutomationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VeilleSourceController extends Controller
{
    public function index(Request $request, VeilleAutomationService $automationService, FormOptionsService $formOptions)
    {
        $this->authorize('viewAny', SourceVeille::class);

        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', '');
        $sourceQuery = SourceVeille::query()
            ->withCount('subjects')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('url', 'like', '%'.$search.'%');
                });
            })
            ->when(in_array($statusFilter, ['active', 'error', 'disabled'], true), function ($query) use ($statusFilter): void {
                $query->where('status', $statusFilter);
            })
            ->orderByDesc('id');

        $sources = $sourceQuery->paginate(12)->withQueryString();
        $online = SourceVeille::where('is_active', true)->count();
        $stats = [
            'total' => SourceVeille::count(),
            'active' => $online,
            'errors' => SourceVeille::where('status', 'error')->count(),
            'disabled' => SourceVeille::where('is_active', false)->count(),
            'due' => SourceVeille::where('is_active', true)->get()->filter(
                static fn (SourceVeille $source): bool => $source->estDue()
            )->count(),
        ];
        $editing = null;

        if ($request->filled('edit')) {
            $editing = SourceVeille::find($request->integer('edit'));
        }

        $automation = $automationService->snapshot();
        $optionsPays = $formOptions->pays();

        return view('veille.sources.index', compact('sources', 'online', 'editing', 'search', 'statusFilter', 'stats', 'automation', 'optionsPays'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', SourceVeille::class);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'url' => ['required', 'url', 'max:1000', 'starts_with:https://,http://', new UrlPublique],
            'type' => 'nullable|string|in:rss,web,single_url,listing',
            'frequency' => 'nullable|string|in:5min,15min,30min,1h,2daily,daily',
            'status' => 'nullable|string|in:active,error,disabled',
            'category' => ['nullable', Rule::enum(PoleRubriqueEnum::class)],
            'country' => 'nullable|string|max:5',
            'language' => 'nullable|string|max:5',
            'priority' => 'nullable|integer|min:1|max:10',
        ]);

        if (empty($validated['type'])) {
            $validated['type'] = $this->detecterType($validated['url']);
        }

        $validated['frequency'] ??= '15min';
        $validated['status'] ??= 'active';

        SourceVeille::create($validated);

        return back()->with('status', 'Source « '.$validated['name'].' » ajoutée avec succès.');
    }

    public function update(Request $request, SourceVeille $sourceveille)
    {
        $this->authorize('update', $sourceveille);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'url' => ['required', 'url', 'max:1000', 'starts_with:https://,http://', new UrlPublique],
            'type' => 'nullable|string|in:rss,web,single_url,listing',
            'frequency' => 'nullable|string|in:5min,15min,30min,1h,2daily,daily',
            'status' => 'nullable|string|in:active,error,disabled',
            'category' => ['nullable', Rule::enum(PoleRubriqueEnum::class)],
            'country' => 'nullable|string|max:5',
            'language' => 'nullable|string|max:5',
            'priority' => 'nullable|integer|min:1|max:10',
        ]);

        if (array_key_exists('type', $validated) && empty($validated['type'])) {
            $validated['type'] = $this->detecterType($validated['url']);
        }
        $validated['type'] ??= $sourceveille->type;
        $validated['frequency'] ??= $sourceveille->frequency;
        $validated['status'] ??= $sourceveille->status;

        $urlChangee = $validated['url'] !== $sourceveille->url;
        if ($urlChangee) {
            $validated['rss_feed'] = null;
        }
        if ($urlChangee || ($validated['status'] === 'active' && $sourceveille->status !== 'active')) {
            $validated['last_error'] = null;
            $validated['consecutive_failures'] = 0;
            $validated['collection_requested_at'] = now();
        }
        $sourceveille->update($validated);

        return to_route('veille.sources')->with('status', 'Source « '.$sourceveille->name.' » modifiée.');
    }

    private function detecterType(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $query = strtolower((string) parse_url($url, PHP_URL_QUERY));

        return preg_match('~(?:/feed/?$|/rss(?:/|$)|\.(?:rss|xml)$)~', $path)
            || preg_match('/(?:^|&)(?:format|type|page)=(?:rss|atom|backend)(?:&|$)/', $query)
            ? 'rss' : 'web';
    }

    public function destroy(SourceVeille $sourceveille)
    {
        $this->authorize('delete', $sourceveille);

        $nom = $sourceveille->name;
        $sourceveille->update(['is_active' => false]);

        return back()->with('status', 'Source « '.$nom.' » désactivée. Les articles historiques sont conservés.');
    }

    public function triggerScrape(Request $request)
    {
        $this->authorize('scrape', SourceVeille::class);

        $validated = $request->validate([
            'source_id' => [
                'nullable',
                'integer',
                Rule::exists('source_veille', 'id')->where('is_active', true),
            ],
        ]);

        $query = SourceVeille::query()->where('is_active', true)
            ->when($validated['source_id'] ?? null, fn ($query, $id) => $query->whereKey($id));
        $count = $query->update(['collection_requested_at' => now(), 'updated_at' => now()]);

        return back()->with('status', $count > 0
            ? "Collecte demandée pour {$count} source(s). Le prochain passage automatique les prendra en charge."
            : 'Aucune source active à collecter.');
    }
}
