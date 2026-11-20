{{-- TopAppBar Material3 — SOURCE STRICTE: App/dashboard.html#L199-L232 (zéro invention) --}}
@props([
    'pageTitle'    => 'TV+A OS',
    'pageSubtitle' => null,
    'headerActions' => null,
    'showGlobalSearch' => true,
])
@php
    $canBrowseConducteurs = auth()->user()?->can('viewAny', \App\Models\Conducteur::class) ?? false;
    $headerSearchRoute = request()->routeIs('workspace.*') ? 'workspace.index' : (request()->routeIs('dashboard') ? 'dashboard' : ((auth()->user()?->can('viewAny', \App\Models\VeilleSubject::class) ?? false) ? 'veille.sujets.index' : null));
    $isRedactionPage = request()->routeIs('conducteurs.redaction.*');
    $isEmissionPage = request()->routeIs('conducteurs.emission.*', 'conducteurs.chef_edition');
    $isPresentateurPage = request()->routeIs('conducteurs.presentateur.*');
    $isConducteurPage = $isRedactionPage || $isEmissionPage || $isPresentateurPage;
    $editionIndexRoute = $isEmissionPage ? 'conducteurs.emission.index' : ($isPresentateurPage ? 'conducteurs.presentateur.index' : 'conducteurs.redaction.index');
    $selectedEditionDate = ($dateEdition ?? null) instanceof \Carbon\CarbonInterface
        ? $dateEdition->toDateString()
        : (($conducteur ?? null)?->date_edition?->toDateString() ?? $initialDateEdition ?? today()->toDateString());
    // Titre d'en-tête : accueil personnalisé sur les tableaux de bord,
    // titre fonctionnel partout ailleurs.
    $utilisateurEntete = auth()->user();
    $prenomEntete = '';
    if ($utilisateurEntete && is_string($utilisateurEntete->name) && trim($utilisateurEntete->name) !== '') {
        $motsEntete = preg_split('/\s+/', trim($utilisateurEntete->name));
        $prenomEntete = (string) ($motsEntete[0] ?? '');
    }
    $titreEntete = request()->routeIs('dashboard')
        ? ($prenomEntete !== '' ? 'Bonjour, '.$prenomEntete.' 👋' : 'Bonjour 👋')
        : $pageTitle;
@endphp
<header class="ui-topbar bg-surface/80 dark:bg-background/80 backdrop-blur-md min-h-14 sm:min-h-16 h-auto shrink-0 py-2 sm:py-3 sticky top-0 z-40 shadow-sm dark:shadow-none flex flex-wrap justify-between items-center w-full px-3 sm:px-8 gap-2 sm:gap-3 border-b border-outline-variant">
    <div class="ui-topbar-heading flex items-center gap-4 flex-1 min-w-0 max-sm:w-full max-sm:flex-none">
        {{-- Burger : ouvre le drawer de navigation sur mobile/tablette --}}
        <button id="sidebar-toggle" type="button"
                class="lg:hidden shrink-0 inline-flex items-center justify-center p-2.5 rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors"
                aria-label="Ouvrir le menu de navigation">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="flex-1 min-w-0">
            <h1 class="ui-logo text-headline-lg font-headline-lg text-primary dark:text-primary-fixed m-0 leading-tight truncate max-sm:text-lg max-sm:whitespace-normal">{{ $titreEntete }}</h1>
            @if($pageSubtitle)
                <p class="ui-subtitle text-xs text-on-surface-variant mt-0.5 font-body-sm truncate">{{ $pageSubtitle }}</p>
            @endif
        </div>
    </div>

    <div class="ui-topbar-actions flex items-center gap-1 sm:gap-2 flex-nowrap overflow-x-auto justify-end max-sm:w-full max-sm:order-last scrollbar-hide flex-1">
        {{-- Boutons d'actions spécifiques page (Export/Ajouter etc) — accepte string HTML OU tableau structuré --}}
        @if($headerActions)
            <div class="flex items-center gap-3 border-r border-outline-variant pr-4">
                @if(is_string($headerActions))
                    {!! $headerActions !!}
                @elseif(is_array($headerActions))
                    @foreach($headerActions as $btn)
                        @php
                            $variant = $btn['variant'] ?? 'ghost';
                            $href = $btn['href'] ?? '#';
                            $icon = $btn['icon'] ?? null;
                            $label = $btn['label'] ?? '';
                            $onclick = $btn['onclick'] ?? null;
                            $id = $btn['id'] ?? null;
                            $attrs = $btn['attrs'] ?? null;
                            $type = $btn['type'] ?? 'link';
                            $form = $btn['form'] ?? null;
                            $class = $variant === 'primary'
                                ? 'bg-[#0054cd] text-white hover:bg-[#0043a3] rounded-lg px-4 py-2 text-sm inline-flex items-center gap-1.5 shadow-sm transition-colors'
                                : 'border border-outline-variant text-on-surface-variant hover:bg-surface-container-low rounded-lg px-4 py-2 text-sm inline-flex items-center gap-1.5 transition-colors';
                        @endphp
                        @if($type === 'button')
                            <button type="{{ $btn['button_type'] ?? 'button' }}" aria-label="{{ $label }}" title="{{ $label }}" @if($form) form="{{ $form }}" @endif @if($id) id="{{ $id }}" @endif @if($onclick) onclick="{{ $onclick }}" @endif @if($attrs) {!! $attrs !!} @endif class="{{ $class }}">
                                @if($icon)<i class="{{ $icon }} text-base"></i>@endif
                                <span class="hidden md:inline">{{ $label }}</span>
                            </button>
                        @else
                            <a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" @if($id) id="{{ $id }}" @endif @if($onclick) onclick="{{ $onclick }}" @endif @if($attrs) {!! $attrs !!} @endif class="{{ $class }}">
                                @if($icon)<i class="{{ $icon }} text-base"></i>@endif
                                <span class="hidden md:inline">{{ $label }}</span>
                            </a>
                        @endif
                    @endforeach
                @endif
            </div>
        @endif

        {{-- Actions globales : liens réels et filtre par date d’édition. --}}
        <a href="{{ route('dashboard') }}#workspace-priorities" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-lg px-2 text-on-surface-variant hover:bg-surface-container-low" title="Priorités de mon espace" aria-label="Voir les priorités de mon espace"><span class="material-symbols-outlined" aria-hidden="true">flag</span><span class="text-xs font-medium">Priorités</span></a>
        @can('viewAny', \App\Models\WorkspaceDossier::class)
        <a href="{{ route('workspace.notifications') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-lg px-2 text-on-surface-variant hover:bg-surface-container-low" title="Notifications des dossiers partagés" aria-label="Notifications des dossiers partagés"><span class="material-symbols-outlined" aria-hidden="true">notifications</span><span data-workspace-unread data-count-url="{{ route('workspace.unread') }}" @if(! $workspaceUnread) hidden @endif class="rounded bg-red-100 px-1.5 text-xs font-bold text-red-800">{{ $workspaceUnread }}</span></a>
        @endcan
        @if(request()->routeIs('dashboard') && isset($activeDashboardRole))
        <a href="{{ route('dashboard', ['role' => $activeDashboardRole]) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-lg px-2 text-on-surface-variant hover:bg-surface-container-low"><span class="material-symbols-outlined" aria-hidden="true">calendar_today</span><span class="text-xs font-medium">Aujourd’hui</span></a>
        <button type="button" id="workspace-filters-toggle" onclick="const panel = document.getElementById('workspace-filters'); if (panel) { panel.open = !panel.open; }" aria-controls="workspace-filters" aria-expanded="{{ request()->hasAny(['from', 'to', 'emission_id', 'department', 'q', 'status']) ? 'true' : 'false' }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-lg px-2 text-on-surface-variant hover:bg-surface-container-low"><span class="material-symbols-outlined" aria-hidden="true">filter_list</span><span class="text-xs font-medium">Filtres</span></button>
        @elseif($canBrowseConducteurs)
        <a href="{{ route($editionIndexRoute, array_merge($editionFilters ?? [], ['date' => today()->toDateString()])) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-lg px-2 text-on-surface-variant hover:bg-surface-container-low" title="Conducteurs du jour" aria-label="Voir les conducteurs du jour">
            <span class="material-symbols-outlined" aria-hidden="true">calendar_today</span>
            <span class="text-xs font-medium">Aujourd’hui</span>
        </a>
        <button type="button" id="edition-filter-toggle" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-lg px-2 text-on-surface-variant hover:bg-surface-container-low" title="Choisir une date d’édition" aria-label="Filtrer les conducteurs par date" aria-controls="edition-filter-panel" aria-expanded="false">
            <span class="material-symbols-outlined" aria-hidden="true">filter_list</span>
            <span class="text-xs font-medium">Date</span>
        </button>

        @endif

        {{-- Recherche globale : icônes rapides → recherche → compte --}}
        @if($showGlobalSearch && $headerSearchRoute)
        <form action="{{ route($headerSearchRoute) }}" method="GET" role="search" class="ui-topbar-search flex-1 max-w-xl px-4 hidden lg:block">
            @if($headerSearchRoute === 'dashboard' && isset($activeDashboardRole))<input type="hidden" name="role" value="{{ $activeDashboardRole }}">@foreach(['from', 'to', 'emission_id', 'department', 'status'] as $headerFilterName)@if(is_scalar(request($headerFilterName)) && filled(request($headerFilterName)))<input type="hidden" name="{{ $headerFilterName }}" value="{{ request($headerFilterName) }}">@endif @endforeach @endif
            <div class="relative group">
                <button type="submit" aria-label="Rechercher dans mon espace" class="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant"><span class="material-symbols-outlined" aria-hidden="true">search</span></button>
                <input
                    type="search" name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" aria-label="Rechercher dans mon espace"
                    class="w-full bg-surface-container-low border border-outline-variant rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:border-primary-container focus:ring-1 focus:ring-primary-container transition-all"
                    placeholder="Rechercher dans mon espace…"
                />
            </div>
        </form>
        @endif

        <div class="h-8 w-[1px] bg-outline-variant sm:mx-2"></div>

        {{-- Profile --}}
        @auth
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 cursor-pointer">
                @php
                    $u = auth()->user();
                    $name = $u?->name ?? 'Invité';
                    $photo = $u?->journaliste?->photoUrl ?? ($u?->intervenant?->photoUrl ?? null);
                    if (!$photo) {
                        $photo = 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=001a41&color=ffffff&size=128';
                    }
                    $roleName = method_exists($u, 'getRoleNames') ? ($u->getRoleNames()->first() ?? 'Utilisateur') : 'Utilisateur';
                @endphp
                <div class="text-right hidden md:block">
                    <div class="text-sm font-bold text-primary">{{ $name }}</div>
                    <div class="text-xs text-on-surface-variant">{{ \Illuminate\Support\Str::headline($roleName) }} · En ligne</div>
                </div>
                <img alt="{{ $name }}" class="ui-avatar w-10 h-10 rounded-full border border-outline-variant object-cover" src="{{ $photo }}"/>
            </a>
        @else
            <a href="{{ route('login') }}" class="text-sm font-bold text-primary hover:underline">Connexion</a>
        @endauth
    </div>
    @if(! (request()->routeIs('dashboard') && isset($activeDashboardRole)) && $canBrowseConducteurs)
    <section id="edition-filter-panel" hidden class="absolute right-4 top-full z-50 mt-2 w-[calc(100%-2rem)] max-w-sm rounded-xl border border-outline-variant bg-white p-4 shadow-xl" aria-label="Filtrer les conducteurs par date">
        <form id="edition-filter-form" action="{{ route($editionIndexRoute) }}" method="GET" class="space-y-3">
            @foreach(($editionFilters ?? []) as $filterName => $filterValue)<input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">@endforeach
            <div class="flex items-center justify-between gap-3"><h2 class="font-semibold text-primary">Choisir une édition</h2><button type="button" id="edition-filter-close" aria-label="Fermer les filtres" class="inline-flex h-11 w-11 items-center justify-center rounded-lg hover:bg-surface-container"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div>
            @unless($isConducteurPage)
                <div><label for="edition-filter-type" class="mb-1 block text-sm">Type de conducteur</label><select id="edition-filter-type" class="w-full rounded-lg border border-outline-variant text-sm"><option value="{{ route('conducteurs.redaction.index') }}">Rédaction</option><option value="{{ route('conducteurs.emission.index') }}">Émission</option></select></div>
            @endunless
            <div><label for="edition-filter-date" class="mb-1 block text-sm">Date d’édition</label><input id="edition-filter-date" type="date" name="date" value="{{ $selectedEditionDate }}" required class="w-full rounded-lg border border-outline-variant text-sm"></div>
            <button type="submit" class="min-h-[44px] w-full rounded-lg bg-primary px-4 text-sm font-semibold text-white">Afficher les conducteurs</button>
        </form>
    </section>
    <script>
        (() => {
            const toggle = document.getElementById('edition-filter-toggle');
            const panel = document.getElementById('edition-filter-panel');
            const form = document.getElementById('edition-filter-form');
            const type = document.getElementById('edition-filter-type');
            if (!toggle || !panel || !form) return;
            const setOpen = (open) => { panel.hidden = !open; toggle.setAttribute('aria-expanded', String(open)); if (open) (type ?? document.getElementById('edition-filter-date')).focus(); };
            toggle.addEventListener('click', () => setOpen(panel.hidden));
            document.getElementById('edition-filter-close').addEventListener('click', () => { setOpen(false); toggle.focus(); });
            type?.addEventListener('change', (event) => { form.action = event.target.value; });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) { setOpen(false); toggle.focus(); } });
            document.addEventListener('click', (event) => { if (!panel.hidden && !panel.contains(event.target) && !toggle.contains(event.target)) setOpen(false); });
        })();
    </script>
    @endif
</header>
