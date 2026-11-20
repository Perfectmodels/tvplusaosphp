@extends('layouts.app')
@php
    use App\Models\Journaliste;
    use App\Enums\PoleRubriqueEnum;
    use App\Enums\StatutJournalisteEnum;
    use Illuminate\Support\Facades\Gate;

    $q = request('q', '');
    $poleFilter = request('pole', '');
    $statutFilter = request('statut', '');

    // La barre de suppression en masse n'apparaît que si l'utilisateur
    // a le droit de supprimer au moins un des journalistes affichés.
    $peutSupprimerEnMasse = $journalistes->getCollection()->contains(
        fn (Journaliste $j) => Gate::check('delete', $j)
    );

    $pageTitle = 'Gestion des journalistes';
    $pageSubtitle = 'Ressources humaines • Rédaction';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Exporter', 'icon' => 'fa-solid fa-file-export', 'href' => '#', 'variant' => 'ghost'],
    ];
    if (Gate::check('create', Journaliste::class)) {
        $headerActions[] = ['label' => 'Ajouter journaliste', 'icon' => 'fa-solid fa-plus', 'href' => route('journalistes.create'), 'variant' => 'primary'];
    }
@endphp
@section('content')

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">Total journalistes</p>
                <p class="text-3xl font-bold text-primary">{{ $totalJ }}</p>
                <p class="text-xs text-secondary mt-2">{{ $journalistes->firstItem() ?? 0 }}–{{ $journalistes->lastItem() ?? 0 }} affichés</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center text-blue-700">
                <i class="fa-solid fa-users text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">En mission</p>
                <p class="text-3xl font-bold text-green-700">{{ $enMission }}</p>
                <p class="text-xs text-secondary mt-2">{{ $totalJ > 0 ? round($enMission * 100 / $totalJ) : 0 }}% de l'effectif</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center text-green-700">
                <i class="fa-solid fa-briefcase text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">En congé</p>
                <p class="text-3xl font-bold text-orange-700">{{ $enConge }}</p>
                <p class="text-xs text-secondary mt-2">{{ $totalJ > 0 ? round($enConge * 100 / $totalJ) : 0 }}% de l'effectif</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center text-orange-700">
                <i class="fa-solid fa-umbrella-beach text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">Sujets en cours (jour)</p>
                <p class="text-3xl font-bold text-primary">{{ $sujetsEnCours }}</p>
                <p class="text-xs text-secondary mt-2"><i class="fa-solid fa-spinner text-blue-600 mr-1"></i>{{ $sujetsEnProduction }} en production</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center text-purple-700">
                <i class="fa-solid fa-newspaper text-lg"></i>
            </div>
        </div>
    </div>
</div>

<form method="GET" action="{{ route('journalistes.index') }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col {{ $peutSupprimerEnMasse ? 'mb-4' : 'mb-8' }}">
    <div class="p-4 border-b border-outline-variant flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center bg-surface-container-low">
        <div class="flex flex-col md:flex-row gap-3 w-full lg:w-auto flex-1">
            <div class="relative flex-1 md:max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                <input type="text" name="q" value="{{ e($q) }}" placeholder="Rechercher (nom, matricule, spécialité...)" class="w-full pl-10 pr-4 py-2 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none"/>
            </div>
            <select name="pole" class="px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                <option value="">Tous les pôles</option>
                @foreach(PoleRubriqueEnum::cases() as $e)
                    <option value="{{ $e->value }}" @selected($poleFilter === $e->value)>{{ $e->label() }}</option>
                @endforeach
            </select>
            <select name="statut" class="px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                <option value="">Tous les statuts</option>
                @foreach(StatutJournalisteEnum::cases() as $e)
                    <option value="{{ $e->value }}" @selected($statutFilter === $e->value)>{{ preg_replace('/^[^\s]+\s+/u', '', $e->label()) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="w-11 h-11 flex items-center justify-center border border-outline rounded-lg text-secondary hover:bg-surface-container transition-colors" title="Filtrer">
                <i class="fa-solid fa-filter"></i>
            </button>
            <a href="{{ route('journalistes.index') }}" class="w-11 h-11 flex items-center justify-center border border-outline rounded-lg text-secondary hover:bg-surface-container transition-colors" title="Réinitialiser">
                <i class="fa-solid fa-arrows-rotate"></i>
            </a>
        </div>
    </div>
</form>

{{-- ====================================================================
     SUPPRESSION EN MASSE — formulaire autonome (jamais imbriqué).
     Les cases sont rattachées via l'attribut HTML5 `form="bulk-journalistes"`.
     ==================================================================== --}}
@if($journalistes->contains(fn ($item) => auth()->user()->can('delete', $item)))
    @include('_partials.bulk-delete-toolbar', ['resource' => 'journalistes'])
@endif


<div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden mb-8">
    <div class="overflow-x-auto">
        <table data-mobile-cards class="w-full text-left border-collapse">
            <thead>
            <tr class="border-b border-outline-variant bg-surface-container-lowest">
                @if($peutSupprimerEnMasse)
                <th class="p-4 table-header w-10">
                    <span class="sr-only">Sélection</span>
                </th>
                @endif
                <th class="p-4 table-header font-semibold">Photo</th>
                <th class="p-4 table-header font-semibold">Matricule</th>
                <th class="p-4 table-header font-semibold">Nom / Prénom</th>
                <th class="p-4 table-header font-semibold">Fonction</th>
                <th class="p-4 table-header font-semibold">Pôle (Rubrique)</th>
                <th class="p-4 table-header font-semibold">Spécialités</th>
                <th class="p-4 table-header font-semibold">Statut</th>
                <th class="p-4 table-header font-semibold">Contact</th>
                <th class="p-4 table-header font-semibold text-center">Sujets attribués</th>
                <th class="p-4 table-header font-semibold text-center w-32">Actions</th>
            </tr>
            </thead>
            <tbody data-bulk-list="bulk-journalistes">
            @forelse($journalistes as $j)
                @php
                    // Compteurs RÉELS (avecCount côté contrôleur — pas de N+1)
                    $chargeN = (int) ($j->sujets_count ?? 0);
                    $chargeDiff = (int) ($j->sujets_diffuses ?? 0);
                    $chargePct = $chargeN > 0 ? (int) round($chargeDiff * 100 / $chargeN) : 0;
                @endphp
                <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors group">
                    @if($peutSupprimerEnMasse)
                    <td class="p-4">
                        @can('delete', $j)
                        @include('_partials.bulk-delete-checkbox', ['resource' => 'journalistes', 'record' => $j, 'label' => $j->nomComplet])
                        @endcan
                    </td>
                    @endif
                    <td class="p-4">
                        <img src="{{ $j->photoUrl }}" class="w-10 h-10 rounded-full object-cover border-2 border-surface-container" alt="{{ e($j->nomComplet) }}">
                    </td>
                    <td class="p-4 font-mono text-xs text-secondary">{{ $j->matricule }}</td>
                    <td class="p-4">
                        <p class="font-semibold">{{ $j->nomComplet }}</p>
                        <p class="text-xs text-secondary">
                            @if($j->is_presentateur)
                                🎙️ Présentateur
                            @else
                                {{ $j->grade ?: '—' }}
                            @endif
                        </p>
                    </td>
                    <td class="p-4">
                        <span class="{{ $j->fonction?->badgeClass() ?? 'badge badge-gray' }}">{{ $j->fonction?->label() ?? '—' }}</span>
                    </td>
                    <td class="p-4">
                        <span class="font-medium text-primary">{{ $j->pole?->label() ?? '—' }}</span>
                    </td>
                    <td class="p-4">
                        <div class="flex flex-wrap gap-1">
                            @foreach(array_slice($j->specialites ?? [], 0, 3) as $tag)
                                <span class="tag text-xs">{{ $tag }}</span>
                            @endforeach
                            @if(count($j->specialites ?? []) > 3)
                                <span class="tag text-xs">+{{ count($j->specialites) - 3 }}</span>
                            @endif
                            @if(empty($j->specialites))
                                <span class="text-xs text-secondary">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="p-4">
                        <span class="{{ $j->statut?->badgeClass() ?? 'badge badge-gray' }}">
                            <i class="{{ $j->statut?->badgeIcon() ?? 'fa-solid fa-circle-question' }} mr-1"></i>
                            {{ preg_replace('/^[^\s]+\s+/u', '', $j->statut?->label() ?? '—') }}
                        </span>
                    </td>
                    <td class="p-4">
                        @if(!empty($j->email))
                            <p class="text-xs"><i class="fa-solid fa-envelope text-secondary mr-1"></i>{{ e($j->email) }}</p>
                        @endif
                        @if(!empty($j->telephone))
                            <p class="text-xs mt-1"><i class="fa-solid fa-phone text-secondary mr-1"></i>{{ e($j->telephone) }}</p>
                        @endif
                        @if(empty($j->email) && empty($j->telephone))
                            <p class="text-xs text-secondary">—</p>
                        @endif
                    </td>
                    <td class="p-4 text-center">
                        <div class="flex flex-col items-center gap-1">
                            <div class="flex items-center gap-1 text-xs font-semibold text-primary">{{ $chargeN }} sujet(s)</div>
                            <div class="w-16 h-1.5 bg-surface-container rounded-full overflow-hidden" title="{{ $chargeDiff }} diffusé(s) sur {{ $chargeN }}">
                                <div class="h-full bg-primary rounded-full" style="width: {{ $chargePct }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="p-4 text-center">
                        <div class="flex gap-3 justify-center">
                            <a href="{{ route('journalistes.show', $j) }}" class="w-11 h-11 flex items-center justify-center rounded-md text-secondary hover:bg-surface-container hover:text-primary transition-colors" title="Voir" aria-label="Voir">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            @can('update', $j)
                            <a href="{{ route('journalistes.edit', $j) }}" class="w-11 h-11 flex items-center justify-center rounded-md text-secondary hover:bg-surface-container hover:text-primary transition-colors" title="Modifier" aria-label="Modifier">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            @endcan
                            @can('delete', $j)
                            <form method="POST" action="{{ route('journalistes.destroy', $j) }}"
                                  data-confirm-delete="Supprimer {{ e($j->nomComplet) }} ?"
                                  data-confirm-delete-text="Cette action est irréversible.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-11 h-11 flex items-center justify-center rounded-md text-secondary hover:bg-error-container hover:text-error transition-colors" title="Supprimer" aria-label="Supprimer">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $peutSupprimerEnMasse ? 11 : 10 }}" class="p-10 text-center text-sm text-secondary">Aucun journaliste trouvé.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($journalistes->hasPages())
        <div class="p-4 border-t border-outline-variant bg-surface-container-low">
            {{ $journalistes->links() }}
        </div>
    @endif
</div>


@endsection
