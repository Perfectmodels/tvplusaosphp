@extends('layouts.app')
@php
    use App\Models\Intervenant;
    use App\Enums\TypeIntervenantEnum;
    use App\Enums\StatutDispoIntervenantEnum;
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Carbon;

    $recherche = $recherche ?? request('q', '');
    $type = $type ?? request('type', '');
    $dispo = $dispo ?? request('dispo', '');
    $filtreRapide = request('filtre', '');

    if (!isset($intervenants)) {
        $query = Intervenant::query()->orderBy('nom');
        if (trim($recherche) !== '') {
            $query->recherche($recherche);
        }
        if ($type) $query->where('type', $type);
        if ($dispo) $query->where('disponibilite', $dispo);
        if ($filtreRapide === 'bookes') {
            $query->whereNotNull('last_scan_at')->where('last_scan_at', '>=', Carbon::now()->subDays(7));
        }
        if ($filtreRapide === 'nouveaux') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        }
        if ($fonction = request('fonction')) {
            $query->where('fonction', 'LIKE', '%' . $fonction . '%');
        }
        if ($ville = request('ville')) {
            $query->where('ville_residence', $ville);
        }
        $intervenants = $query->paginate(18)->withQueryString();
    }

    $totalI = Intervenant::count();
    $disponibles = Intervenant::where('disponibilite', StatutDispoIntervenantEnum::Disponible->value)->count();
    $aRelancer = Intervenant::where('disponibilite', StatutDispoIntervenantEnum::Occupe->value)->count();
    $bookes = Intervenant::whereNotNull('last_scan_at')->where('last_scan_at', '>=', Carbon::now()->subDays(7))->count();
    $nouveaux = Intervenant::where('created_at', '>=', Carbon::now()->subDays(30))->count();

    $pctDisponibles = $totalI > 0 ? round($disponibles * 100 / $totalI, 1) : 0;
    $pctRelancer = $totalI > 0 ? round($aRelancer * 100 / $totalI, 1) : 0;
    $pctBookes = $totalI > 0 ? round($bookes * 100 / $totalI, 1) : 0;
    $pctNouveaux = $totalI > 0 ? round($nouveaux * 100 / $totalI, 1) : 0;

    $pageTitle = 'Intervenants';
    $pageSubtitle = 'Gestion des invités et experts • ' . $intervenants->total() . ' fiches actives';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Scanner QR', 'icon' => 'fa-solid fa-camera', 'href' => route('scanner-qr.index'), 'variant' => 'ghost'],
        ['label' => 'Suivi logistique', 'icon' => 'fa-solid fa-chart-line', 'href' => route('suivi-intervenants.index'), 'variant' => 'ghost'],
    ];
    if (Gate::check('create', Intervenant::class)) {
        $headerActions[] = ['label' => 'Nouvel intervenant', 'icon' => 'fa-solid fa-plus', 'href' => route('intervenants.create'), 'variant' => 'primary'];
    }
@endphp
@section('content')

<!-- 5 KPIs GRID COLS-5 -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/30 flex items-center gap-4 custom-shadow">
        <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center flex-shrink-0 text-secondary">
            <span class="material-symbols-outlined text-lg">groups</span>
        </div>
        <div>
            <div class="text-xs font-label-sm text-on-surface-variant uppercase tracking-wider mb-1">Total intervenants</div>
            <div class="flex items-end gap-2">
                <span class="font-headline-lg text-headline-lg text-primary-container font-bold">{{ number_format($totalI, 0, ',', ' ') }}</span>
                <span class="text-xs text-secondary font-medium mb-1">+{{ $nouveaux }} ce mois</span>
            </div>
        </div>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/30 flex items-center gap-4 custom-shadow">
        <div class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center flex-shrink-0 text-emerald-600">
            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
        </div>
        <div>
            <div class="text-xs font-label-sm text-on-surface-variant uppercase tracking-wider mb-1">Disponibles</div>
            <div class="flex items-end gap-2">
                <span class="font-headline-lg text-headline-lg text-primary-container font-bold">{{ number_format($disponibles, 0, ',', ' ') }}</span>
                <span class="text-xs text-on-surface-variant mb-1">{{ $pctDisponibles }}%</span>
            </div>
        </div>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/30 flex items-center gap-4 custom-shadow">
        <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center flex-shrink-0 text-amber-600">
            <span class="material-symbols-outlined text-lg">assignment_return</span>
        </div>
        <div>
            <div class="text-xs font-label-sm text-on-surface-variant uppercase tracking-wider mb-1">À relancer</div>
            <div class="flex items-end gap-2">
                <span class="font-headline-lg text-headline-lg text-primary-container font-bold">{{ number_format($aRelancer, 0, ',', ' ') }}</span>
                <span class="text-xs text-on-surface-variant mb-1">{{ $pctRelancer }}%</span>
            </div>
        </div>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/30 flex items-center gap-4 custom-shadow">
        <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center flex-shrink-0 text-blue-600">
            <span class="material-symbols-outlined text-lg">calendar_today</span>
        </div>
        <div>
            <div class="text-xs font-label-sm text-on-surface-variant uppercase tracking-wider mb-1">Déjà bookés</div>
            <div class="flex items-end gap-2">
                <span class="font-headline-lg text-headline-lg text-primary-container font-bold">{{ number_format($bookes, 0, ',', ' ') }}</span>
                <span class="text-xs text-on-surface-variant mb-1">{{ $pctBookes }}%</span>
            </div>
        </div>
    </div>

    <div class="bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/30 flex items-center gap-4 custom-shadow">
        <div class="w-10 h-10 rounded-full bg-purple-500/10 flex items-center justify-center flex-shrink-0 text-purple-600">
            <span class="material-symbols-outlined text-lg">auto_awesome</span>
        </div>
        <div>
            <div class="text-xs font-label-sm text-on-surface-variant uppercase tracking-wider mb-1">Nouveaux</div>
            <div class="flex items-end gap-2">
                <span class="font-headline-lg text-headline-lg text-primary-container font-bold">{{ number_format($nouveaux, 0, ',', ' ') }}</span>
                <span class="text-xs text-on-surface-variant mb-1">{{ $pctNouveaux }}%</span>
            </div>
        </div>
    </div>
</div>

<!-- FILTERS -->
<div class="flex flex-col gap-4 mb-8">
    <!-- Quick filters pills -->
    <div class="mobile-wrap-tabs flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
        @php
            $qsBase = ['q' => $recherche, 'type' => $type, 'dispo' => $dispo];
            $qsActive = request()->query();
            $isTous = !($dispo || $filtreRapide);
            $pillClass = static function (bool $active): string {
                return $active
                    ? 'flex items-center gap-2 px-4 py-2.5 min-h-[44px] bg-primary-container text-white rounded-full text-sm font-label-sm whitespace-nowrap'
                    : 'flex items-center gap-2 px-4 py-2.5 min-h-[44px] bg-surface-container-lowest border border-outline-variant/50 text-on-surface rounded-full text-sm font-label-sm hover:bg-surface-container transition-colors whitespace-nowrap';
            };
        @endphp
        <a href="{{ route('intervenants.index', array_merge($qsBase, ['dispo' => null, 'filtre' => null])) }}" class="{{ $pillClass($isTous) }}">
            <span class="material-symbols-outlined text-sm">grid_view</span>
            Tous
        </a>
        <a href="{{ route('intervenants.index', array_merge($qsBase, ['dispo' => StatutDispoIntervenantEnum::Disponible->value, 'filtre' => null])) }}" class="{{ $pillClass($dispo === StatutDispoIntervenantEnum::Disponible->value) }}">
            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
            Disponibles
        </a>
        <a href="{{ route('intervenants.index', array_merge($qsBase, ['dispo' => StatutDispoIntervenantEnum::Occupe->value, 'filtre' => null])) }}" class="{{ $pillClass($dispo === StatutDispoIntervenantEnum::Occupe->value) }}">
            <div class="w-2 h-2 rounded-full bg-amber-500"></div>
            À booker
        </a>
        <a href="{{ route('intervenants.index', array_merge($qsBase, ['dispo' => StatutDispoIntervenantEnum::Interdit->value, 'filtre' => null])) }}" class="{{ $pillClass($dispo === StatutDispoIntervenantEnum::Interdit->value) }}">
            <span class="material-symbols-outlined text-sm text-red-500">cached</span>
            Relances
        </a>
        <a href="{{ route('intervenants.index', array_merge($qsBase, ['dispo' => null, 'filtre' => 'bookes'])) }}" class="{{ $pillClass($filtreRapide === 'bookes') }}">
            <span class="material-symbols-outlined text-sm text-blue-500">event_available</span>
            Déjà bookés
        </a>
        <a href="{{ route('intervenants.index', array_merge($qsBase, ['dispo' => null, 'filtre' => 'nouveaux'])) }}" class="{{ $pillClass($filtreRapide === 'nouveaux') }}">
            <span class="material-symbols-outlined text-sm text-purple-500">auto_awesome</span>
            Nouveaux
        </a>
    </div>

    <!-- Detailed filters -->
    <form method="GET" action="{{ route('intervenants.index') }}" class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[200px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm">search</span>
            <input
                type="text"
                name="q"
                value="{{ $recherche }}"
                placeholder="Rechercher un intervenant à booker..."
                class="w-full pl-9 pr-4 py-2 bg-surface rounded-lg border border-outline-variant/50 focus:border-primary-container focus:ring-1 focus:ring-primary-container text-sm font-body-sm outline-none transition-all"
            />
        </div>

        <select name="type" class="px-3 py-2.5 bg-surface border border-outline-variant/50 rounded-lg text-sm font-body-sm hover:bg-surface-container transition-colors outline-none pr-8">
            <option value="">Toutes les catégories</option>
            @foreach(TypeIntervenantEnum::cases() as $e)
                <option value="{{ $e->value }}" @selected($type === $e->value)>{{ $e->label() }}</option>
            @endforeach
        </select>

        <select name="dispo" class="px-3 py-2.5 bg-surface border border-outline-variant/50 rounded-lg text-sm font-body-sm hover:bg-surface-container transition-colors outline-none pr-8">
            <option value="">Disponibilité</option>
            @foreach(StatutDispoIntervenantEnum::cases() as $e)
                <option value="{{ $e->value }}" @selected($dispo === $e->value)>{{ preg_replace('/^[^\s]+\s+/u', '', $e->label()) }}</option>
            @endforeach
        </select>

        @php
            $fonctionsDistinctes = \Illuminate\Support\Facades\DB::table('intervenants')
                ->whereNotNull('fonction')->where('fonction', '!=', '')
                ->distinct()->orderBy('fonction')->limit(30)->pluck('fonction');
            $villesDistinctes = \Illuminate\Support\Facades\DB::table('intervenants')
                ->whereNotNull('ville_residence')->where('ville_residence', '!=', '')
                ->distinct()->orderBy('ville_residence')->limit(30)->pluck('ville_residence');
        @endphp

        <select name="fonction" class="px-3 py-2.5 bg-surface border border-outline-variant/50 rounded-lg text-sm font-body-sm hover:bg-surface-container transition-colors outline-none pr-8">
            <option value="">Fonction</option>
            @foreach($fonctionsDistinctes as $f)
                <option value="{{ $f }}" @selected(request('fonction') === $f)>{{ $f }}</option>
            @endforeach
        </select>

        <select name="ville" class="px-3 py-2.5 bg-surface border border-outline-variant/50 rounded-lg text-sm font-body-sm hover:bg-surface-container transition-colors outline-none pr-8">
            <option value="">Ville</option>
            @foreach($villesDistinctes as $v)
                <option value="{{ $v }}" @selected(request('ville') === $v)>{{ $v }}</option>
            @endforeach
        </select>

        <div data-emission-searchable class="min-w-[220px] space-y-1">
            <input type="search" data-emission-search aria-label="Rechercher une émission pour filtrer les invités"
                   placeholder="Rechercher une émission…" autocomplete="off"
                   class="w-full px-3 py-2 bg-surface border border-outline-variant/50 rounded-lg text-sm outline-none focus:border-primary-container focus:ring-1 focus:ring-primary-container">
            <select name="emission_id" data-emission-select
                    class="w-full px-3 py-2.5 bg-surface border border-outline-variant/50 rounded-lg text-sm outline-none focus:border-primary-container focus:ring-1 focus:ring-primary-container">
                <option value="">Toutes les émissions</option>
                @foreach($emissions as $id => $nom)
                    <option value="{{ $id }}" @selected((int) $emissionId === (int) $id)>{{ $nom }}</option>
                @endforeach
            </select>
        </div>

        <a href="{{ route('intervenants.index') }}" class="flex items-center gap-2 px-3 py-2.5 text-on-surface-variant text-sm font-label-sm hover:text-primary transition-colors ml-auto">
            <span class="material-symbols-outlined text-sm">refresh</span>
            Réinitialiser
        </a>

        <button type="submit" class="px-4 py-2.5 bg-primary-container text-white rounded-lg text-sm font-label-sm hover:bg-secondary transition-colors">
            Filtrer
        </button>
    </form>
</div>

<!-- CARDS GRID -->
@if($intervenants->contains(fn ($item) => auth()->user()->can('delete', $item)))
    @include('_partials.bulk-delete-toolbar', ['resource' => 'intervenants'])
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-4 mb-8" data-bulk-list="bulk-intervenants">
    @forelse($intervenants as $i)
        @php
            $statut = $i->disponibilite ?? StatutDispoIntervenantEnum::Disponible;
            $statutText = preg_replace('/^[^\s]+\s+/u', '', $statut->label());
            $statutColorClass = match(true) {
                $statut === StatutDispoIntervenantEnum::Disponible => 'bg-white/90 backdrop-blur-sm px-2 py-1 rounded text-xs font-label-sm text-emerald-600',
                $statut === StatutDispoIntervenantEnum::Occupe => 'bg-white/90 backdrop-blur-sm px-2 py-1 rounded text-xs font-label-sm text-amber-600',
                default => 'bg-white/90 backdrop-blur-sm px-2 py-1 rounded text-xs font-label-sm text-red-600',
            };
            $domaines = is_array($i->domaines) ? $i->domaines : ($i->domaines ? json_decode($i->domaines, true) ?? [] : []);
            $domainePill = !empty($domaines) ? $domaines[0] : ($i->type?->label() ?? 'Sans domaine');
            $domaineColorClass = match(true) {
                $i->type === TypeIntervenantEnum::Expert || $i->type === TypeIntervenantEnum::Correspondant => 'bg-primary-container/10 text-primary-container',
                $i->type === TypeIntervenantEnum::Politicien => 'bg-blue-50 text-blue-600',
                $i->type === TypeIntervenantEnum::SocieteCivil => 'bg-emerald-50 text-emerald-600',
                in_array($i->type, [TypeIntervenantEnum::Artiste, TypeIntervenantEnum::Sportif, TypeIntervenantEnum::Animateur]) => 'bg-indigo-50 text-indigo-600',
                default => 'bg-surface-container-highest text-on-surface-variant',
            };
        @endphp
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-hidden flex flex-col custom-shadow group">
        <div class="px-3">@include('_partials.bulk-delete-checkbox', ['resource' => 'intervenants', 'record' => $i, 'label' => $i->nom_complet])</div>
        <div class="relative h-40 bg-surface-container">
            <div class="absolute top-2 left-2 z-10 {{ $statutColorClass }} flex items-center gap-1">
                {{ $statutText }}
            </div>
            <div class="absolute top-2 right-2 z-10 flex items-center gap-2">
                <a href="{{ route('intervenants.qr', $i) }}"
                   title="Voir le badge QR — {{ $i->code_intervenant }}"
                   class="bg-white/90 backdrop-blur-sm p-2.5 rounded min-w-[44px] min-h-[44px] text-purple-600 hover:text-purple-700 hover:bg-white transition-all shadow-sm">
                    <span class="material-symbols-outlined text-base">qr_code_2</span>
                </a>
                @can('delete', $i)
                <form method="POST" action="{{ route('intervenants.destroy', $i) }}"
                      data-confirm-delete="Supprimer {{ e($i->nom_complet) }} ?"
                      data-confirm-delete-text="Cette action est irréversible. Sa photo, ses documents et son badge QR seront supprimés."
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            title="Supprimer l'intervenant"
                            class="bg-white/90 backdrop-blur-sm p-2.5 rounded min-w-[44px] min-h-[44px] text-error hover:text-white hover:bg-error transition-all shadow-sm">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                </form>
                @endcan
            </div>
            <img
                class="w-full h-full object-cover object-top mix-blend-multiply opacity-90 transition-transform duration-500 group-hover:scale-[1.03]"
                src="{{ $i->photoUrl }}"
                alt="Portrait de {{ $i->nom_complet }}"
                onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name={{ urlencode($i->nom_complet) }}&background=0054cd&color=fff&size=256&bold=true';"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
        </div>
        <div class="p-4 flex-1 flex flex-col">
            <a href="{{ route('intervenants.show', $i) }}" class="hover:text-primary-container transition-colors">
                <h3 class="font-headline-sm text-headline-sm text-on-surface mb-1 line-clamp-1">{{ $i->nom_complet }}</h3>
            </a>
            <p class="text-xs text-on-surface-variant mb-3 line-clamp-1">
                {{ $i->fonction ?: ($i->poste ?: ($i->organisme ?? '—')) }}
                @if($i->organisme && $i->fonction) · {{ $i->organisme }} @endif
            </p>
            <div class="flex flex-wrap gap-1 mb-4 mt-auto">
                <span class="px-2 py-1 {{ $domaineColorClass }} rounded text-xs font-medium">
                    {{ \Illuminate\Support\Str::limit($domainePill, 24) }}
                </span>
            </div>
            <div class="flex items-center gap-1 text-xs text-on-surface-variant mb-4">
                <span class="material-symbols-outlined text-xs">location_on</span>
                {{ $i->ville_residence ? $i->ville_residence . ($i->pays_residence ? ', ' . $i->pays_residence : '') : 'Non renseignée' }}
            </div>
            <a
                href="{{ route('parcours-intervenants.index', ['intervenant_id' => $i->id]) }}"
                class="w-full flex items-center justify-between px-4 py-2 bg-primary-container text-white rounded-lg text-sm font-label-sm hover:bg-secondary transition-colors"
            >
                Booker
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </a>
        </div>
    </div>
    @empty
        <div class="col-span-full bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-12 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-surface-container-highest flex items-center justify-center mb-4 text-on-surface-variant">
                <span class="material-symbols-outlined text-3xl">person_off</span>
            </div>
            <h3 class="font-headline-md text-on-surface mb-2">Aucun intervenant trouvé</h3>
            <p class="text-sm text-on-surface-variant mb-6 max-w-md mx-auto">
                Modifiez vos filtres ou créez une nouvelle fiche intervenant pour l'ajouter au répertoire.
            </p>
            @can('create', Intervenant::class)
                <a href="{{ route('intervenants.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-container text-white rounded-lg text-sm font-label-sm hover:bg-secondary transition-colors">
                    <span class="material-symbols-outlined text-sm">add</span>
                    Créer un intervenant
                </a>
            @endcan
        </div>
    @endforelse
</div>

<!-- Pagination -->
@if($intervenants->hasPages())
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-4">
        {{ $intervenants->onEachSide(1)->links() }}
    </div>
@endif
@endsection
