@extends('layouts.app')
@php
    $pageTitle = 'Créer un conducteur Présentateur';
    $pageSubtitle = 'Préparer une nouvelle édition de votre programme';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('conducteurs.emission.index'), 'variant' => 'ghost'],
    ];

    $today = old('date_edition', date('Y-m-d'));
    $defaultDuree = (int) old('target_duration_sec', 915);
@endphp
@section('content')
<form action="{{ route('conducteurs.presentateur.store') }}" method="POST" class="max-w-5xl mx-auto space-y-6" novalidate>
    @csrf

    <div class="card-surface">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-primary-container text-primary flex items-center justify-center font-bold text-sm">
                <i class="fa-solid fa-presentation-screen"></i>
            </div>
            <div class="flex-1">
                <h2 class="font-bold text-slate-900">Nouveau conducteur d'émission</h2>
                <p class="text-sm text-slate-500">Renseignez l'émission, la date et le présentateur assigné. Les séquences seront ajoutées ensuite depuis la vue Rédaction.</p>
            </div>
        </div>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Émission <span class="text-error">*</span>
                    </label>
                    <div data-emission-searchable class="space-y-2">
                        <input type="search" data-emission-search aria-label="Rechercher une émission"
                               placeholder="Rechercher dans les émissions…" autocomplete="off" class="input-field">
                        <select name="emission_id" required data-emission-select class="input-field">
                            <option value="">Sélectionner une émission…</option>
                            @foreach($emissions as $e)
                                <option value="{{ $e->id }}" @selected(old('emission_id') == $e->id)>
                                    {{ $e->nom }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('emission_id')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Date d'édition <span class="text-error">*</span>
                    </label>
                    <input type="date" name="date_edition" required value="{{ $today }}" class="input-field">
                    @error('date_edition')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Présentateur assigné <span class="text-error">*</span>
                    </label>
                    <select name="presentateur_id" required class="input-field">
                        <option value="">Sélectionner un présentateur…</option>
                        @if($presentateurs->isNotEmpty())
                            <optgroup label="Présentateurs (intervenants)">
                                @foreach($presentateurs as $p)
                                    @php
                                        $civ = '';
                                        try {
                                            $civ = is_object($p->civilite) && method_exists($p->civilite, 'label')
                                                ? $p->civilite->label()
                                                : (string) ($p->civilite?->value ?? '');
                                        } catch (\Throwable) { $civ = ''; }
                                    @endphp
                                    <option value="{{ $p->id }}" @selected((string) old('presentateur_id') === (string) $p->id)>
                                        {{ trim($civ.' '.$p->prenom.' '.$p->nom) }}
                                        @if($p->fonction) — {{ $p->fonction }}@endif
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if(($presentateursJournalistes ?? collect())->isNotEmpty())
                            <optgroup label="Présentateurs (journalistes)">
                                @foreach($presentateursJournalistes as $j)
                                    @php
                                        $jCiv = '';
                                        try {
                                            $jCiv = is_object($j->civilite) && method_exists($j->civilite, 'label')
                                                ? $j->civilite->label()
                                                : (string) ($j->civilite?->value ?? '');
                                        } catch (\Throwable) { $jCiv = ''; }
                                        $jFn = $j->fonction?->value ?? $j->fonction ?? 'Présentateur';
                                        $jVal = 'j:'.$j->id;
                                    @endphp
                                    <option value="{{ $jVal }}" @selected((string) old('presentateur_id') === $jVal)>
                                        {{ trim($jCiv.' '.$j->prenom.' '.$j->nom) }} — {{ $jFn }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    @error('presentateur_id')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                    @if($presentateurs->isEmpty() && ($presentateursJournalistes ?? collect())->isEmpty())
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-1">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Aucun présentateur disponible. Ajoutez un intervenant avec la fonction « Présentateur/Animateur » ou marquez un journaliste comme présentateur.
                        </p>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Durée cible (optionnel)
                    </label>
                    <select name="target_duration_sec" class="input-field">
                        <option value="">Valeur par défaut (15 min 15 s)</option>
                        <option value="600"  @selected($defaultDuree === 600)>10 min</option>
                        <option value="900"  @selected($defaultDuree === 900)>15 min</option>
                        <option value="915"  @selected($defaultDuree === 915)>15 min 15 s (Standard JT)</option>
                        <option value="1200" @selected($defaultDuree === 1200)>20 min</option>
                        <option value="1800" @selected($defaultDuree === 1800)>30 min</option>
                        <option value="2700" @selected($defaultDuree === 2700)>45 min</option>
                        <option value="3600" @selected($defaultDuree === 3600)>1 h</option>
                        <option value="7200" @selected($defaultDuree === 7200)>2 h</option>
                        <option value="10800" @selected($defaultDuree === 10800)>3 h</option>
                        <option value="14400" @selected($defaultDuree === 14400)>4 h</option>
                        <option value="21600" @selected($defaultDuree === 21600)>6 h</option>
                    </select>
                    @error('target_duration_sec')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Texte d'ouverture (optionnel)
                </label>
                <textarea name="opening_text" rows="4" maxlength="5000"
                          placeholder="Accroche, mot de bienvenue, mentions légales à lire en début d'émission…"
                          class="input-field resize-none">{{ old('opening_text') }}</textarea>
                <p class="text-sm text-slate-400 mt-1">5 000 caractères max. Raccourci affiché dans le panneau de résumé.</p>
                @error('opening_text')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/60 rounded-b-2xl flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <i class="fa-solid fa-circle-info"></i>
                Une fois créé, vous pourrez consulter les séquences, activer celle en cours et prendre vos notes personnelles.
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('conducteurs.emission.index') }}"
                   class="px-5 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 font-medium text-sm">
                    Annuler
                </a>
                <button type="submit"
                        class="px-6 py-2 rounded-lg bg-primary text-white hover:bg-primary-container hover:text-primary font-semibold text-sm inline-flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Créer le conducteur
                </button>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="card-surface border border-error/30 bg-error/5">
            <div class="p-4 flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-error/15 text-error flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="flex-1 text-sm">
                    <p class="font-semibold text-error mb-1">Le formulaire contient {{ $errors->count() }} erreur(s) :</p>
                    <ul class="list-disc pl-5 text-slate-700 space-y-0.5">
                        @foreach($errors->all() as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
</form>
@endsection
