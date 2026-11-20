<?php

namespace App\Http\Controllers;

use App\Enums\PoleRubriqueEnum;
use App\Models\SourceVeille;
use App\Models\VeilleSubject;
use App\Services\VeilleAutomationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VeilleSubjectController extends Controller
{
    use AuthorizesRequests;

    protected function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = Str::slug($base) ?: ('sujet-'.Str::random(6));
        $cand = $slug;
        $i = 1;
        while (VeilleSubject::where('slug', $cand)
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->exists()) {
            $cand = $slug.'-'.$i++;
        }

        return $cand;
    }

    public function index(Request $request, VeilleAutomationService $automationService)
    {
        $this->authorize('viewAny', VeilleSubject::class);
        $request->validate([
            'country' => ['nullable', 'in:GA'],
            'stream' => ['nullable', 'in:gabon,articles,alertes,a_verifier,all'],
        ]);

        $stream = (string) $request->query('stream', '');
        $country = (string) $request->query('country', '');
        $gabonOnly = $country === 'GA' || in_array($stream, ['gabon', 'articles', 'alertes'], true);

        $query = VeilleSubject::with(['source'])
            ->withCount('remixes')
            ->orderByRaw('COALESCE(published_at, scraped_at, created_at) DESC')
            ->orderByDesc('id');

        if ($gabonOnly) {
            $query->gabon();
        }
        if ($stream === 'a_verifier') {
            $query->whereNot(function ($q) {
                $q->gabon();
            });
        }

        if ($cat = $request->query('category')) {
            // ?category=none → les sujets « Non classés »
            if ($cat === 'none') {
                $query->whereNull('category');
            } elseif ($normalise = PoleRubriqueEnum::tryFromLoose((string) $cat)?->value) {
                $query->where('category', $normalise);
            } else {
                $query->whereRaw('1 = 0'); // catégorie inconnue → liste vide
            }
        }
        if ($status = $request->query('status')) {
            if ($status === 'pending') {
                $query->whereIn('status', ['pending', 'draft']);
            } elseif ($status === 'archived') {
                $query->whereIn('status', ['archived', 'rejected']);
            } elseif (in_array($status, ['approved', 'rejected', 'draft', 'archived'], true)) {
                $query->where('status', $status);
            }
        }
        if ($src = $request->query('source_id')) {
            $query->where('source_id', $src);
        }
        if ($q = trim($request->query('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'LIKE', '%'.$q.'%')
                    ->orWhere('summary', 'LIKE', '%'.$q.'%')
                    ->orWhere('tags', 'LIKE', '%'.$q.'%');
            });
        }

        // Stream/kind filtering (derived from existing data, no schema change)
        $streamFiltered = false;
        if (in_array($stream, ['articles', 'alertes'], true)) {
            $candidates = (clone $query)->limit(500)->get();
            $ids = $candidates->filter(function ($s) use ($stream) {
                $kind = $s->veilleKind();
                return $stream === 'alertes' ? in_array($kind, ['alerte', 'social'], true) : $kind === 'article';
            })->pluck('id')->all();
            $query = VeilleSubject::with(['source'])
                ->withCount('remixes')
                ->whereIn('id', $ids ?: [0])
                ->orderByRaw('COALESCE(published_at, scraped_at, created_at) DESC')
                ->orderByDesc('id');
            $streamFiltered = true;
        }

        $subjects = $query->paginate(12)->withQueryString();

        $baseCount = VeilleSubject::query();
        $gabonBase = (clone $baseCount)->gabon();
        $streamCounts = [
            'gabon' => (clone $gabonBase)->count(),
            'a_verifier' => (clone $baseCount)->whereNot(function ($q) { $q->gabon(); })->count(),
        ];
        $gabonCandidates = (clone $gabonBase)->with('source')->latest('id')->limit(800)->get();
        $streamCounts['alertes'] = $gabonCandidates->filter(fn ($s) => in_array($s->veilleKind(), ['alerte', 'social'], true))->count();
        $streamCounts['articles'] = $gabonCandidates->filter(fn ($s) => $s->veilleKind() === 'article')->count();

        $countQuery = VeilleSubject::query()
            ->when($gabonOnly, fn ($q) => $q->gabon())
            ->when($stream === 'a_verifier', fn ($q) => $q->whereNot(function ($qq) { $qq->gabon(); }));
        if ($streamFiltered) {
            // counts in header reflect status within the broader selected scope
            $countQuery = VeilleSubject::query()
                ->whereIn('id', $gabonCandidates->pluck('id')->all() ?: [0])
                ->when($gabonOnly, fn ($q) => $q->gabon());
        }
        $counts = [
            'all' => (clone $countQuery)->count(),
            'pending' => (clone $countQuery)->whereIn('status', ['pending', 'draft'])->count(),
            'approved' => (clone $countQuery)->where('status', 'approved')->count(),
            'archived' => (clone $countQuery)->whereIn('status', ['archived', 'rejected'])->count(),
        ];

        // Compteurs par rubrique (pills de filtre) + « Non classés »
        $categoryBase = VeilleSubject::query()
            ->when($gabonOnly, fn ($q) => $q->gabon())
            ->when($stream === 'a_verifier', fn ($q) => $q->whereNot(function ($qq) { $qq->gabon(); }));
        $categoryCounts = (clone $categoryBase)
            ->selectRaw('category, count(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category');
        $nonClasses = (int) (clone $categoryBase)->whereNull('category')->count();

        $sources = SourceVeille::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $automation = $automationService->snapshot();

        return view('veille.sujets.index', compact(
            'subjects', 'counts', 'categoryCounts', 'nonClasses', 'sources', 'automation',
            'streamCounts', 'stream', 'gabonOnly'
        ));
    }

    public function show(VeilleSubject $subject)
    {
        $this->authorize('view', $subject);
        $subject->load(['source', 'journaliste', 'remixes', 'evenementsEditoriaux']);

        return view('veille.sujets.show', compact('subject'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', VeilleSubject::class);
        $validated = $request->validate([
            'title' => 'required|string|min:2|max:255',
            'url' => 'nullable|url',
            'source_id' => 'nullable|exists:source_veille,id',
            'category' => ['nullable', Rule::enum(PoleRubriqueEnum::class)],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'archived'])],
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'image_url' => 'nullable|url|max:1000',
            'priority' => 'nullable|integer|min:1|max:10',
            'published_at' => 'nullable|date',
        ]);

        $validated['slug'] = $this->uniqueSlug($validated['title']);
        $validated['status'] = $validated['status'] ?? 'draft';
        $validated['scraped_at'] = now();
        if (empty($validated['priority'])) {
            unset($validated['priority']);
        }

        if (! empty($validated['url']) && VeilleSubject::where('url', $validated['url'])->exists()) {
            $existing = VeilleSubject::where('url', $validated['url'])->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'skipped' => true,
                    'message' => 'URL déjà existante',
                    'id' => $existing->id,
                ], 200);
            }

            return redirect()->route('veille.sujets.show', $existing)
                ->with('status', 'Sujet déjà existant pour cette URL.');
        }

        $subject = VeilleSubject::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'skipped' => false,
                'message' => 'Sujet créé avec succès.',
                'id' => $subject->id,
                'title' => $subject->title,
                'status' => $subject->status,
            ], 200);
        }

        return redirect()->route('veille.sujets.show', $subject)
            ->with('status', 'Sujet « '.e($subject->title).' » créé avec succès.');
    }

    public function update(Request $request, VeilleSubject $subject)
    {
        $this->authorize('update', $subject);
        $validated = $request->validate([
            'title' => 'sometimes|required|string|min:2|max:255',
            'url' => 'sometimes|nullable|url|unique:veille_subjects,url,'.$subject->id,
            'source_id' => 'sometimes|nullable|exists:source_veille,id',
            'category' => ['sometimes', 'nullable', Rule::enum(PoleRubriqueEnum::class)],
            'status' => ['sometimes', 'nullable', Rule::in(['draft', 'pending', 'approved', 'rejected', 'archived'])],
            'summary' => 'sometimes|nullable|string',
            'content' => 'sometimes|nullable|string',
            'image_url' => 'sometimes|nullable|url|max:1000',
            'priority' => 'sometimes|nullable|integer|min:1|max:10',
            'assigned_journaliste_id' => 'sometimes|nullable|exists:journalistes,id',
            'published_at' => 'sometimes|nullable|date',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = $this->uniqueSlug($validated['title'], $subject->id);
        }

        $subject->update($validated);

        return redirect()->back()
            ->with('status', 'Sujet « '.e($subject->title).' » mis à jour.');
    }

    public function destroy(VeilleSubject $subject)
    {
        $this->authorize('delete', $subject);
        $title = $subject->title;
        $subject->update(['status' => 'archived']);

        return redirect()->route('veille.sujets.index')
            ->with('status', 'Sujet « '.e($title).' » archivé afin de conserver son historique.');
    }

    public function archive(VeilleSubject $subject)
    {
        $this->authorize('update', $subject);
        $subject->update(['status' => 'archived']);

        return redirect()->back()
            ->with('status', 'Sujet archivé.');
    }
}
