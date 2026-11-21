{{-- Sidebar TV+A OS Material3 — UNIFORME 100% SUR TOUTES LES PAGES: LOGO → ŒIL → MENU --}}
<nav id="app-sidebar" aria-label="Navigation principale" tabindex="-1" class="bg-surface dark:bg-background w-64 h-screen fixed left-0 top-0 overflow-y-auto border-r border-outline-variant dark:border-surface-variant flex flex-col h-full py-stack-md shrink-0 z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <!-- BLOC HEADER UNIFORME: [1] Logo TV+A OS  →  [2] Œil Bleu photo  →  [3] Subtitle -->
    <div class="relative px-6 mb-8 flex flex-col gap-2">
        <button type="button" data-sidebar-close
                class="absolute right-3 top-0 inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-low focus-visible:outline focus-visible:outline-2 focus-visible:outline-secondary lg:hidden"
                aria-label="Fermer le menu principal">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>
        {{-- [1] LOGO TV+A OS — WORDMARK UNIQUE, IDENTIQUE SUR TOUT LE SITE --}}
        <div class="ui-sidebar-logo flex items-center gap-3 mb-1">
            <div class="w-11 h-11 rounded-2xl bg-primary-container text-on-primary flex items-center justify-center font-bold shadow-md shadow-primary/15">
                <span class="material-symbols-outlined filled" style="font-size:26px; font-variation-settings: 'FILL' 1;">visibility</span>
            </div>
            <div class="leading-tight min-w-0">
                <h1 class="m-0"><x-brand.wordmark class="text-xl text-primary-container dark:text-primary-fixed" /></h1>
                <div class="text-[11px] uppercase tracking-[0.22em] text-on-surface-variant font-bold">TV+ Afrique</div>
            </div>
        </div>

        {{-- Œil HTML partagé avec l’accueil immersif. --}}
        <div class="flex justify-center"><x-welcome-eye class="ui-sidebar-oeil tvos-regard--compact" aria-label="Œil TV+A OS" /></div>

        {{-- [3] Sous-titre header sidebar — identique sur toutes les pages --}}
        <p class="text-xs uppercase tracking-widest text-on-surface-variant font-bold">
            Système d'exploitation TV Afrique
        </p>
    </div>

    <!-- Navigation Tabs 10 modules TV+A OS (liens route() si activé sinon #) -->
    <div class="flex-1 flex flex-col gap-1 px-2 font-body-sm text-body-sm font-semibold">
        @php
            use Illuminate\Support\Facades\Request;
            use Illuminate\Support\Facades\Route;

            $r = Request::route()?->getName() ?? '';
            $active = fn($p) => str_starts_with($r, $p) ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-lg mx-2';
            $href = static function (string $routeName) {
                return Route::has($routeName) ? route($routeName) : '#';
            };
            $isActive = static function (string $patternOrRoute) use ($r) {
                if (str_contains($patternOrRoute, '*') || str_starts_with($patternOrRoute,'/')) {
                    return request()->is($patternOrRoute);
                }
                return str_starts_with($r, $patternOrRoute);
            };
        @endphp

        <a class="px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ $isActive('dashboard') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : $active('dashboard') }}" href="{{ $href('dashboard') }}">
            <span class="material-symbols-outlined shrink-0">dashboard</span>
            <span class="min-w-0 truncate">Tableau de bord</span>
        </a>

        @can('viewAny', \App\Models\WorkspaceDossier::class)
        <a class="px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ $active('workspace.') }}" href="{{ route('workspace.index') }}"><span class="material-symbols-outlined shrink-0">hub</span><span class="min-w-0 truncate">Espace métier partagé</span></a>
        @endcan

        @can('viewAny', \App\Models\DigitalPublication::class)
        <a class="px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ $active('digital.') }}" href="{{ route('digital.index') }}"><span class="material-symbols-outlined shrink-0">public</span><span class="min-w-0 truncate">Publications numériques</span></a>
        @endcan

        <!-- ==================== MODULES MASQUÉS POUR PLUS TARD ====================
             Vues vides (prototypes HTML non intégrés) — masqués du menu le 14/10/2026.
             Les routes sont commentées dans routes/module_autres.php (→ 404).
             Pour réactiver : décommenter l'entrée ici ET sa route correspondante.
             · Conférences  (route 'conferences.*')     · view conferences/*
             · Ressources   (route 'ressources')        · view ressources.blade.php
             · Planning     (route 'planning')          · view planning.blade.php
             · Archives     (route 'archives')          · view archives.blade.php
             · Réglages     (route 'reglages')          · view reglages.blade.php
        ======================================================================== -->

        @can('viewAny', \App\Models\Conducteur::class)
            @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'superadmin']))
            <div x-data="{ openConducteurs: {{ request()->routeIs('conducteurs.*') ? 'true' : 'false' }} }" class="group">
                <button type="button"
                    @click="openConducteurs = !openConducteurs"
                    :aria-expanded="String(openConducteurs)" aria-controls="tv-sidebar-conducteurs"
                    class="w-full px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ request()->routeIs('conducteurs.*') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-lg mx-2' }}">
                    <span class="material-symbols-outlined shrink-0">view_list</span>
                    <span class="min-w-0 flex-1 text-left truncate">Conducteurs</span>
                    <span class="material-symbols-outlined shrink-0 text-xs transition-transform duration-200"
                          :class="openConducteurs ? 'rotate-90' : ''">chevron_right</span>
                </button>
                <div id="tv-sidebar-conducteurs" x-show="openConducteurs" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 max-h-0"
                     x-transition:enter-end="opacity-100 max-h-[28rem]"
                     x-transition:leave="transition ease-in duration-120"
                     x-transition:leave-start="opacity-100 max-h-[28rem]"
                     x-transition:leave-end="opacity-0 max-h-0"
                     class="overflow-hidden ml-3 pl-3 border-l border-outline-variant/50 space-y-0.5 py-1.5">
                    <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('conducteurs.redaction*') ? 'text-error bg-error-container/15 font-semibold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                       href="{{ $href('conducteurs.redaction.index') }}">
                        <span class="material-symbols-outlined shrink-0 text-[17px]">edit_document</span>
                        <span class="min-w-0 truncate">Rédaction</span>
                    </a>
                    <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('conducteurs.emission*') ? 'text-error bg-error-container/15 font-semibold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                       href="{{ $href('conducteurs.emission.index') }}">
                        <span class="material-symbols-outlined shrink-0 text-[17px]">tv</span>
                        <span class="min-w-0 truncate">Émission</span>
                    </a>
                    <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('conducteurs.chef_edition*') ? 'text-error bg-error-container/15 font-semibold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                       href="{{ route('conducteurs.chef_edition') }}">
                        <span class="material-symbols-outlined shrink-0 text-[17px]">fact_check</span>
                        <span class="min-w-0 truncate">Chef d’édition</span>
                    </a>
                    <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('conducteurs.presentateur*') ? 'text-error bg-error-container/15 font-semibold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                       href="{{ $href('conducteurs.presentateur.index') }}">
                        <span class="material-symbols-outlined shrink-0 text-[17px]">co_present</span>
                        <span class="min-w-0 truncate">Présentateur</span>
                    </a>
                </div>
            </div>
            @else
            <a class="px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ request()->routeIs('conducteurs.redaction*') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-lg mx-2' }}" href="{{ $href('conducteurs.redaction.index') }}">
                <span class="material-symbols-outlined shrink-0">edit_document</span>
                <span class="min-w-0 truncate">Conducteurs · Rédaction</span>
            </a>
            <a class="px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ request()->routeIs('conducteurs.emission*') || request()->routeIs('conducteurs.chef_edition*') || request()->routeIs('conducteurs.presentateur*') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-lg mx-2' }}" href="{{ $href('conducteurs.emission.index') }}">
                <span class="material-symbols-outlined shrink-0">tv</span>
                <span class="min-w-0 truncate">Conducteurs · Chef d'Édition</span>
            </a>
            @endif
        @endcan

        @can('viewAny', \App\Models\Emission::class)
        <a class="px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ $isActive('emissions') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : $active('emissions') }}" href="{{ $href('emissions.index') }}">
            <span class="material-symbols-outlined shrink-0">live_tv</span>
            <span class="min-w-0 truncate">Émissions</span>
        </a>
        @endcan

        @if(auth()->user()->can('viewAny', \App\Models\VeilleSubject::class) || auth()->user()->can('viewAny', \App\Models\SourceVeille::class))
        <!-- GROUPE COLLAPSÉ : VEILLE & SOURCES (2 sous-menus) -->
        <div x-data="{ openVeille: {{ request()->routeIs('veille.*') || request()->routeIs('sources.veille.*') ? 'true' : 'false' }} }" class="group">
            <button type="button"
                @click="openVeille = !openVeille"
                :aria-expanded="String(openVeille)" aria-controls="tv-sidebar-veille"
                class="w-full px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ request()->routeIs('veille.*') || request()->routeIs('sources.veille.*') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-lg mx-2' }}">
                <span class="material-symbols-outlined shrink-0">travel_explore</span>
                <span class="min-w-0 flex-1 text-left truncate">Veille & Sources</span>
                <span class="material-symbols-outlined shrink-0 text-xs transition-transform duration-200"
                      :class="openVeille ? 'rotate-90' : ''">chevron_right</span>
            </button>

            <div id="tv-sidebar-veille" x-show="openVeille" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 max-h-0"
                 x-transition:enter-end="opacity-100 max-h-96"
                 x-transition:leave="transition ease-in duration-120"
                 x-transition:leave-start="opacity-100 max-h-96"
                 x-transition:leave-end="opacity-0 max-h-0"
                 class="overflow-hidden ml-3 pl-3 border-l border-outline-variant/50 space-y-0.5 py-1.5">

                {{-- 1. Veille --}}
                @can('viewAny', \App\Models\VeilleSubject::class)
                <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('veille.sujets.*') ? 'text-error bg-error-container/15 font-semibold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                   href="{{ $href('veille.sujets.index') }}">
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('veille.sujets.*') ? 'bg-error' : 'bg-on-surface-variant/40' }}"></span>
                    <span class="material-symbols-outlined shrink-0 text-[17px]">visibility</span>
                    <span class="min-w-0 truncate">Veille</span>
                </a>
                @endcan

                {{-- 2. Sources de veille --}}
                @can('viewAny', \App\Models\SourceVeille::class)
                <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('veille.sources*') || request()->routeIs('sources.veille.*') ? 'text-error bg-error-container/15 font-semibold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                   href="{{ $href('veille.sources') }}">
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('veille.sources*') || request()->routeIs('sources.veille.*') ? 'bg-error' : 'bg-on-surface-variant/40' }}"></span>
                    <span class="material-symbols-outlined shrink-0 text-[17px]">rss_feed</span>
                    <span class="min-w-0 truncate">Sources de veille</span>
                </a>
                @endcan
            </div>
        </div>

        @endif

        @can('viewAny', \App\Models\Journaliste::class)
        <a class="px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ $isActive('journalistes') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : $active('journalistes') }}" href="{{ $href('journalistes.index') }}">
            <span class="material-symbols-outlined shrink-0">person_search</span>
            <span class="min-w-0 truncate">Journalistes</span>
        </a>
        @endcan

        <!-- GROUPE COLLAPSÉ : INTERVENANTS (2 sous-menus comme capture TV+A OS) -->
        @can('viewAny', \App\Models\Intervenant::class)
        <div x-data="{ openIntervenants: {{ request()->routeIs('intervenants.*') || request()->routeIs('parcours-intervenants.*') || request()->routeIs('suivi-intervenants.*') ? 'true' : 'false' }} }" class="group">
            <button type="button"
                @click="openIntervenants = !openIntervenants"
                :aria-expanded="String(openIntervenants)" aria-controls="tv-sidebar-intervenants"
                class="w-full px-4 py-3 flex items-center gap-3 whitespace-nowrap transition-colors {{ request()->routeIs('intervenants.*') || request()->routeIs('parcours-intervenants.*') ? 'text-error bg-error-container/10 border-r-4 border-error font-bold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high dark:hover:bg-surface-container-highest rounded-lg mx-2' }}">
                <span class="material-symbols-outlined shrink-0">group_add</span>
                <span class="min-w-0 flex-1 text-left truncate">Intervenants</span>
                <span class="material-symbols-outlined shrink-0 text-xs transition-transform duration-200"
                      :class="openIntervenants ? 'rotate-90' : ''">chevron_right</span>
            </button>

            <div id="tv-sidebar-intervenants" x-show="openIntervenants" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 max-h-0"
                 x-transition:enter-end="opacity-100 max-h-96"
                 x-transition:leave="transition ease-in duration-120"
                 x-transition:leave-start="opacity-100 max-h-96"
                 x-transition:leave-end="opacity-0 max-h-0"
                 class="overflow-hidden ml-3 pl-3 border-l border-outline-variant/50 space-y-0.5 py-1.5">

                {{-- 1. Liste des intervenants --}}
                <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('intervenants.index') ? 'text-error bg-error-container/15 font-semibold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                   href="{{ $href('intervenants.index') }}">
                    <span class="w-1 h-1 rounded-full bg-on-surface-variant/40"></span>
                    <span class="min-w-0 truncate">Liste des intervenants</span>
                </a>

                {{-- 2. Parcours de l'intervenant (ROUGE comme capture) --}}
                <a class="px-3 py-2 rounded-lg flex items-center gap-2.5 whitespace-nowrap transition-colors text-sm {{ request()->routeIs('parcours-intervenants.*') || request()->routeIs('suivi-intervenants.*') ? 'text-error bg-error-container/15 font-bold' : 'text-on-surface-variant/90 hover:text-primary hover:bg-surface-container-high' }}"
                   href="{{ route('parcours-intervenants.index') }}">
                    <span class="w-1 h-1 rounded-full {{ request()->routeIs('parcours-intervenants.*') ? 'bg-error' : 'bg-on-surface-variant/40' }}"></span>
                    <span class="min-w-0 truncate">Parcours de l'intervenant</span>
                </a>
            </div>
        </div>

        @endcan

        @php
            $showAdminSection = auth()->check() && (
                auth()->user()->can('viewAny', \App\Models\User::class)
                || auth()->user()->can('viewAny', \App\Models\ActivityLog::class)
                || auth()->user()->hasAnyRole(['admin', 'superadmin'])
            );
            $canManageUsers = auth()->check() && auth()->user()->can('viewAny', \App\Models\User::class);
            $adminActive = request()->routeIs('users.*') || request()->routeIs('admin.activity-logs') || request()->routeIs('utilisateurs.permissions*');
        @endphp
        @if($showAdminSection)
        <section class="mt-5 mx-2 rounded-2xl border {{ $adminActive ? 'border-primary/20 bg-primary/[0.04]' : 'border-outline-variant/60 bg-surface-container-low/40' }} p-2" aria-label="Administration">
            <div class="px-3 pt-1.5 pb-2 flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <span class="material-symbols-outlined" style="font-size:16px;">admin_panel_settings</span>
                </span>
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-[0.18em] font-black text-primary leading-none">Administration</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Gestion et contrôle</p>
                </div>
            </div>

            <div class="space-y-0.5">
                @if($canManageUsers)
                <a class="group flex items-center gap-3 px-3 py-2.5 rounded-xl whitespace-nowrap transition-colors {{ request()->routeIs('users.*') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-highest' }}" href="{{ route('users.index') }}">
                    <span class="material-symbols-outlined shrink-0 {{ request()->routeIs('users.*') ? '' : 'text-primary/70 group-hover:text-primary' }}" style="font-size:20px;">manage_accounts</span>
                    <span class="min-w-0 truncate text-sm">Gestion utilisateurs</span>
                </a>
                @endif

                @if(auth()->check() && auth()->user()->can('viewAny', \App\Models\ActivityLog::class))
                <a class="group flex items-center gap-3 px-3 py-2.5 rounded-xl whitespace-nowrap transition-colors {{ request()->routeIs('admin.activity-logs') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-highest' }}" href="{{ route('admin.activity-logs') }}">
                    <span class="material-symbols-outlined shrink-0 {{ request()->routeIs('admin.activity-logs') ? '' : 'text-primary/70 group-hover:text-primary' }}" style="font-size:20px;">history</span>
                    <span class="min-w-0 truncate text-sm">Journal d’activité</span>
                </a>
                @endif

                @if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'superadmin']))
                <a class="group flex items-center gap-3 px-3 py-2.5 rounded-xl whitespace-nowrap transition-colors {{ request()->routeIs('utilisateurs.permissions*') ? 'bg-primary text-on-primary shadow-sm font-semibold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-highest' }}" href="{{ route('utilisateurs.permissions') }}">
                    <span class="material-symbols-outlined shrink-0 {{ request()->routeIs('utilisateurs.permissions*') ? '' : 'text-primary/70 group-hover:text-primary' }}" style="font-size:20px;">tune</span>
                    <span class="min-w-0 truncate text-sm">Droits par rôle</span>
                </a>
                @endif
            </div>
        </section>
        @endif
    </div>

    <!-- Footer User + Slogan -->
    <div class="mt-auto px-6 pt-4 border-t border-outline-variant">
        @auth
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 mb-4">
                @php
                    $u = auth()->user();
                    $name = $u?->name ?? 'Invité';
                    $photo = $u?->journaliste?->photoUrl ?? ($u?->intervenant?->photoUrl ?? null);
                    if (!$photo) {
                        $photo = 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=001a41&color=ffffff&size=128';
                    }
                    $roleName = method_exists($u, 'getRoleNames') ? ($u->getRoleNames()->first() ?? 'Utilisateur') : 'Utilisateur';
                @endphp
                <img alt="{{ $name }}" class="w-10 h-10 rounded-full border border-outline-variant object-cover" src="{{ $photo }}"/>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-primary truncate">{{ $name }}</div>
                    <div class="text-xs text-on-surface-variant truncate">{{ \Illuminate\Support\Str::headline($roleName) }}</div>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant">logout</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="text-xs font-semibold text-on-surface-variant hover:text-error w-full text-left">
                    Se déconnecter
                </button>
            </form>
        @endauth
        @guest
            <div class="text-xs font-bold text-on-surface-variant mb-4">
                <a href="{{ route('login') }}" class="hover:text-primary">Connexion →</a>
            </div>
        @endguest
        <div class="mt-4 flex gap-4 text-xs font-bold text-on-surface-variant">
            <span>Informer</span>
            <span>Coordonner</span>
            <span>Inspirer</span>
        </div>
    </div>
</nav>
