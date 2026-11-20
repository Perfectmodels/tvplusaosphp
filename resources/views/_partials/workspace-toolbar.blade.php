<div class="ed-role-toolbar">
    <div><p>{{ now()->translatedFormat('l j F Y') }}</p><span class="ed-role-scope">{{ $scopeLabel }}</span></div>
    <x-dashboard-role-switcher :roles="$dashboardRoles" :active-role="$activeDashboardRole" />
</div>
<details id="workspace-filters" class="ed-role-filters" ontoggle="document.getElementById('workspace-filters-toggle')?.setAttribute('aria-expanded', String(this.open))" @if(request()->hasAny(['from', 'to', 'emission_id', 'department', 'q', 'status'])) open @endif>
    <summary><span class="material-symbols-outlined" aria-hidden="true">tune</span>Filtrer la période, l’émission ou le statut<span class="material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
    <form action="{{ route('dashboard') }}" method="GET" class="ed-role-filter-form">
        <input type="hidden" name="role" value="{{ $activeDashboardRole }}">
        <label>Du<input type="date" name="from" value="{{ $workspaceFilters['from'] }}" required class="form-input"></label>
        <label>Au<input type="date" name="to" value="{{ $workspaceFilters['to'] }}" required class="form-input"></label>
        @if($filterEmissions->isNotEmpty())<label>Émission<select name="emission_id" class="form-input"><option value="">Toutes mes émissions</option>@foreach($filterEmissions as $emission)<option value="{{ $emission->id }}" @selected((int)($workspaceFilters['emission_id'] ?? 0) === $emission->id)>{{ $emission->nom }}</option>@endforeach</select></label>@endif
        @if($filterDepartments->isNotEmpty())<label>Équipe / service<select name="department" class="form-input"><option value="">Tous les services</option>@foreach($filterDepartments as $department)<option value="{{ $department }}" @selected(($workspaceFilters['department'] ?? '') === $department)>{{ $department }}</option>@endforeach</select></label>@endif
        <label>Rechercher<input type="search" name="q" value="{{ $workspaceFilters['q'] ?? '' }}" placeholder="Titre, nom…" maxlength="150" class="form-input"></label>
        @if(!in_array($activeDashboardRole, ['admin', 'superadmin', 'responsable_administratif', 'documentaliste', 'receptionniste'], true))<label>Statut<select name="status" class="form-input"><option value="">Tous les statuts</option>@foreach($activeDashboardRole === 'community_manager' ? config('workspaces.digital_statuses') : ['urgent' => 'Urgences / retards', 'awaiting_validation' => 'À valider', 'validated' => 'Validés'] as $value => $label)<option value="{{ $value }}" @selected(($workspaceFilters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>@endif
        <div class="ed-role-filter-submit"><button type="submit">Appliquer</button><a href="{{ route('dashboard', ['role' => $activeDashboardRole]) }}">Aujourd’hui</a></div>
    </form>
</details>
