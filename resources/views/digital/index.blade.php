@extends('layouts.app')
@php($pageTitle = 'Publications numériques')
@php($pageSubtitle = 'Préparation, validation et suivi des publications')
@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">Publications numériques</h1>@can('create', \App\Models\DigitalPublication::class)<a href="{{ route('digital.create') }}" class="min-h-[44px] rounded-lg bg-primary text-white px-4 py-3 text-sm font-semibold">Créer un contenu</a>@endcan</div>
    <p class="text-sm text-on-surface-variant">Préparez et faites valider vos contenus. La publication est confirmée manuellement avec son lien après sa mise en ligne sur la plateforme choisie.</p>
    <form method="GET" class="flex flex-wrap gap-3 items-end rounded-xl border border-outline-variant bg-white p-4">
        <label class="text-sm font-semibold min-w-0">Plateforme<select name="platform" class="form-input mt-1 w-full"><option value="">Toutes</option>@foreach(config('workspaces.digital_platforms') as $value => $label)<option value="{{ $value }}" @selected(($filters['platform'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-sm font-semibold min-w-0">Statut<select name="status" class="form-input mt-1 w-full"><option value="">Tous</option>@foreach(config('workspaces.digital_statuses') as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="min-h-[44px] bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold">Filtrer</button><a href="{{ route('digital.index') }}" class="min-h-[44px] inline-flex items-center text-sm underline text-primary">Réinitialiser</a>
    </form>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">@forelse($publications as $publication)<a href="{{ route('digital.show', $publication) }}" class="min-w-0 rounded-xl border border-outline-variant bg-white p-5 hover:border-primary"><p class="text-sm font-semibold text-primary">{{ config('workspaces.digital_platforms.'.$publication->platform) }} · {{ config('workspaces.digital_statuses.'.$publication->status) }}</p><h2 class="mt-3 font-bold break-words">{{ $publication->title }}</h2><p class="mt-2 text-sm text-on-surface-variant">{{ $publication->auteur?->name ?? 'Compte supprimé' }} · {{ $publication->updated_at->format('d/m/Y H:i') }}</p>@if($publication->scheduled_at)<p class="mt-2 text-sm {{ $publication->status === 'scheduled' && $publication->scheduled_at->isPast() ? 'text-error' : 'text-secondary' }}">Prévu le {{ $publication->scheduled_at->format('d/m/Y H:i') }}</p>@endif</a>@empty<p class="rounded-xl border border-outline-variant bg-white p-6 text-on-surface-variant text-sm">Aucun contenu numérique dans votre périmètre pour ces filtres.</p>@endforelse</div>
    {{ $publications->links() }}
</div>
@endsection
