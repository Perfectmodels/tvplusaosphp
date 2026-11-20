@extends('layouts.app')
@php
    use App\Enums\CiviliteEnum;
    use App\Enums\FonctionJournalisteEnum;
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Carbon;

    $j = $journaliste;
    if (!($j instanceof \App\Models\Journaliste)) {
        $j = \App\Models\Journaliste::findOrFail($journaliste);
    }

    $nomComplet = e($j->nomComplet ?? ($j->prenom.' '.$j->nom));
    $photoUrl = $j->photoUrl;
    $matricule = e($j->matricule ?? '—');

    $dateEmbauche = $j->date_embauche ? Carbon::parse($j->date_embauche) : null;
    $anciennete = $dateEmbauche ? $dateEmbauche->diffForHumans(['parts' => 2, 'short' => false]) : '—';

    // Compteurs RÉELS de sujets — calculés par JournalisteController::show().
    // ($stats['total'] = 0 pour un journaliste sans sujet attribué manuellement)
    $stats = $stats ?? ['total' => 0, 'avenir' => 0, 'enCours' => 0, 'diffuses' => 0, 'diffusePct' => 0];
    $sujetsResponsables = $sujetsResponsables ?? collect();

    $poleLabel = $j->pole?->label() ?? '—';
    $poleSansIcon = trim(preg_replace('/^[^\p{L}]+/u', '', $poleLabel));

    $pageTitle = 'Fiche journaliste';
    $pageSubtitle = 'Détail du profil · Ressources humaines';
    $showGlobalSearch = false;

    $headerActions = [
        ['label' => 'Retour', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('journalistes.index'), 'variant' => 'ghost'],
    ];
    if (Gate::check('update', $j)) {
        $headerActions[] = ['label' => 'Modifier', 'icon' => 'fa-solid fa-pen-to-square', 'href' => route('journalistes.edit', $j), 'variant' => 'ghost'];
    }
    if (!$j->is_presentateur && Gate::check('promouvoir-presentateur', $j)) {
        $headerActions[] = ['label' => 'Promouvoir présentateur', 'icon' => 'fa-solid fa-microphone-lines', 'type' => 'button', 'button_type' => 'submit', 'form' => "promote-form-{$j->id}", 'variant' => 'primary'];
    }
    if ($j->is_presentateur && Gate::check('retrograder-presentateur', $j)) {
        $headerActions[] = ['label' => 'Démettre présentateur', 'icon' => 'fa-solid fa-user-minus', 'type' => 'button', 'button_type' => 'submit', 'form' => "demote-form-{$j->id}", 'variant' => 'ghost'];
    }
@endphp
@section('content')
@php
    $credentials = session('journaliste_credentials');
@endphp
@if($credentials && ($credentials['journaliste_id'] ?? null) === $j->id)
    <div class="max-w-6xl mx-auto mb-6 p-5 rounded-xl border border-primary bg-primary-container/10" role="status">
        <h2 class="font-bold text-primary">Compte créé — informations de première connexion</h2>
        <p class="mt-2">Adresse e-mail : <strong>{{ $credentials['email'] }}</strong></p>
        <p class="mt-2">Mot de passe temporaire : <code class="font-mono font-bold select-all">{{ $credentials['password'] }}</code></p>
        <p class="mt-3 text-sm text-secondary">Communiquez ces informations au journaliste. Ce mot de passe n’est affiché qu’après la création et devra être changé à la première connexion.</p>
    </div>
@endif

<form id="promote-form-{{ $j->id }}" method="POST" action="{{ route('journalistes.promouvoir-presentateur', $j) }}" class="hidden">@csrf</form>
<form id="demote-form-{{ $j->id }}" method="POST" action="{{ route('journalistes.retrograder-presentateur', $j) }}" class="hidden">@csrf</form>

<div class="bg-gradient-to-r from-primary via-primary-container to-primary/80 px-8 py-10">
    <div class="max-w-6xl mx-auto flex flex-col lg:flex-row gap-6 lg:items-end">
        <div class="flex items-end gap-5">
            <div class="w-28 h-28 rounded-2xl border-4 border-white/20 shadow-xl overflow-hidden bg-surface-container flex-shrink-0">
                <img src="{{ $photoUrl }}" class="w-full h-full object-cover" alt="{{ $nomComplet }}">
            </div>
            <div class="text-white pb-2">
                <p class="text-xs text-white/70 uppercase tracking-widest">Matricule <span class="font-mono font-semibold text-white/90">{{ $matricule }}</span></p>
                <h1 class="text-3xl font-bold mt-1 leading-tight">{{ $nomComplet }}</h1>
                <div class="flex items-center gap-2 mt-2 flex-wrap">
                    @if($j->fonction)
                        <span class="{{ $j->fonction->badgeClass() }}">
                            @if($j->fonction === FonctionJournalisteEnum::ChefRubrique)
                                <i class="fa-solid fa-crown text-xs mr-1"></i>
                            @endif
                            {{ preg_replace('/^[^\s]+\s+/u', '', $j->fonction->label()) }}
                        </span>
                    @endif
                    @if($j->pole)
                        <span class="px-2.5 py-1 rounded-full bg-white/15 border border-white/20 text-xs font-semibold">Pôle {{ $poleSansIcon }}</span>
                    @endif
                    @if($j->is_presentateur)
                        <span class="px-2.5 py-1 rounded-full bg-amber-400/30 border border-amber-200/40 text-xs font-semibold">🎙️ Présentateur</span>
                    @endif
                    @if($j->statut)
                        <span class="{{ $j->statut->badgeClass() }}">
                            <i class="{{ $j->statut->badgeIcon() }} mr-1"></i>
                            {{ preg_replace('/^[^\s]+\s+/u', '', $j->statut->label()) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="lg:ml-auto flex flex-wrap gap-x-8 gap-y-4 text-white/90 pb-2">
            <div>
                <p class="text-xs uppercase tracking-widest text-white/90">Ancienneté</p>
                <p class="text-xl font-bold mt-0.5">{{ $anciennete }}</p>
            </div>
            <div class="w-px bg-white/20 hidden lg:block"></div>
            <div>
                <p class="text-xs uppercase tracking-widest text-white/90">Sujets traités</p>
                <p class="text-xl font-bold mt-0.5">{{ $stats['total'] }}</p>
            </div>
            <div class="w-px bg-white/20 hidden lg:block"></div>
            <div>
                <p class="text-xs uppercase tracking-widest text-white/90">Sujets en cours</p>
                <p class="text-xl font-bold mt-0.5">{{ $stats['enCours'] }}</p>
            </div>
        </div>
    </div>
</div>

<div class="px-8 max-w-6xl mx-auto">
    <div class="mobile-wrap-tabs border-b border-outline-variant flex items-center gap-1 -mt-px bg-surface-container-lowest sticky top-0 z-10 overflow-x-auto scrollbar-hide">
        <button class="tab-btn active shrink-0 whitespace-nowrap" data-tab="profil">
            <i class="fa-regular fa-id-card"></i> Profil
        </button>
        <button class="tab-btn shrink-0 whitespace-nowrap" data-tab="sujets">
            <i class="fa-regular fa-newspaper"></i> Sujets <span class="ml-1 px-1.5 py-0.5 rounded-full bg-primary/10 text-primary text-xs font-bold">{{ $stats['total'] }}</span>
        </button>
        <button class="tab-btn shrink-0 whitespace-nowrap" data-tab="historique">
            <i class="fa-regular fa-clock"></i> Historique
        </button>
        <div class="ml-auto hidden md:flex items-center gap-2 pr-1">
            <div class="flex items-center gap-1 text-xs text-secondary">
                <i class="fa-solid fa-circle text-green-500 text-xs"></i>
                Compte actif
                @if($j->date_embauche)
                    • Embauche : {{ Carbon::parse($j->date_embauche)->translatedFormat('d M Y') }}
                @endif
            </div>
        </div>
    </div>

    <div id="tab-profil" class="tab-content py-8 space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between bg-surface-container">
                        <h3 class="font-bold uppercase tracking-wide text-sm text-primary">Identité</h3>
                        @can('update', $j)
                            <a href="{{ route('journalistes.edit', $j) }}" class="text-xs text-secondary hover:text-primary transition-colors px-2 py-2 inline-flex items-center min-h-[44px]"><i class="fa-solid fa-pen mr-1"></i> Éditer</a>
                        @endcan
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <p class="info-label">Civilité</p>
                            <p class="info-value">{{ $j->civilite?->label() ?? ($j->civilite ?? '—') }}</p>
                        </div>
                        <div>
                            <p class="info-label">Nom / Prénom</p>
                            <p class="info-value font-semibold">{{ e($j->nom) }} {{ e($j->prenom) }}</p>
                        </div>
                        <div>
                            <p class="info-label">Date de naissance</p>
                            <p class="info-value">
                                @if($j->date_naissance)
                                    {{ Carbon::parse($j->date_naissance)->translatedFormat('d M Y') }}
                                    <span class="text-secondary text-xs">({{ Carbon::parse($j->date_naissance)->age }} ans)</span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="info-label">Lieu de naissance</p>
                            <p class="info-value">{{ e($j->lieu_naissance) ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between bg-surface-container">
                        <h3 class="font-bold uppercase tracking-wide text-sm text-primary">Poste &amp; Affectation</h3>
                        @can('update', $j)
                            <a href="{{ route('journalistes.edit', $j) }}" class="text-xs text-secondary hover:text-primary transition-colors px-2 py-2 inline-flex items-center min-h-[44px]"><i class="fa-solid fa-pen mr-1"></i> Éditer</a>
                        @endcan
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <p class="info-label">Fonction</p>
                            <p class="info-value">
                                @if($j->fonction)
                                    <span class="{{ $j->fonction->badgeClass() }} inline-block">{{ preg_replace('/^[^\s]+\s+/u', '', $j->fonction->label()) }}</span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="info-label">Pôle / Rubrique</p>
                            <p class="info-value font-semibold text-primary">{{ $poleLabel }}</p>
                        </div>
                        <div>
                            <p class="info-label">Statut professionnel</p>
                            <p class="info-value">
                                @if($j->statut)
                                    <span class="{{ $j->statut->badgeClass() }} inline-block"><i class="{{ $j->statut->badgeIcon() }} mr-1"></i> {{ preg_replace('/^[^\s]+\s+/u', '', $j->statut->label()) }}</span>
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="info-label">Niveau / Grade</p>
                            <p class="info-value">
                                {{ $j->grade ?: '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="info-label">Date d'embauche</p>
                            <p class="info-value">{{ $dateEmbauche ? $dateEmbauche->translatedFormat('d M Y') : '—' }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="info-label">Spécialités &amp; Domaines d'expertise</p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @forelse($j->specialites ?? [] as $tag)
                                    <span class="tag">{{ e($tag) }}</span>
                                @empty
                                    <span class="text-xs text-secondary">Aucune spécialité renseignée</span>
                                @endforelse
                            </div>
                        </div>
                        @if($j->is_presentateur)
                            <div class="md:col-span-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <p class="info-label text-amber-700">🎙️ Présentateur depuis</p>
                                <p class="info-value font-semibold text-amber-900 mt-0.5">
                                    {{ $j->date_promotion_presentateur ? Carbon::parse($j->date_promotion_presentateur)->translatedFormat('d M Y') : '—' }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between bg-surface-container">
                        <h3 class="font-bold uppercase tracking-wide text-sm text-primary">Coordonnées</h3>
                        @can('update', $j)
                            <a href="{{ route('journalistes.edit', $j) }}" class="text-xs text-secondary hover:text-primary transition-colors px-2 py-2 inline-flex items-center min-h-[44px]"><i class="fa-solid fa-pen mr-1"></i> Éditer</a>
                        @endcan
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                                <div>
                                    <p class="info-label">Email professionnel</p>
                                    <p class="info-value text-primary">{{ e($j->email) ?: '—' }}</p>
                                    @if($j->email)
                                        <p class="text-xs text-secondary mt-0.5"><i class="fa-solid fa-check-double text-green-600 mr-1"></i> Compte lié</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-lg bg-green-100 text-green-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <div>
                                    <p class="info-label">Téléphone</p>
                                    <p class="info-value font-mono">{{ e($j->telephone) ?: '—' }}</p>
                                    @if($j->telephone)
                                        <p class="text-xs text-secondary mt-0.5">Mobile professionnel</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-start gap-3 md:col-span-2">
                                <div class="w-9 h-9 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div>
                                    <p class="info-label">Adresse</p>
                                    <p class="info-value">{{ e($j->adresse) ?: '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container">
                        <h3 class="font-bold uppercase tracking-wide text-sm text-primary">Compte utilisateur</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        @if($j->user)
                            <div class="flex items-center justify-between gap-3">
                                <span class="info-label">Statut compte</span>
                                <span class="badge {{ $j->user->is_active ? 'badge-green' : 'badge-gray' }}">{{ $j->user->is_active ? 'Activé' : 'Désactivé' }}</span>
                            </div>
                            <div>
                                <p class="info-label">Adresse de connexion</p>
                                <p class="font-mono text-sm break-all">{{ $j->user->email }}</p>
                            </div>
                            <div>
                                <p class="info-label">Rôles du compte</p>
                                <p class="text-sm">{{ $j->user->roles->pluck('name')->map(fn ($role) => \Illuminate\Support\Str::headline($role))->implode(', ') ?: 'Aucun rôle attribué' }}</p>
                            </div>
                            @if($j->user->password_changed_at === null)
                                <p class="text-sm text-secondary">Changement du mot de passe requis à la première connexion.</p>
                            @endif
                            @can('viewAny', \App\Models\User::class)
                                <a href="{{ route('users.edit', $j->user) }}" class="btn btn-secondary">Gérer le compte</a>
                            @endcan
                        @else
                            <p class="text-sm text-secondary">Aucun compte utilisateur lié. Cette fiche ne permet pas encore de se connecter.</p>
                            @can('update', $j)
                                <a href="{{ route('journalistes.edit', $j) }}" class="btn btn-secondary">Créer le compte utilisateur</a>
                            @endcan
                        @endif
                    </div>
                </div>

                <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant bg-surface-container">
                        <h3 class="font-bold uppercase tracking-wide text-sm text-primary">Charge actuelle</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">Sujets en cours</span>
                            <span class="font-bold text-primary text-lg">{{ $stats['enCours'] }}</span>
                        </div>
                        <div class="w-full h-2 bg-surface-container rounded-full overflow-hidden">
                            <div class="h-full bg-primary rounded-full" style="width: {{ $stats['diffusePct'] }}%"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
                            <div class="flex items-center justify-between"><span class="text-secondary">À faire</span><span class="font-bold text-blue-600">{{ $stats['avenir'] }}</span></div>
                            <div class="flex items-center justify-between"><span class="text-secondary">En cours</span><span class="font-bold text-orange-600">{{ $stats['enCours'] }}</span></div>
                            <div class="flex items-center justify-between"><span class="text-secondary">Diffusés</span><span class="font-bold text-green-600">{{ $stats['diffuses'] }}</span></div>
                            <div class="flex items-center justify-between"><span class="text-secondary">Total</span><span class="font-bold">{{ $stats['total'] }}</span></div>
                        </div>
                        <p class="text-xs text-secondary">Progression : {{ $stats['diffuses'] }}/{{ $stats['total'] }} diffusé(s) ({{ $stats['diffusePct'] }}%)</p>
                    </div>
                </div>

                <div class="bg-primary-container/10 border border-primary-container/30 rounded-xl overflow-hidden">
                    <div class="p-5">
                        <h3 class="font-bold uppercase tracking-wide text-sm text-primary mb-3">Notes admin</h3>
                        @if($j->notes_admin)
                            <p class="text-xs text-secondary leading-relaxed whitespace-pre-wrap">{{ e($j->notes_admin) }}</p>
                        @else
                            <p class="text-xs text-secondary italic leading-relaxed">Aucune note administrative saisie.</p>
                        @endif
                    </div>
                </div>

                @if(!$j->is_presentateur && Gate::check('promouvoir-presentateur', $j))
                    <form method="POST" action="{{ route('journalistes.promouvoir-presentateur', $j) }}"
                          data-confirm="Promouvoir {{ $nomComplet }} au statut de Présentateur ?"
                          data-confirm-text="Ce journaliste pourra présenter les émissions.">
                        @csrf
                        <button type="submit" class="w-full px-4 py-3 bg-amber-500 text-white rounded-xl hover:bg-amber-600 transition-colors font-semibold shadow-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-microphone-lines"></i> Nommer Présentateur
                        </button>
                    </form>
                @endif
                @if($j->is_presentateur && Gate::check('retrograder-presentateur', $j))
                    <form method="POST" action="{{ route('journalistes.retrograder-presentateur', $j) }}"
                          data-confirm="Rétrograder {{ $nomComplet }} ?"
                          data-confirm-text="Le statut Présentateur sera retiré.">
                        @csrf
                        <button type="submit" class="w-full px-4 py-3 border border-outline-variant text-secondary rounded-xl hover:bg-surface-container transition-colors font-semibold flex items-center justify-center gap-2">
                            <i class="fa-solid fa-user-minus"></i> Retirer statut Présentateur
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div id="tab-sujets" class="tab-content py-8 space-y-6 hidden">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-4">
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">Total sujets</p>
                <p class="text-2xl font-bold text-primary">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-4">
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">En cours</p>
                <p class="text-2xl font-bold text-orange-600">{{ $stats['enCours'] }}</p>
            </div>
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-4">
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">Diffusés</p>
                <p class="text-2xl font-bold text-green-600">{{ $stats['diffuses'] }}</p>
            </div>
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-4">
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">À venir</p>
                <p class="text-2xl font-bold text-blue-600">{{ $stats['avenir'] }}</p>
            </div>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
            <div class="p-4 border-b border-outline-variant bg-surface-container-low">
                <h3 class="font-bold uppercase tracking-wide text-sm text-primary">Sujets rattachés — Historique conducteurs</h3>
            </div>
            <div class="overflow-x-auto">
                <table data-mobile-cards class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant">
                            <th class="p-4 table-header font-semibold">Date / Émission</th>
                            <th class="p-4 table-header font-semibold">Titre du sujet</th>
                            <th class="p-4 table-header font-semibold">Type</th>
                            <th class="p-4 table-header font-semibold">Rôle</th>
                            <th class="p-4 table-header font-semibold">Statut</th>
                            <th class="p-4 table-header font-semibold">Durée</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sujetsResponsables as $sujet)
                            @php
                                $emissionLabel = $sujet->emission?->nom
                                    ?? $sujet->conducteur?->emission?->nom
                                    ?? '—';
                                $dateSujet = $sujet->conducteur?->date_edition
                                    ? Carbon::parse($sujet->conducteur->date_edition)->translatedFormat('d M Y')
                                    : $sujet->created_at?->translatedFormat('d M Y');
                                $duree = $sujet->duration_seconds ? sprintf('%d:%02d', intdiv((int) $sujet->duration_seconds, 60), (int) $sujet->duration_seconds % 60) : '—';
                            @endphp
                            <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors">
                                <td class="p-4">
                                    <p class="font-semibold">{{ $dateSujet ?? '—' }}</p>
                                    <p class="text-xs text-secondary">{{ $emissionLabel }}</p>
                                </td>
                                <td class="p-4">
                                    <p class="font-semibold text-sm">{{ $sujet->title }}</p>
                                </td>
                                <td class="p-4">
                                    <span class="tag text-xs">{{ $sujet->type?->label() ?? '—' }}</span>
                                </td>
                                <td class="p-4 text-xs text-secondary">Journaliste responsable</td>
                                <td class="p-4">
                                    @php $statutTerrain = $sujet->statut_terrain?->badgeClass() ?? 'badge badge-gray'; @endphp
                                    <span class="text-xs px-2 py-1 rounded-full {{ $statutTerrain }}">
                                        {{ $sujet->statut_terrain?->label() ?? '—' }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-secondary">{{ $duree }}</td>
                            </tr>
                        @empty
                            <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors">
                                <td class="p-4">
                                    <p class="font-semibold">—</p>
                                    <p class="text-xs text-secondary">Aucun conducteur rattaché</p>
                                </td>
                                <td class="p-4 text-secondary text-xs" colspan="5">Aucun sujet attribué pour l'instant. Les sujets apparaissent ici après attribution manuelle dans un conducteur de rédaction.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="tab-historique" class="tab-content py-8 hidden">
        <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant bg-surface-container flex items-center justify-between">
                <h3 class="font-bold uppercase tracking-wide text-sm text-primary">Historique des actions</h3>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <p>Fiche créée le {{ $j->created_at?->translatedFormat('d M Y à H:i') ?? '—' }}.</p>
                <p>Dernière mise à jour le {{ $j->updated_at?->translatedFormat('d M Y à H:i') ?? '—' }}.</p>
                @can('viewAny', \App\Models\ActivityLog::class)
                    <a href="{{ route('admin.activity-logs') }}" class="text-primary underline">Consulter le journal d’activité</a>
                @endcan
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.remove('hidden');
    });
});
</script>
@endsection
