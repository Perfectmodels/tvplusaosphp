@extends('layouts.app')
@push('styles')
    @vite(['resources/css/dashboard.css', 'resources/css/workspace.css'])
@endpush
@php
    $pageTitle = 'Tableau de bord';
    $pageSubtitle = $roleLabel.' · Réalisation, montage, habillage et suivi des conducteurs';
    $headerActions = null;
    $heroStats = collect($kpis)->take(4)
        ->map(fn ($kpi) => ['label' => $kpi['label'], 'value' => $kpi['value'], 'icon' => $kpi['icon']])
        ->all();
    $heroStats[] = ['label' => 'Priorités techniques', 'value' => count($priorities), 'icon' => 'flag'];
@endphp
@section('content')
<div class="ed-dashboard-content ed-role-dashboard">
    @include('_partials.workspace-toolbar')
    @include('_partials.dashboard-hero', ['heroTitle' => 'Indicateurs de production'])
    @include('_partials.dashboard-role-body')
</div>
@endsection
