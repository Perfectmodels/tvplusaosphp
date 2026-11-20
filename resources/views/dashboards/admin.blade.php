@extends('layouts.app')
@push('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/workspace.css'])
@endpush
@php
    $pageTitle = 'Tableau de bord';
    $pageSubtitle = $roleLabel.' · Supervision globale : veille, production, rédactions, émissions et administration';
    $headerActions = null;
    $tableauDeBordChiffres = collect($tableauDeBordChiffres ?? []);
    $heroStats = collect($kpis)->take(4)
        ->map(fn ($kpi) => ['label' => $kpi['label'], 'value' => $kpi['value'], 'icon' => $kpi['icon']])
        ->all();
    if ($tableauDeBordChiffres->has('inscriptions_attente')) {
        $heroStats[] = ['label' => 'Inscriptions en attente', 'value' => $tableauDeBordChiffres->get('inscriptions_attente'), 'icon' => 'person_add'];
    }
    if ($tableauDeBordChiffres->has('comptes_actifs')) {
        $heroStats[] = ['label' => 'Comptes actifs', 'value' => $tableauDeBordChiffres->get('comptes_actifs').' / '.$tableauDeBordChiffres->get('comptes_total'), 'icon' => 'manage_accounts'];
    }
    if ($tableauDeBordChiffres->has('conducteurs_du_jour')) {
        $heroStats[] = ['label' => 'Conducteurs du jour', 'value' => $tableauDeBordChiffres->get('conducteurs_du_jour'), 'icon' => 'event_available'];
    }
@endphp
@section('content')
<div class="ed-dashboard-content ed-role-dashboard">
    @include('_partials.workspace-toolbar')
    @include('_partials.dashboard-hero', ['heroTitle' => 'Indicateurs de supervision'])
    @include('_partials.dashboard-role-extras')
    @include('_partials.dashboard-role-body')
</div>
@endsection
