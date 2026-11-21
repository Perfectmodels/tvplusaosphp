@extends('layouts.app')
@php
    $pageTitle = 'Nouveau sondage';
    $pageSubtitle = 'Débat · '.$emission->nom;
@endphp
@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="card-surface p-6">
            <h2 class="mb-1 text-xl font-bold text-slate-900">Créer un sondage en direct</h2>
            <p class="mb-6 text-sm text-slate-600">
                Les débatteurs de la séquence DÉBAT sont automatiquement les candidats du sondage.
            </p>

            @if($conducteurs->count() > 1)
                <form method="GET" action="{{ route('sondages.create', $emission) }}" class="mb-5 rounded-lg border border-slate-200 p-3">
                    <label for="edition-select" class="mb-2 block text-sm font-semibold text-slate-800">Édition du débat</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <select id="edition-select" name="conducteur_id" required class="min-w-[200px] flex-1 rounded-lg border border-slate-300 p-2.5 text-sm">
                            <option value="">Choisissez une édition</option>
                            @foreach($conducteurs as $edition)
                                <option value="{{ $edition->id }}" @selected((int) $selectedConducteurId === (int) $edition->id)>
                                    {{ $edition->date_edition?->format('d/m/Y') }} · {{ $edition->code_conducteur_affichage }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white">Afficher les débatteurs</button>
                    </div>
                    @error('conducteur_id') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
                </form>
            @endif
            @if($candidats->isEmpty())
                <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    Aucun débatteur n'est associé à une séquence DÉBAT de cette émission.
                    Ajoutez au moins deux intervenants avec le rôle « Débatteur » avant de créer le sondage.
                </div>
            @else
                <form method="POST" action="{{ route('sondages.store', $emission) }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="conducteur_id" value="{{ $selectedConducteurId }}">

                    <div>
                        <label for="question" class="mb-1.5 block text-sm font-semibold text-slate-800">Question posée au public <span class="text-red-700">*</span></label>
                        <input type="text" id="question" name="question" required minlength="5" maxlength="255"
                               value="{{ old('question') }}"
                               placeholder="Qui avez-vous trouvé le plus convaincant ?"
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                        @error('question')
                            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold text-slate-800">Candidats ({{ $candidats->count() }})</legend>
                        <ul class="space-y-2">
                            @foreach($candidats as $candidat)
                                <li class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <span class="text-xs font-bold text-slate-400">{{ (int) ($candidat->pivot->ordre_passage ?? $loop->iteration) }}</span>
                                    <span class="text-sm font-medium text-slate-800">{{ $candidat->nomComplet }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>

                    <div class="flex gap-3">
                        <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white hover:opacity-90">
                            Créer le sondage
                        </button>
                        <a href="{{ route('emissions.show', $emission) }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Annuler
                        </a>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
