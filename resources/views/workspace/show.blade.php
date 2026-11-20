@extends('layouts.app')
@php($pageTitle = 'Dossier métier partagé')
@push('styles') @vite('resources/css/workspace.css') @endpush
@section('content')
<div class="ws-space" data-workspace-case data-state-url="{{ route('workspace.state', $dossier) }}" data-revision="{{ $revision }}">
    <div class="ws-heading"><a class="ws-link" href="{{ route('workspace.index') }}">← Tous les dossiers</a><p class="ws-live" data-workspace-live role="status">Actualisation automatique des échanges et de l’avancement</p></div>
    <div data-workspace-panels>@include('workspace.panels')</div>

</div>
@endsection
