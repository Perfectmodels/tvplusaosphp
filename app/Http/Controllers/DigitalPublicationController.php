<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDigitalPublicationRequest;
use App\Http\Requests\UpdateDigitalPublicationRequest;
use App\Models\DigitalPublication;
use App\Models\Emission;
use App\Models\WorkspaceDossier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DigitalPublicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DigitalPublication::class);
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(config('workspaces.digital_statuses')))], 'platform' => ['nullable', Rule::in(array_keys(config('workspaces.digital_platforms')))]]);
        $publications = DigitalPublication::accessibleTo($request->user())->with(['auteur', 'emission'])
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['platform'] ?? null), fn ($q) => $q->where('platform', $filters['platform']))
            ->latest('updated_at')->latest('id')->paginate(15)->withQueryString();

        return view('digital.index', compact('publications', 'filters'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', DigitalPublication::class);

        $request->validate(['dossier' => ['nullable', 'integer', 'exists:workspace_dossiers,id']]);
        $sourceDossier = $request->filled('dossier') ? WorkspaceDossier::findOrFail($request->input('dossier')) : null;
        if ($sourceDossier) {
            $this->authorize('view', $sourceDossier);
            abort_if(! $sourceDossier->source_available || $sourceDossier->merged_into_id, 422);
        }

        return view('digital.form', ['sourceDossier' => $sourceDossier, 'publication' => new DigitalPublication, 'emissions' => Emission::orderBy('nom')->get(['id', 'nom'])]);
    }

    public function store(StoreDigitalPublicationRequest $request): RedirectResponse
    {
        $publication = DigitalPublication::create([...$request->safe()->except('dossier_id'), ...$request->sourceAttributes(), 'created_by' => $request->user()->id, 'status' => 'draft']);

        return redirect()->route('digital.show', $publication)->with('status', 'Brouillon créé.');
    }

    public function show(DigitalPublication $publication): View
    {
        $this->authorize('view', $publication);

        return view('digital.show', ['publication' => $publication, 'sourceDossier' => $publication->sharedDossier()]);
    }

    public function edit(DigitalPublication $publication): View
    {
        $this->authorize('update', $publication);

        return view('digital.form', ['sourceDossier' => $publication->sharedDossier(), 'publication' => $publication, 'emissions' => Emission::orderBy('nom')->get(['id', 'nom'])]);
    }

    public function update(UpdateDigitalPublicationRequest $request, DigitalPublication $publication): RedirectResponse
    {
        DB::transaction(function () use ($request, $publication): void {
            $locked = DigitalPublication::lockForUpdate()->findOrFail($publication->id);
            $this->authorize('update', $locked);
            $locked->update([...$request->safe()->except('dossier_id'), ...$request->sourceAttributes(), 'status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'scheduled_at' => null]);
        });

        return redirect()->route('digital.show', $publication)->with('status', 'Brouillon enregistré.');
    }

    public function transition(Request $request, DigitalPublication $publication): RedirectResponse
    {
        $this->authorize('view', $publication);
        $valid = $request->validate([
            'action' => ['required', Rule::in(['submit', 'approve', 'return', 'schedule', 'publish'])],
            'feedback' => ['required_if:action,return', 'nullable', 'string', 'max:5000'],
            'scheduled_at' => ['required_if:action,schedule', 'nullable', 'date', 'after:now'],
            'publication_url' => ['required_if:action,publish', 'nullable', 'url:http,https', 'max:2048'],
        ]);
        DB::transaction(function () use ($request, $publication, $valid): void {
            $locked = DigitalPublication::lockForUpdate()->findOrFail($publication->id);
            $action = $valid['action'];
            $this->authorize(match ($action) {
                'submit' => 'update', 'approve', 'return' => 'approve', default => 'publish'
            }, $locked);
            $allowed = match ($action) {
                'submit' => ['draft', 'changes_requested'], 'approve' => ['awaiting_validation'],
                'return' => ['awaiting_validation', 'approved', 'scheduled'], 'schedule', 'publish' => ['approved', 'scheduled'],
            };
            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages(['action' => 'Cette opération n’est pas disponible dans l’état actuel du contenu.']);
            }
            $attributes = match ($action) {
                'submit' => ['status' => 'awaiting_validation', 'feedback' => null],
                'approve' => ['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'feedback' => null],
                'return' => ['status' => 'changes_requested', 'feedback' => trim($valid['feedback']), 'approved_by' => null, 'approved_at' => null, 'scheduled_at' => null],
                'schedule' => ['status' => 'scheduled', 'scheduled_at' => $valid['scheduled_at']],
                'publish' => ['status' => 'published', 'published_at' => now(), 'publication_url' => $valid['publication_url']],
            };
            $locked->update($attributes);
        });

        return redirect()->route('digital.show', $publication)->with('status', 'État du contenu mis à jour.');
    }
}
