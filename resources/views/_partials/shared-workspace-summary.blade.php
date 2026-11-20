@if($sharedWorkspace ?? null)
<section class="ws-summary" aria-label="Travail partagé">
    <div><a href="{{ route('workspace.index') }}" class="ws-title-link">Travail partagé</a><p>{{ $sharedWorkspace['dossiers'] }} dossier(s) accessibles · {{ $sharedWorkspace['pending'] }} tâche(s) à suivre</p></div>
    <div class="ws-summary-actions">@if($sharedWorkspace['overdue'])<a href="{{ route('workspace.index', ['status' => 'mine']) }}" class="ws-status ws-status-blocked">{{ $sharedWorkspace['overdue'] }} échéance(s) dépassée(s)</a>@endif @if($sharedWorkspace['blocked'])<a href="{{ route('workspace.index', ['status' => 'blocked']) }}" class="ws-status ws-status-blocked">{{ $sharedWorkspace['blocked'] }} blocage(s)</a>@endif<a class="ws-link" href="{{ route('workspace.index', ['status' => 'mine']) }}">Mes tâches</a><a class="ws-link" href="{{ route('workspace.index') }}">Voir les dossiers</a></div>
    @if($sharedWorkspace['tasks']->isNotEmpty())<div class="ws-summary-tasks">@foreach($sharedWorkspace['tasks'] as $task)<a href="{{ route('workspace.show', $task->dossier) }}#ws-tasks"><strong>{{ $task->title }}</strong><span>{{ $task->due_at ? $task->due_at->format('d/m H:i') : 'Sans échéance' }} · {{ config('workspaces.task_statuses.'.$task->status) }}</span></a>@endforeach</div>@endif
</section>
@endif
