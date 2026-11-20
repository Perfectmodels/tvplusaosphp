@extends('layouts.app')
@push('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/workspace.css'])
@endpush
@php
    $pageTitle = 'Tableau de bord';
    $pageSubtitle = $roleLabel.' · Vos émissions, le déroulé antenne et les alertes du jour';
    $headerActions = null;
    $tableauDeBordChiffres = collect($tableauDeBordChiffres ?? []);
    $heroStats = collect($kpis)->take(3)
        ->map(fn ($kpi) => ['label' => $kpi['label'], 'value' => $kpi['value'], 'icon' => $kpi['icon']])
        ->all();
    $heroStats[] = ['label' => 'Mes émissions aujourd’hui', 'value' => $tableauDeBordChiffres->get('conducteurs_du_jour', 0), 'icon' => 'event_available'];
    $heroStats[] = ['label' => 'Alertes info actives', 'value' => $tableauDeBordChiffres->get('alertes_info', 0), 'icon' => 'notification_important'];
@endphp
@section('content')
<div class="ed-dashboard-content ed-role-dashboard">
    @include('_partials.workspace-toolbar')
    @include('_partials.dashboard-hero', ['heroTitle' => 'Mes indicateurs antenne'])
    @include('_partials.dashboard-role-extras')
    @include('_partials.dashboard-role-body')
</div>
@endsection
