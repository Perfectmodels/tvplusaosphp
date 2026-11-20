@extends('layouts.app')
@php($pageTitle = 'Contenu numérique')
@section('content')
@if($sourceDossier ?? null)@can('view', $sourceDossier)<p class="mb-4 text-sm"><a class="text-primary underline" href="{{ route('workspace.show', $sourceDossier) }}">Ouvrir le dossier partagé · {{ $sourceDossier->title }}</a></p>@endcan @endif
<div class="max-w-4xl mx-auto space-y-5">
    <a href="{{ route('digital.index') }}" class="inline-flex min-h-[44px] items-center text-sm text-primary underline">Tous les contenus numériques</a>
    <article class="rounded-2xl border border-outline-variant bg-white p-4 sm:p-6 space-y-4 break-words">
        <p class="text-sm font-semibold text-primary">{{ config('workspaces.digital_platforms.'.$publication->platform) }} · {{ config('workspaces.digital_statuses.'.$publication->status) }}</p><h1 class="text-2xl font-bold">{{ $publication->title }}</h1>
        <p class="text-sm text-secondary">{{ $publication->auteur?->name ?? 'Compte supprimé' }} · {{ $publication->updated_at->format('d/m/Y H:i') }}</p>
        <div class="whitespace-pre-wrap text-base leading-relaxed">{{ $publication->content }}</div>
        @if($publication->feedback)<div class="rounded-lg bg-error-container p-4 text-sm text-on-error-container"><strong>Correction demandée</strong><p class="mt-2">{{ $publication->feedback }}</p></div>@endif
        @if($publication->approved_at)<p class="text-sm text-secondary">Validé le {{ $publication->approved_at->format('d/m/Y H:i') }}</p>@endif
        @if($publication->scheduled_at)<p class="text-sm text-secondary">Planifié le {{ $publication->scheduled_at->format('d/m/Y H:i') }}</p>@endif
        @if($publication->published_at)<p class="text-sm text-secondary">Publication confirmée le {{ $publication->published_at->format('d/m/Y H:i') }}</p><a href="{{ $publication->publication_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] items-center text-primary underline text-sm break-all">Voir la publication</a>@endif
        @can('update', $publication)<a href="{{ route('digital.edit', $publication) }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-outline-variant px-4 py-2 text-sm text-primary font-semibold">Modifier le brouillon</a>
        <form method="POST" action="{{ route('digital.transition', $publication) }}">@csrf<input type="hidden" name="action" value="submit"><button class="min-h-[44px] rounded-lg bg-primary text-white px-4 py-3 text-sm font-semibold">Demander la validation</button></form>@endcan
    </article>
    @can('approve', $publication)
        @if($publication->status === 'awaiting_validation')<form method="POST" action="{{ route('digital.transition', $publication) }}" class="rounded-xl border border-outline-variant bg-white p-4">@csrf<input type="hidden" name="action" value="approve"><button class="min-h-[44px] rounded-lg bg-primary text-white px-4 py-3 text-sm font-semibold">Approuver le contenu</button></form>@endif
        @if(in_array($publication->status, ['awaiting_validation', 'approved', 'scheduled'], true))<form method="POST" action="{{ route('digital.transition', $publication) }}" class="rounded-xl border border-outline-variant bg-white p-4 space-y-3">@csrf<input type="hidden" name="action" value="return"><label class="block text-sm font-semibold">Corrections à apporter<textarea name="feedback" rows="3" required maxlength="5000" class="form-input mt-2 w-full">{{ old('feedback') }}</textarea></label><button class="min-h-[44px] rounded-lg border border-outline-variant text-primary px-4 py-3 text-sm font-semibold">Retourner pour correction</button></form>@endif
    @endcan
    @can('publish', $publication)
        @if(in_array($publication->status, ['approved', 'scheduled'], true))
            <form method="POST" action="{{ route('digital.transition', $publication) }}" class="rounded-xl border border-outline-variant bg-white p-4 space-y-3">@csrf<input type="hidden" name="action" value="schedule"><label class="block text-sm font-semibold">Date et heure de publication<input type="datetime-local" name="scheduled_at" required value="{{ old('scheduled_at', $publication->scheduled_at?->format('Y-m-d\\TH:i')) }}" class="form-input mt-2 w-full min-w-0"></label><button class="min-h-[44px] rounded-lg bg-primary text-white px-4 py-3 text-sm font-semibold">Enregistrer la planification</button></form>
            <form method="POST" action="{{ route('digital.transition', $publication) }}" class="rounded-xl border border-outline-variant bg-white p-4 space-y-3">@csrf<input type="hidden" name="action" value="publish"><p class="text-sm text-on-surface-variant">Après la mise en ligne sur la plateforme, renseignez son lien pour confirmer la publication.</p><label class="block text-sm font-semibold">Lien de la publication<input type="url" name="publication_url" required maxlength="2048" placeholder="https://…" value="{{ old('publication_url') }}" class="form-input mt-2 w-full min-w-0"></label><button class="min-h-[44px] rounded-lg bg-primary text-white px-4 py-3 text-sm font-semibold">Marquer comme publié</button></form>
        @endif
    @endcan
</div>
@endsection
