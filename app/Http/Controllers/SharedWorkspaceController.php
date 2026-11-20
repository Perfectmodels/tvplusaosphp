<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceActionRequest;
use App\Models\DigitalPublication;
use App\Models\User;
use App\Models\WorkspaceAttachment;
use App\Models\WorkspaceDossier;
use App\Models\WorkspaceTask;
use App\Services\SharedWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedWorkspaceController extends Controller
{
    public function __construct(private SharedWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', WorkspaceDossier::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'blocked', 'mine'])], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $user = $request->user();
        $dossiers = WorkspaceDossier::accessibleTo($user)->whereNull('merged_into_id')->with(['story', 'sequence.conducteur.emission', 'members:id,name'])->withCount(['tasks as pending_tasks_count' => fn ($t) => $t->where('status', '!=', 'done'), 'tasks as blocked_tasks_count' => fn ($t) => $t->where('status', 'blocked')])
            ->when(filled($filters['q'] ?? null), fn ($d) => $d->where(fn ($d) => $d->whereHas('story', fn ($s) => $s->where('title', 'like', '%'.$filters['q'].'%'))->orWhereHas('sequence', fn ($s) => $s->where('title', 'like', '%'.$filters['q'].'%'))))
            ->when(($filters['status'] ?? '') === 'active', fn ($d) => $d->whereHas('tasks', fn ($t) => $t->where('status', '!=', 'done')))
            ->when(($filters['status'] ?? '') === 'blocked', fn ($d) => $d->whereHas('tasks', fn ($t) => $t->where('status', 'blocked')))
            ->when(($filters['status'] ?? '') === 'mine', fn ($d) => $d->whereHas('tasks', fn ($t) => $t->where('assigned_to', $user->id)->where('status', '!=', 'done')))
            ->when($filters['from'] ?? null, fn ($d, $date) => $d->whereDate('updated_at', '>=', $date))->when($filters['to'] ?? null, fn ($d, $date) => $d->whereDate('updated_at', '<=', $date))->latest('updated_at')->latest('id')->paginate(12)->withQueryString();

        return view('workspace.index', ['dossiers' => $dossiers, 'filters' => $filters, 'sharedWorkspace' => $this->workspace->dashboard($user)]);
    }

    public function show(Request $request, WorkspaceDossier $dossier): View|RedirectResponse
    {
        $this->authorize('view', $dossier);
        if ($dossier->merged_into_id) {
            return redirect()->route('workspace.show', $dossier->merged_into_id);
        }
        $this->workspace->notifications($request->user())->where('workspace_dossier_id', $dossier->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('workspace.show', $this->data($request, $dossier));
    }

    private function data(Request $request, WorkspaceDossier $dossier): array
    {
        $dossier->load(['story.sequences.conducteur.emission', 'sequence.conducteur.emission', 'members:id,name,is_active']);
        $publications = DigitalPublication::query()->where(function ($p) use ($dossier): void {
            if ($dossier->editorial_story_id) {
                $p->where('editorial_story_id', $dossier->editorial_story_id)->orWhereHas('sourceSequence', fn ($s) => $s->where('editorial_story_id', $dossier->editorial_story_id));
            } elseif ($dossier->sequence_id) {
                $p->where('source_sequence_id', $dossier->sequence_id);
            } else {
                $p->whereRaw('1=0');
            }
        })->latest()->get();
        $mayInvite = $request->user()->can('manageMembers', $dossier);
        $candidates = $mayInvite ? User::where('is_active', true)->permission('workspace.view')->orderBy('name')->get(['id', 'name']) : $dossier->members->filter->is_active;

        return ['dossier' => $dossier, 'tasks' => $dossier->tasks()->with('assignee:id,name')->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")->latest('id')->paginate(12, ['*'], 'tasks_page')->withQueryString(), 'comments' => $dossier->comments()->with('author:id,name')->latest('id')->paginate(15, ['*'], 'comments_page')->withQueryString(), 'attachments' => $dossier->attachments()->with('author:id,name')->latest('id')->paginate(12, ['*'], 'files_page')->withQueryString(), 'activities' => $dossier->activities()->latest('id')->limit(15)->get(), 'publications' => $publications, 'candidates' => $candidates, 'revision' => (int) $dossier->activities()->max('id')];
    }

    public function state(Request $request, WorkspaceDossier $dossier): JsonResponse
    {
        $this->authorize('view', $dossier);
        $request->validate(['revision' => ['nullable', 'integer', 'min:0']]);
        if ($dossier->merged_into_id) {
            return response()->json(['redirect' => route('workspace.show', $dossier->merged_into_id)])->header('Cache-Control', 'private, no-store');
        }
        $revision = (int) $dossier->activities()->max('id');
        if ((string) $request->query('revision') === (string) $revision) {
            return response()->json(['revision' => $revision])->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['revision' => $revision, 'html' => view('workspace.panels', $this->data($request, $dossier))->render()])->header('Cache-Control', 'private, no-store');
    }

    public function comment(WorkspaceActionRequest $request, WorkspaceDossier $dossier): RedirectResponse
    {
        $this->workspace->comment($dossier, $request->user(), $request->validated('body'));

        return back()->with('status', 'Commentaire partagé avec l’équipe.');
    }

    public function task(WorkspaceActionRequest $request, WorkspaceDossier $dossier): RedirectResponse
    {
        $this->workspace->createTask($dossier, $request->user(), $request->validated());

        return back()->with('status', 'Tâche attribuée.');
    }

    public function updateTask(WorkspaceActionRequest $request, WorkspaceDossier $dossier, WorkspaceTask $task): RedirectResponse
    {
        $this->workspace->updateTask($dossier, $task, $request->user(), $request->validated());

        return back()->with('status', 'Avancement enregistré.');
    }

    public function attach(WorkspaceActionRequest $request, WorkspaceDossier $dossier): RedirectResponse
    {
        $this->workspace->attach($dossier, $request->user(), $request->file('file'));

        return back()->with('status', 'Pièce jointe partagée.');
    }

    public function download(WorkspaceDossier $dossier, WorkspaceAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $dossier);
        abort_unless($attachment->workspace_dossier_id === $dossier->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function addMember(WorkspaceActionRequest $request, WorkspaceDossier $dossier): RedirectResponse
    {
        $this->workspace->addMember($dossier, $request->user(), User::findOrFail($request->validated('user_id')));

        return back()->with('status', 'Collaborateur ajouté au dossier.');
    }

    public function removeMember(WorkspaceActionRequest $request, WorkspaceDossier $dossier, User $member): RedirectResponse
    {
        $this->workspace->removeMember($dossier, $request->user(), $member);

        return back()->with('status', 'Accès au dossier retiré.');
    }

    public function notifications(Request $request): View
    {
        $this->authorize('viewAny', WorkspaceDossier::class);
        $notifications = $this->workspace->notifications($request->user())->with('dossier.story', 'dossier.sequence')->latest('id')->paginate(20);

        return view('workspace.notifications', compact('notifications'));
    }

    public function unread(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkspaceDossier::class);

        return response()->json(['unread' => $this->workspace->unreadCount($request->user())])->header('Cache-Control', 'private, no-store');
    }

    public function markRead(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', WorkspaceDossier::class);
        $this->workspace->notifications($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marquées comme lues.');
    }
}
