@extends('layouts.app')
@php($pageTitle = 'Notifications de mon équipe')
@push('styles') @vite('resources/css/workspace.css') @endpush
@section('content')
<div class="ws-space"><div class="ws-heading"><div><h1>Notifications de mon équipe</h1><p>Affectations, échanges et changements dans mes dossiers accessibles.</p></div><form method="POST" action="{{ route('workspace.notifications.read') }}">@csrf<button type="submit" class="ws-button ws-button-secondary">Tout marquer comme lu</button></form></div><section class="ws-section">@forelse($notifications as $notification)<article class="ws-dossier-row"><div><a class="ws-title-link" href="{{ route('workspace.show', $notification->dossier) }}">{{ $notification->dossier->title }}</a><p>{{ $notification->message }}</p><small>{{ $notification->created_at->format('d/m/Y à H:i') }} · {{ $notification->read_at ? 'Lue' : 'Non lue' }}</small></div><a class="ws-link" href="{{ route('workspace.show', $notification->dossier) }}">Ouvrir le dossier</a></article>@empty<div class="ws-empty">Aucune notification disponible.</div>@endforelse{{ $notifications->links() }}</section></div>
@endsection
