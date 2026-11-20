@extends('layouts.app')
@php($pageTitle = 'Espace métier partagé')
@push('styles') @vite('resources/css/workspace.css') @endpush
@section('content')
<div class="ws-space">
    <div class="ws-heading"><div><h1>Espace métier partagé</h1><p>Un même sujet, une équipe, un suivi commun de la rédaction à la publication.</p></div></div>
    @include('_partials.shared-workspace-summary')
    <form method="GET" class="ws-filters" aria-label="Filtrer les dossiers">
        <label>Rechercher un sujet<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Titre du sujet"></label>
        <label>Travail à suivre<select name="status"><option value="">Tous mes dossiers accessibles</option>@foreach(['mine' => 'Mes tâches en attente', 'active' => 'Tâches en cours', 'blocked' => 'Blocages signalés'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Modifié depuis<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label><label>Jusqu’au<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <button class="ws-button" type="submit">Filtrer</button><a href="{{ route('workspace.index') }}" class="ws-link">Réinitialiser</a>
    </form>
    <section class="ws-section" aria-label="Dossiers partagés">
        <div class="ws-section-title"><h2>Les dossiers de mon équipe</h2><span>{{ $dossiers->total() }} dossier(s)</span></div>
        @forelse($dossiers as $dossier)
        <article class="ws-dossier-row"><div><a class="ws-title-link" href="{{ route('workspace.show', $dossier) }}">{{ $dossier->title }}</a><p>{{ $dossier->members->pluck('name')->join(' · ') ?: 'Équipe à constituer' }}</p><small>Mis à jour {{ $dossier->updated_at->format('d/m/Y à H:i') }}</small></div><div class="ws-row-end"><span>{{ $dossier->pending_tasks_count }} tâche(s) en attente</span>@if($dossier->blocked_tasks_count)<span class="ws-status ws-status-blocked">{{ $dossier->blocked_tasks_count }} blocage(s)</span>@endif<a class="ws-link" href="{{ route('workspace.show', $dossier) }}">Ouvrir le dossier <span aria-hidden="true">→</span></a></div></article>
        @empty<div class="ws-empty"><h3>Aucun dossier dans ce périmètre</h3><p>Les sujets enregistrés en rédaction alimentent cet espace. Vous y retrouverez les dossiers qui vous sont affectés ou partagés.</p>@can('viewAny', \App\Models\Conducteur::class)<a class="ws-link" href="{{ route('conducteurs.redaction.index') }}">Ouvrir les conducteurs de rédaction</a>@endcan</div>@endforelse
        {{ $dossiers->links() }}
    </section>
</div>
@endsection
