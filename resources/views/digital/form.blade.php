@extends('layouts.app')
@php($pageTitle = $publication->exists ? 'Modifier le contenu numérique' : 'Créer un contenu numérique')
@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold">{{ $pageTitle }}</h1>
    <form method="POST" action="{{ $publication->exists ? route('digital.update', $publication) : route('digital.store') }}" class="rounded-2xl bg-white border border-outline-variant p-4 sm:p-6 space-y-5">
        @if($sourceDossier ?? null)@can('view', $sourceDossier)<p class="text-sm">Dossier partagé : <a class="text-primary underline" href="{{ route('workspace.show', $sourceDossier) }}">{{ $sourceDossier->title }}</a></p><input type="hidden" name="dossier_id" value="{{ $sourceDossier->id }}">@endcan @endif
        @csrf @if($publication->exists)@method('PATCH')@endif
        <label class="block text-sm font-semibold">Titre<input name="title" value="{{ old('title', $publication->title) }}" maxlength="255" required class="form-input mt-2 w-full"></label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"><label class="text-sm font-semibold min-w-0">Plateforme<select name="platform" required class="form-input mt-2 w-full">@foreach(config('workspaces.digital_platforms') as $value => $label)<option value="{{ $value }}" @selected(old('platform', $publication->platform) === $value)>{{ $label }}</option>@endforeach</select></label><label class="text-sm font-semibold min-w-0">Émission associée<select name="emission_id" class="form-input mt-2 w-full"><option value="">Sans émission associée</option>@foreach($emissions as $emission)<option value="{{ $emission->id }}" @selected((int) old('emission_id', $publication->emission_id) === $emission->id)>{{ $emission->nom }}</option>@endforeach</select></label></div>
        <label class="block text-sm font-semibold">Contenu<textarea name="content" rows="12" maxlength="50000" required class="form-input mt-2 w-full">{{ old('content', $publication->content) }}</textarea></label>
        <p class="text-sm text-on-surface-variant">L’enregistrement crée un brouillon. Demandez ensuite sa validation depuis la fiche.</p>
        <div class="flex flex-wrap gap-3"><button type="submit" class="min-h-[44px] rounded-lg bg-primary text-white px-4 py-3 text-sm font-semibold">Enregistrer le brouillon</button><a href="{{ $publication->exists ? route('digital.show', $publication) : route('digital.index') }}" class="inline-flex items-center min-h-[44px] text-primary text-sm underline">Annuler</a></div>
    </form>
</div>
@endsection
