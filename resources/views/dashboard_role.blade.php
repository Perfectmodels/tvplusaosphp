@extends('layouts.app')
@push('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/workspace.css'])
@endpush
@php
    $pageTitle = 'Tableau de bord';
    $pageSubtitle = $roleLabel.' · Priorités et activité de votre espace';
    $headerActions = null;
@endphp
@section('content')
<div class="ed-dashboard-content ed-role-dashboard">
    @include('_partials.workspace-toolbar')
    @if($kpis !== [])<section class="ed-kpis ed-role-kpis" aria-label="Indicateurs du rôle">@foreach($kpis as $kpi)<a href="#workspace-details" class="ed-kpi"><span class="ed-kpi-icon ed-tone-{{ ['blue', 'green', 'purple', 'red'][$loop->index % 4] }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $kpi['icon'] }}</span></span><div><h2>{{ $kpi['label'] }}</h2><strong>{{ $kpi['value'] }}</strong><small>{{ $kpi['value'] === 0 ? 'Aucun enregistrement' : 'Voir les détails' }}</small></div></a>@endforeach</section>@endif
    @include('_partials.dashboard-role-body')
</div>
@endsection
