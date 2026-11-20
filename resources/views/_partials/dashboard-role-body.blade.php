@include('_partials.dashboard-conference-pointage')
@include('_partials.shared-workspace-summary')
<div class="ed-grid" id="workspace-details">
    <div class="ed-column">
        @include('_partials.workspace-distribution')
        @include('_partials.workspace-list', ['listTitle' => 'Mes priorités', 'listId' => 'workspace-priorities', 'listItems' => $priorities, 'listEmpty' => 'Aucune urgence, échéance proche ou validation attendue dans ce périmètre.'])
    </div>
    <div class="ed-column">
        @foreach($sections as $section)@include('_partials.workspace-list', ['listTitle' => $section['title'], 'listItems' => $section['items'], 'listEmpty' => $section['empty'], 'listId' => null])@endforeach
        @if($sections === [])<section class="ed-card"><div class="ed-card-heading"><h2>Mon activité</h2></div><div class="ed-role-empty">Aucune donnée disponible pour cet espace.</div></section>@endif
    </div>
    <div class="ed-column">
        @include('_partials.workspace-list', ['listTitle' => 'Activité récente', 'listItems' => $recentActivity, 'listEmpty' => 'Aucun changement enregistré dans ce périmètre et cette période.', 'listId' => null])
        @include('_partials.workspace-actions')
    </div>
</div>
<p class="ed-role-footnote">Les priorités incluent les tâches encore en attente, même si elles ont été créées avant la période sélectionnée.</p>
@if($workspaceNotices !== [])<details class="ed-role-notices"><summary>Périmètre et modules disponibles</summary>@foreach($workspaceNotices as $notice)<p>{{ $notice }}</p>@endforeach</details>@endif
