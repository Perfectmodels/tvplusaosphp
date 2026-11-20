@extends('layouts.app')
@push('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/workspace.css'])
@endpush
@php
    $pageTitle = 'Tableau de bord';
    $pageSubtitle = $roleLabel.' · Arbitrage éditorial, validations et supervision des contenus';
    $headerActions = null;
    $heroStats = collect($kpis)->take(4)
        ->map(fn ($kpi) => ['label' => $kpi['label'], 'value' => $kpi['value'], 'icon' => $kpi['icon']])
        ->all();
    $heroStats[] = ['label' => 'Priorités du moment', 'value' => count($priorities), 'icon' => 'flag'];
@endphp
@section('content')
<div class="ed-dashboard-content ed-role-dashboard">
    @include('_partials.workspace-toolbar')
    @include('_partials.dashboard-hero', ['heroTitle' => 'Indicateurs de la rédaction'])
    @include('_partials.dashboard-role-body')
</div>
@endsection
