@extends('layouts.app')
@php
    $pageTitle = $emission->nom;
    $pageSubtitle = 'Fiche émission · Détails complets & équipe';
    $showGlobalSearch = true;
    $headerActions = [];
    if (Illuminate\Support\Facades\Gate::check('update', $emission)) {
        $headerActions[] = ['label' => 'Modifier', 'icon' => 'fa-solid fa-pen', 'href' => route('emissions.edit', $emission), 'variant' => 'outline'];
    }
    if ($emission->type === \App\Enums\TypeEmissionEnum::Debat
        && Illuminate\Support\Facades\Gate::check('create', [\App\Models\Sondage::class, $emission])) {
        $headerActions[] = ['label' => 'Sondage en direct', 'icon' => 'fa-solid fa-chart-bar', 'href' => route('sondages.create', $emission), 'variant' => 'outline'];
    }
@endphp
@section('content')
    @php
        $coul7 = Str::limit($emission->couleur_habillage ?? ($emission->theme_color ?? '#0054cd'), 7, '');
    @endphp
    <!-- HERO -->
    <div class="card-surface overflow-hidden -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8 mb-6 !rounded-none border-x-0 border-t-0">
        <div class="h-2 w-full" style="background:{{ $coul7 }};"></div>
        <div class="p-8">
            <div class="flex items-start gap-6 flex-wrap">
                <div class="w-24 h-24 rounded-2xl flex items-center justify-center text-white text-4xl font-black shadow-inner shrink-0" style="background:linear-gradient(135deg,{{ $coul7 }},{{ $coul7 }}cc);">
                    {{ $emission->initiales }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                        {!! $emission->type?->badge() ?? '' !!}
                        {!! $emission->statut?->badge() ?? '' !!}
                        @if($emission->categorie)<span class="badge-purple text-xs px-2.5 py-1 rounded-md font-medium">{{ $emission->categorie }}</span>@endif
                        @if($emission->cible_audience)<span class="badge-indigo text-xs px-2.5 py-1 rounded-md font-medium">{{ $emission->cible_audience }}</span>@endif
                    </div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-1">{{ $emission->nom }}</h2>
                    @if($emission->sous_titre)<p class="text-lg text-slate-500 mb-3">{{ $emission->sous_titre }}</p>@endif
                    @if($emission->description)<p class="text-sm text-slate-600 max-w-3xl leading-relaxed">{{ $emission->description }}</p>@endif
                </div>
                <div class="grid grid-cols-2 gap-3 sm:ml-auto w-full sm:w-auto">
                    <div class="card-surface !p-3 text-center min-w-[100px]">
                        <div class="text-xs uppercase text-slate-400 tracking-wider mb-1">Horaire</div>
                        <div class="text-lg font-bold text-slate-900">{{ $emission->horaireLabel }}</div>
                    </div>
                    <div class="card-surface !p-3 text-center min-w-[100px]">
                        <div class="text-xs uppercase text-slate-400 tracking-wider mb-1">Durée</div>
                        <div class="text-lg font-bold text-slate-900">{{ $emission->duree_minutes }} min</div>
                    </div>
                    <div class="card-surface !p-3 text-center min-w-[100px]">
                        <div class="text-xs uppercase text-slate-400 tracking-wider mb-1">Conducteurs</div>
                        <div class="text-lg font-bold text-primary">{{ $emission->nombreConducteurs }}</div>
                    </div>
                    <div class="card-surface !p-3 text-center min-w-[100px]">
                        <div class="text-xs uppercase text-slate-400 tracking-wider mb-1">Jours</div>
                        <div class="text-sm font-bold text-slate-900">
                            @if($emission->joursCourts)
                                @foreach($emission->joursCourts as $j)<span class="badge-blue text-xs px-2 py-1 rounded-md mr-0.5">{{ $j }}</span>@endforeach
                            @else <span class="text-slate-400 text-xs italic">—</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <!-- BARRE D'ACTIONS PRINCIPALES -->
            <div class="mt-6 pt-6 border-t border-slate-100 flex items-center gap-3 flex-wrap">
                <a href="{{ route('emissions.index') }}" class="btn-ghost"><i class="fa-solid fa-arrow-left mr-2"></i> Retour au catalogue</a>
                @can('update', $emission)
                    <a href="{{ route('emissions.edit', $emission) }}" class="btn-primary"><i class="fa-solid fa-pen mr-2"></i> Modifier l'émission</a>
                @endcan
                @can('create', \App\Models\Conducteur::class)
                    <button class="btn-outline"><i class="fa-solid fa-plus mr-2"></i> Nouveau conducteur</button>
                @endcan
                @can('delete', $emission)
                    <form method="POST" action="{{ route('emissions.destroy', $emission) }}"
                          data-confirm-delete="Supprimer définitivement l'émission « {{ e($emission->nom) }} » ?"
                          data-confirm-delete-text="Cette action est irréversible."
                          class="ml-auto">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full !border-error-container !text-error hover:!bg-error-container flex items-center gap-2 border rounded-lg px-4 py-2 text-sm font-medium transition-colors">
                            <i class="fa-solid fa-trash-can"></i> Supprimer l'émission
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- INFO TECHNIQUE -->
        <div class="card-surface">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2 mb-4">
                <i class="fa-solid fa-circle-info text-primary"></i>
                <h3 class="font-bold text-slate-900">Informations techniques</h3>
            </div>
            <dl class="divide-y divide-slate-100">
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Format image</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $emission->format_image ?? '16:9' }}</dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Qualité</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $emission->qualite ?? 'HD 1080p' }}</dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Fuseau horaire</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $emission->fuseau_horaire ?? 'Africa/Libreville' }}</dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Police titres</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $emission->police_titres ?? 'Inter' }}</dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Couleur</dt>
                    <dd class="text-sm font-medium text-slate-800 flex items-center gap-2"><span class="w-4 h-4 rounded" style="background:{{ $coul7 }};"></span>{{ $coul7 }}</dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Date lancement</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $emission->date_lancement?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Date fin</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $emission->date_fin?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Langue(s)</dt>
                    <dd class="text-sm text-slate-800">
                        @forelse($emission->langues ?? [] as $l)<span class="badge-blue text-xs px-2 py-1 rounded-md mr-1 font-medium">{{ $l }}</span>@empty — @endforelse
                    </dd>
                </div>
                <div class="px-6 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <dt class="text-xs uppercase text-slate-400 tracking-wider">Chef d'édition</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $emission->chefEdition?->name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <!-- PRESENTATEURS -->
        <div class="card-surface lg:col-span-2">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between mb-4 gap-3 flex-wrap">
                <div class="flex items-center gap-2"><i class="fa-solid fa-microphone-lines text-primary"></i><h3 class="font-bold text-slate-900">Présentateurs & animateurs</h3></div>
                @can('update', $emission)<a href="{{ route('emissions.edit', $emission) }}#step-2" class="btn-outline !px-3 !py-2.5 min-h-[44px] text-xs"><i class="fa-solid fa-user-plus mr-1.5"></i>Gérer</a>@endcan
            </div>
            @if($emission->presentateurs->count()===0)
                <div class="px-6 py-12 text-center text-slate-400 text-sm italic">Aucun présentateur affecté pour le moment.</div>
            @else
                <div class="overflow-x-auto">
                    <table data-mobile-cards class="min-w-full text-sm">
                        <thead class="table-header">
                            <tr>
                                <th class="px-6 py-3 text-left">Journaliste</th>
                                <th class="px-6 py-3 text-left">Rôle</th>
                                <th class="px-6 py-3 text-left">Statut</th>
                                <th class="px-6 py-3 text-left">Ordre</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($emission->presentateurs as $j)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center text-white font-semibold text-xs ring-2 ring-white">{{ $j->initiales }}</div>
                                            <div>
                                                <div class="text-sm font-semibold text-slate-800">{{ $j->nom_complet }}</div>
                                                <div class="text-xs text-slate-500">{{ $j->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">{!! \App\Enums\RolePresentateurEmissionEnum::tryFrom($j->pivot->role)?->badge() ?? $j->pivot->role !!}</td>
                                    <td class="px-6 py-3">
                                        @if($j->pivot->statut_affectation==='Confirme') <span class="badge-green text-xs px-2.5 py-1 rounded-md font-medium">Confirmé</span>
                                        @elseif($j->pivot->statut_affectation==='Refuse') <span class="badge-red text-xs px-2.5 py-1 rounded-md font-medium">Refusé</span>
                                        @else <span class="badge-blue text-xs px-2.5 py-1 rounded-md font-medium">En attente</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-slate-700">#{{ $j->pivot->ordre }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- CONDUCTEURS RECENTS -->
        <div class="card-surface lg:col-span-3">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between mb-4 gap-3 flex-wrap">
                <div class="flex items-center gap-2"><i class="fa-solid fa-clipboard-list text-primary"></i><h3 class="font-bold text-slate-900">Conducteurs associés ({{ $emission->nombreConducteurs }})</h3></div>
                @can('create', \App\Models\Conducteur::class)
                    <button class="btn-primary !px-3 !py-2.5 min-h-[44px] text-xs"><i class="fa-solid fa-plus mr-1.5"></i>Nouveau conducteur</button>
                @endcan
            </div>
            @if($emission->conducteurs->count()===0)
                <div class="px-6 py-12 text-center text-slate-400 text-sm italic">Aucun conducteur rédigé pour cette émission.</div>
            @else
                <div class="overflow-x-auto">
                    <table data-mobile-cards class="min-w-full text-sm">
                        <thead class="table-header">
                            <tr>
                                <th class="px-6 py-3 text-left">#</th>
                                <th class="px-6 py-3 text-left">Titre</th>
                                <th class="px-6 py-3 text-left">Date</th>
                                <th class="px-6 py-3 text-left">Statut</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($emission->conducteurs->take(10) as $c)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-6 py-3 text-slate-500 font-mono">#{{ $c->id }}</td>
                                    <td class="px-6 py-3 font-medium text-slate-800">{{ $c->title }}</td>
                                    <td class="px-6 py-3 text-slate-600">{{ $c->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-6 py-3"><span class="badge-blue text-xs px-2 py-1 rounded-md capitalize">{{ $c->status ?? 'draft' }}</span></td>
                                    <td class="px-6 py-3 text-right"><button class="text-primary hover:text-primary-600 text-xs font-semibold px-2 py-2 inline-flex min-h-[44px]">Ouvrir</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
