@extends('layouts.app')
@php
    $pageTitle = 'Événement éditorial';
    $pageSubtitle = 'Vérification humaine, sujets et suivi de production';
    $headerActions = null;
    $responsableEditorial = auth()->user()->hasAnyRole(['admin', 'directeur_general', 'chef_edition', 'chef_redaction']);
@endphp

@section('content')
    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-outline-variant/30 card-shadow p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <a href="{{ route('veille.sujets.index') }}" class="text-xs font-bold text-primary-container hover:underline">← Retour à la veille</a>
                    <h1 class="text-2xl font-black text-on-primary-fixed mt-3">{{ $event->title }}</h1>
                    @if($event->description)
                        <p class="text-sm text-on-surface-variant mt-2">{{ $event->description }}</p>
                    @endif
                    <p class="text-xs text-on-surface-variant mt-2">
                        Créé par {{ $event->createur?->name ?? 'Compte supprimé' }} · {{ $event->created_at?->format('d/m/Y H:i') }}
                        @if($event->verified_at)
                            · Vérifié par {{ $event->validePar?->name ?? 'Compte supprimé' }} le {{ $event->verified_at->format('d/m/Y H:i') }}
                        @endif
                    </p>
                </div>
                <span class="px-3 py-1.5 rounded-full text-xs font-bold {{ $event->status->value === 'verified' ? 'bg-tertiary/10 text-tertiary' : ($event->status->value === 'rejected' ? 'bg-error/10 text-error' : 'bg-secondary-container/10 text-secondary-container') }}">
                    {{ match($event->status->value) {
                        'draft' => 'Brouillon',
                        'in_verification' => 'En vérification',
                        'verified' => 'Vérifié',
                        'rejected' => 'Rejeté',
                        default => $event->status->value,
                    } }}
                </span>
            </div>

            @if($event->rejection_reason)
                <div class="mt-4 p-3 rounded-lg bg-error/10 text-error text-sm">
                    <strong>Motif du rejet :</strong> {{ $event->rejection_reason }}
                    <span class="block text-xs mt-1">Par {{ $event->rejetePar?->name ?? 'Compte supprimé' }} · {{ $event->rejected_at?->format('d/m/Y H:i') }}</span>
                </div>
            @endif

            @if($event->sourcesVeille->isNotEmpty())
                <div class="mt-5 pt-4 border-t border-outline-variant/30">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-2">Éléments de veille liés</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($event->sourcesVeille as $sourceVeille)
                            <a href="{{ route('veille.sujets.show', $sourceVeille) }}" class="px-3 py-2 rounded-lg bg-surface-container text-sm font-semibold text-primary-container hover:underline">
                                {{ $sourceVeille->title }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <section class="bg-white rounded-xl border border-outline-variant/30 card-shadow p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-on-primary-fixed">Vérifications</h2>
                    <p class="text-xs text-on-surface-variant mt-1">Les conclusions doivent être saisies et examinées par une personne.</p>
                </div>
                @if($responsableEditorial && $peutValiderEvenement)
                    <form method="POST" action="{{ route('evenements-editoriaux.validate', $event) }}" data-confirm="Valider cet événement éditorial ?">
                        @csrf
                        <button class="px-4 py-2.5 rounded-lg bg-tertiary text-white text-sm font-bold">Valider l’événement</button>
                    </form>
                @endif
            </div>

            @if($responsableEditorial && $event->status->value === 'in_verification')
                <form method="POST" action="{{ route('evenements-editoriaux.reject', $event) }}" class="flex flex-col sm:flex-row gap-2 p-4 rounded-xl bg-error/5 mb-5">
                    @csrf
                    <input name="rejection_reason" required minlength="5" maxlength="2000" class="flex-1 rounded-lg border-outline-variant" placeholder="Motif obligatoire pour rejeter l’événement">
                    <button class="px-4 py-2 rounded-lg bg-error text-white text-sm font-bold">Rejeter l’événement</button>
                </form>
            @endif

            @if($contributeurEditorial && $event->status->value === 'in_verification')
                <form method="POST" action="{{ route('evenements-editoriaux.verifications.store', $event) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3 p-4 rounded-xl bg-surface-container-low mb-5">
                    @csrf
                    <label class="text-sm font-semibold text-on-surface">Type de vérification
                        <input name="type" required maxlength="100" class="mt-1 w-full rounded-lg border-outline-variant" placeholder="Ex. confirmation de source">
                    </label>
                    <label class="text-sm font-semibold text-on-surface">Élément à vérifier
                        <input name="claim" required minlength="5" maxlength="5000" class="mt-1 w-full rounded-lg border-outline-variant" placeholder="Quel fait ou quelle affirmation vérifier ?">
                    </label>
                    <label class="text-sm font-semibold text-on-surface md:col-span-2">Preuve / notes
                        <textarea name="evidence" rows="2" class="mt-1 w-full rounded-lg border-outline-variant"></textarea>
                    </label>
                    <label class="text-sm font-semibold text-on-surface md:col-span-2">Lien vers la preuve
                        <input name="evidence_url" type="url" maxlength="1000" class="mt-1 w-full rounded-lg border-outline-variant" placeholder="https://…">
                    </label>
                    <div class="md:col-span-2">
                        <button class="px-4 py-2 rounded-lg bg-primary-container text-white text-sm font-bold">Ajouter la vérification</button>
                    </div>
                </form>
            @endif

            <div class="space-y-3">
                @forelse($event->verifications as $verification)
                    <article class="rounded-xl border border-outline-variant/40 p-4">
                        <div class="flex flex-wrap justify-between gap-2">
                            <div>
                                <div class="text-xs font-bold uppercase text-primary-container">{{ str_replace('_', ' ', $verification->type) }}</div>
                                <h3 class="font-semibold text-on-primary-fixed mt-1">{{ $verification->claim }}</h3>
                            </div>
                            <span class="text-xs font-bold px-2 py-1 rounded bg-surface-container">{{ match($verification->status->value) {
                                'pending' => 'À examiner',
                                'confirmed' => 'Confirmée',
                                'refuted' => 'Contredite',
                                'inconclusive' => 'Non concluante',
                                default => $verification->status->value,
                            } }}</span>
                        </div>
                        @if($verification->evidence)
                            <p class="text-sm text-on-surface-variant mt-2 whitespace-pre-line">{{ $verification->evidence }}</p>
                        @endif
                        @if($verification->evidence_url)
                            <a href="{{ $verification->evidence_url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-primary-container underline mt-2 inline-block">Consulter la preuve</a>
                        @endif
                        <div class="text-xs text-on-surface-variant mt-2">
                            Ajoutée par {{ $verification->createur?->name ?? 'Compte supprimé' }}
                            @if($verification->checked_at)
                                · examinée par {{ $verification->verifiePar?->name ?? 'Compte supprimé' }} le {{ $verification->checked_at->format('d/m/Y H:i') }}
                            @endif
                        </div>

                        @if($contributeurEditorial && $event->status->value === 'in_verification' && $verification->status->value === 'pending')
                            <form method="POST" action="{{ route('verifications-editoriales.finish', $verification) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4 pt-4 border-t border-outline-variant/30">
                                @csrf
                                @method('PATCH')
                                <label class="text-xs font-semibold text-on-surface">Conclusion humaine
                                    <select name="status" required class="mt-1 w-full rounded-lg border-outline-variant">
                                        <option value="">Choisir une conclusion</option>
                                        <option value="confirmed">Confirmée</option>
                                        <option value="refuted">Contredite</option>
                                        <option value="inconclusive">Non concluante</option>
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-on-surface">Notes / éléments constatés
                                    <input name="evidence" maxlength="20000" class="mt-1 w-full rounded-lg border-outline-variant" value="{{ $verification->evidence }}">
                                </label>
                                <div class="md:col-span-2">
                                    <button class="px-3 py-2 rounded-lg bg-secondary-container text-white text-xs font-bold">Enregistrer mon examen</button>
                                </div>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-on-surface-variant py-4">Aucune vérification enregistrée. L’événement ne peut pas encore être validé.</p>
                @endforelse
            </div>
        </section>

        <section class="bg-white rounded-xl border border-outline-variant/30 card-shadow p-6">
            <h2 class="text-lg font-bold text-on-primary-fixed">Sujets éditoriaux</h2>
            <p class="text-xs text-on-surface-variant mt-1 mb-4">Un événement peut donner lieu à plusieurs sujets et angles.</p>

            @if($contributeurEditorial && $event->status->value !== 'rejected')
                <form method="POST" action="{{ route('evenements-editoriaux.stories.store', $event) }}" class="grid grid-cols-1 gap-3 p-4 rounded-xl bg-surface-container-low mb-5">
                    @csrf
                    <label class="text-sm font-semibold text-on-surface">Titre du sujet
                        <input name="title" required minlength="3" maxlength="255" class="mt-1 w-full rounded-lg border-outline-variant">
                    </label>
                    <label class="text-sm font-semibold text-on-surface">Résumé
                        <textarea name="summary" rows="2" maxlength="10000" class="mt-1 w-full rounded-lg border-outline-variant"></textarea>
                    </label>
                    <label class="text-sm font-semibold text-on-surface">Angle éditorial
                        <textarea name="angle" rows="2" maxlength="10000" class="mt-1 w-full rounded-lg border-outline-variant"></textarea>
                    </label>
                    <div><button class="px-4 py-2 rounded-lg bg-primary-container text-white text-sm font-bold">Créer le sujet</button></div>
                </form>
            @endif

            <div class="space-y-4">
                @forelse($event->sujets as $story)
                    <article class="rounded-xl border border-outline-variant/40 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-on-primary-fixed">{{ $story->title }}</h3>
                                @if($story->sharedDossier)@can('view', $story->sharedDossier)<a class="text-sm text-primary underline inline-flex min-h-11 items-center" href="{{ route('workspace.show', $story->sharedDossier) }}">Ouvrir le dossier partagé</a>@endcan @endif
                                @if($story->angle)
                                    <p class="text-sm text-on-surface-variant mt-1">Angle : {{ $story->angle }}</p>
                                @endif
                                <p class="text-xs text-on-surface-variant mt-2">Statut : {{ str_replace('_', ' ', $story->status->value) }}
                                    @if($story->journalisteAssigne)
                                        · Affecté à {{ $story->journalisteAssigne->prenom }} {{ $story->journalisteAssigne->nom }}
                                    @endif
                                </p>
                                @if($story->assigned_at)
                                    <p class="text-xs text-on-surface-variant mt-1">Affecté par {{ $story->affectePar?->name ?? 'Compte supprimé' }} le {{ $story->assigned_at->format('d/m/Y H:i') }}</p>
                                @endif
                                @if($story->submitted_for_validation_at)
                                    <p class="text-xs text-on-surface-variant mt-1">Soumis par {{ $story->soumisPourValidationPar?->name ?? 'Compte supprimé' }} le {{ $story->submitted_for_validation_at->format('d/m/Y H:i') }}</p>
                                @endif
                                @if($story->verification_exception_reason)
                                    <p class="mt-2 text-xs font-semibold text-error">Dérogation de vérification : {{ $story->verification_exception_reason }}</p>
                                @endif
                                @if($story->rejection_reason)
                                    <p class="mt-2 text-xs font-semibold text-error">Motif du rejet : {{ $story->rejection_reason }} · {{ $story->rejetePar?->name ?? 'Compte supprimé' }}</p>
                                @endif
                            </div>
                            @if($story->sequences->isNotEmpty())
                                <span class="text-xs font-bold px-2 py-1 rounded bg-tertiary/10 text-tertiary">Présent dans {{ $story->sequences->count() }} conducteur(s)</span>
                            @endif
                        </div>

                        @if($responsableEditorial && ($story->status->value === 'draft' || $story->status->value === 'assigned'))
                            <form method="POST" action="{{ route('sujets-editoriaux.assign', $story) }}" class="flex flex-wrap gap-2 mt-4">
                                @csrf
                                @method('PATCH')
                                <select name="journaliste_id" required class="rounded-lg border-outline-variant text-sm">
                                    <option value="">Affecter à un journaliste…</option>
                                    @foreach($journalistes as $journaliste)
                                        <option value="{{ $journaliste->id }}" @selected($story->assigned_journaliste_id === $journaliste->id)>{{ $journaliste->prenom }} {{ $journaliste->nom }}</option>
                                    @endforeach
                                </select>
                                <button class="px-3 py-2 rounded-lg border border-outline-variant text-sm font-semibold">Enregistrer l’affectation</button>
                            </form>
                        @endif

                        @if(($story->status->value === 'assigned') && ($responsableEditorial || (int) $story->assigned_journaliste_id === (int) $journalisteConnecteId))
                            <form method="POST" action="{{ route('sujets-editoriaux.production.start', $story) }}" class="mt-3">
                                @csrf
                                @method('PATCH')
                                <button class="px-3 py-2 rounded-lg bg-primary-container text-white text-xs font-bold">Démarrer la production</button>
                            </form>
                        @elseif($story->status->value === 'in_production' && ($responsableEditorial || (int) $story->assigned_journaliste_id === (int) $journalisteConnecteId))
                            <form method="POST" action="{{ route('sujets-editoriaux.validation.submit', $story) }}" class="mt-3">
                                @csrf
                                @method('PATCH')
                                <button class="px-3 py-2 rounded-lg bg-secondary-container text-white text-xs font-bold">Soumettre à validation</button>
                            </form>
                        @elseif($story->status->value === 'awaiting_validation' && $responsableEditorial)
                            <div class="mt-4 pt-4 border-t border-outline-variant/30">
                                <form method="POST" action="{{ route('sujets-editoriaux.approve', $story) }}" class="space-y-2">
                                    @csrf
                                    <label class="block text-xs font-semibold text-on-surface">Motif de dérogation si l’événement n’est pas vérifié (obligatoire dans ce cas)
                                        <textarea name="verification_exception_reason" maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border-outline-variant" placeholder="Laisser vide si l’événement est vérifié."></textarea>
                                    </label>
                                    <button class="px-3 py-2 rounded-lg bg-tertiary text-white text-xs font-bold">Valider le sujet</button>
                                </form>
                                <form method="POST" action="{{ route('sujets-editoriaux.reject', $story) }}" class="flex flex-col sm:flex-row gap-2 mt-3">
                                    @csrf
                                    <input name="rejection_reason" required minlength="5" maxlength="2000" class="flex-1 rounded-lg border-outline-variant text-sm" placeholder="Motif obligatoire du rejet">
                                    <button class="px-3 py-2 rounded-lg bg-error text-white text-xs font-bold">Rejeter le sujet</button>
                                </form>
                            </div>
                        @endif

                        @if($story->status->value === 'validated' && $responsableEditorial)
                            <form method="POST" action="{{ route('sujets-editoriaux.conducteur.store', $story) }}" class="grid grid-cols-1 md:grid-cols-3 gap-2 mt-4 pt-4 border-t border-outline-variant/30">
                                @csrf
                                <select name="conducteur_id" required class="rounded-lg border-outline-variant text-sm md:col-span-2">
                                    <option value="">Choisir un conducteur de rédaction…</option>
                                    @foreach($conducteurs as $conducteur)
                                        <option value="{{ $conducteur->id }}">{{ $conducteur->nom ?: $conducteur->emission?->nom ?: 'Conducteur' }} · {{ $conducteur->date_edition?->format('d/m/Y') ?? 'Date à définir' }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="duration_seconds" min="1" max="36000" value="120" class="rounded-lg border-outline-variant text-sm" aria-label="Durée en secondes">
                                <button class="px-3 py-2 rounded-lg bg-primary-container text-white text-xs font-bold md:col-span-3">Ajouter au conducteur</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-on-surface-variant py-4">Aucun sujet éditorial créé pour cet événement.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
