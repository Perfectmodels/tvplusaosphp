@extends('layouts.app')
@section('content')
    @include('_partials.conducteur-edition-selector')
    <div class="px-4 md:px-6 py-5 bg-surface-container-lowest border-b border-outline/10">
        <div class="max-w-[1400px] mx-auto flex flex-wrap justify-between items-end gap-4">
            <div>
                <div class="flex items-center gap-3 text-xs text-on-surface-variant uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-list-alt text-primary"></i>
                    <span>Conducteurs · Vue Présentateur</span>
                </div>
                <h1 class="text-2xl font-bold text-on-surface flex items-center gap-3">
                    Conduite d'Émission — Programme en direct
                </h1>
                <p class="text-sm text-on-surface-variant mt-2 max-w-2xl">
                    Sélectionnez un conducteur pour accéder à la vue présentateur : scripts et notes,
                    séquence en cours et chrono de l'émission.
                </p>
            </div>
            <div class="flex gap-2 items-center flex-wrap justify-end">
                <a href="{{ route('conducteurs.chef_edition') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm border border-outline/30 rounded-lg hover:bg-surface-container transition-colors">
                    <i class="fa-solid fa-pen-to-square text-xs"></i> Vue Chef Édition
                </a>
                <a href="{{ route('conducteurs.presentateur.create') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm border border-primary/30 text-primary rounded-lg hover:bg-primary/10 transition-colors">
                    <i class="fa-solid fa-plus text-xs"></i> Nouveau conducteur
                </a>
                <a href="{{ route('conducteurs.emission.index') }}" class="flex items-center gap-2 rounded-lg border border-outline/30 px-4 py-2 text-sm hover:bg-surface-container">Choisir un conducteur</a>
            </div>
        </div>
    </div>

    <div class="p-4 md:p-6 max-w-[1400px] mx-auto">
        @if($conducteurs->count() === 0)
            <div class="p-16 text-center bg-white border border-outline/10 rounded-xl shadow-sm">
                <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-primary/10 text-primary flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-presentation-screen"></i>
                </div>
                <p class="text-lg italic text-secondary mb-3">Aucun conducteur pour cette date.</p>
                <p class="text-sm text-on-surface-variant mb-6 max-w-md mx-auto">
                    Créez un nouveau conducteur Présentateur pour préparer votre émission et accéder à la vue plateau
                    (prise de notes WYSIWYG, activation des séquences en direct).
                </p>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('conducteurs.presentateur.create') }}"
                       class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-lg hover:bg-primary-container font-medium">
                        <i class="fa-solid fa-plus"></i> Créer un conducteur Présentateur
                    </a>
                    <a href="{{ route('conducteurs.chef_edition') }}"
                       class="inline-flex items-center gap-2 px-5 py-2 border border-outline/30 rounded-lg hover:bg-surface-container text-sm">
                        <i class="fa-solid fa-pen-to-square"></i> Aller en Chef Édition
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($conducteurs as $c)
                    @continue(! in_array((string) $c->type, ['presentateur', 'presentation', 'emission'], true))
                    @php
                        $totalSeq = $c->sequences()->count();
                        $progression = (int) ($c->progression_percent ?? 0);
                        $activeSeq = $c->sequenceActive;
                        $presentateurNom = optional($c->presentateur)->prenom
                            ? trim($c->presentateur->prenom.' '.$c->presentateur->nom)
                            : 'Non assigné';
                    @endphp
                    <article class="bg-white border border-outline/10 rounded-xl overflow-hidden shadow-sm hover:shadow-md hover:border-primary/30 transition-all group">
                        <div class="p-5 border-b border-outline/10">
                            <div class="flex justify-between items-start mb-3 gap-3">
                                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-primary/20 bg-primary/10 text-primary text-xs font-bold tracking-wider">
                                        <i class="fa-solid fa-fingerprint text-xs"></i>{{ $c->code_conducteur_affichage }}
                                    </span>
                                    {!! $c->type_badge_html !!}
                                    {!! $c->statut_badge_html !!}
                                </div>
                                <span class="text-xs text-on-surface-variant">
                                    {{ optional($c->date_edition)->format('d/m/Y') ?? 'Date à renseigner' }}
                                </span>
                            </div>
                            <h3 class="font-bold text-lg text-on-surface line-clamp-1 mb-1">
                                {{ optional($c->emission)->nom ?? 'MID\'INFOS' }} · {{ $c->type_label }}
                            </h3>
                            <div class="flex items-center gap-2 text-sm text-on-surface-variant">
                                <i class="fa-regular fa-user text-xs"></i> Conduit par <span class="font-medium text-on-surface">{{ $presentateurNom }}</span>
                            </div>
                        </div>
                        <div class="px-5 py-4 space-y-3 bg-surface-container-lowest">
                            <div class="grid grid-cols-3 gap-2 text-xs text-center">
                                <div class="p-2 rounded-md bg-surface-container">
                                    <div class="text-xs sm:text-xs uppercase tracking-wide text-on-surface-variant">Séquences</div>
                                    <div class="text-lg font-bold text-on-surface mt-1">{{ $totalSeq }}</div>
                                </div>
                                <div class="p-2 rounded-md bg-surface-container">
                                    <div class="text-xs sm:text-xs uppercase tracking-wide text-on-surface-variant">Durée</div>
                                    <div class="text-lg font-bold text-on-surface mt-1">{{ $c->total_duration_hm ?? '00:00' }}</div>
                                </div>
                                <div class="p-2 rounded-md bg-surface-container">
                                    <div class="text-xs sm:text-xs uppercase tracking-wide text-on-surface-variant">Workflow</div>
                                    <div class="text-lg font-bold text-primary mt-1">{{ $progression }}%</div>
                                </div>
                            </div>
                            @if($activeSeq)
                                <div class="p-3 bg-primary-fixed/15 border-l-4 border-primary rounded-md text-xs">
                                    <div class="text-xs sm:text-xs uppercase tracking-wider text-primary font-semibold mb-1">🎙️ En ce moment</div>
                                    <div class="font-semibold text-on-surface line-clamp-1">#{{ $activeSeq->position }} · {{ $activeSeq->title }}</div>
                                </div>
                            @else
                                <div class="p-3 border-l-4 border-outline/20 rounded-md text-xs text-on-surface-variant italic bg-surface-container-low">
                                    Aucune séquence activée · cliquez sur une ligne dans la vue présentateur
                                </div>
                            @endif
                        </div>
                        <div class="p-4 border-t border-outline/10 flex items-center justify-between">
                            <a href="{{ route('conducteurs.presentateur.show', $c) }}"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg group-hover:bg-primary-container transition-colors text-sm font-medium">
                                <i class="fa-solid fa-microphone-lines text-xs"></i> Ouvrir vue présentateur
                            </a>
                            <a href="{{ route('conducteurs.chef_edition', $c) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg min-h-[44px] text-xs text-on-surface-variant hover:text-primary transition-colors">
                                <i class="fa-solid fa-pen"></i> Modifier
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-8">
                @if($allDates ?? false)
                    @include('_partials.conducteur-overview-pagination')
                @else
                    @include('_partials.conducteur-edition-pagination')
                @endif
            </div>
        @endif
    </div>
@if($conducteurs->isEmpty() && !($allDates ?? false))
    <div class="px-4 pb-6">@include('_partials.conducteur-edition-pagination')</div>
@endif
@endsection

