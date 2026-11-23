<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Favicons : œil réaliste de l'intro --}}
    @include('partials.favicon')

    {{-- PWA : application installable (manifest, icônes, couleur de thème) --}}
    <link rel="manifest" href="/manifest.webmanifest?v=2">
    <meta name="theme-color" content="#001a41">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="TV+A OS">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ $title ?? config('app.name', 'TV+A OS') }}</title>

    {{-- Google Fonts Inter + Material Symbols + FontAwesome (STRICTE PROTOTYPE HTML) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        #app-sidebar { height: 100vh; height: 100dvh; max-width: 100%; }
        .tv-nav-links { overscroll-behavior: contain; }
        .tv-nav-links > * { flex-shrink: 0; }
        #app-sidebar :is(a, button):focus-visible { outline: 2px solid currentColor; outline-offset: -3px; }
        @media (max-width: 1023.98px) {
            #app-sidebar.-translate-x-full { visibility: hidden; }
            .tv-nav-links :is(a, button) { min-height: 44px; }
        }
        @media (max-height: 500px) {
            #app-sidebar { overflow-y: auto; }
            #app-sidebar .tv-nav-links { flex: none; overflow: visible; }
        }

        body { font-family: 'Inter', sans-serif; }
        /* ===== BLOC OFFICIEL GOOGLE MATERIAL SYMBOLS (rendu icône LIGATURES) =====
           Sans ce bloc complet, le browser affiche les NOMS des icônes en TEXTE
           (ex: "presentation" 12 lettres au lieu du pictogramme).
           C'était la cause exacte de "PRES@ATION" affiché dans sidebar. */
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined' !important;
            font-weight: normal !important;
            font-style: normal !important;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            font-feature-settings: 'liga';
            font-feature-settings: 'liga' 1;
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;
        }
        /* Badge & Tag System (STRICTE prototype HTML) */
        .badge {
            @apply inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold tracking-wide;
        }
        .badge-blue   { @apply bg-blue-100 text-blue-700; }
        .badge-green  { @apply bg-green-100 text-green-700; }
        .badge-orange { @apply bg-orange-100 text-orange-700; }
        .badge-red    { @apply bg-red-100 text-red-700; }
        .badge-purple { @apply bg-purple-100 text-purple-700; }
        .badge-indigo { @apply bg-indigo-100 text-indigo-700; }
        .badge-gray   { @apply bg-gray-200 text-gray-700; }
        .tag {
            @apply inline-block px-2 py-0.5 text-xs font-semibold bg-surface-container-high text-on-surface-variant rounded-md border border-outline-variant/70;
        }
        .table-header { @apply text-xs uppercase tracking-widest text-on-surface-variant; }

        /* ===========================================================
           NAVBAR COMPACTE AU SCROLL
           Au-delà de ~24px de scroll, la topbar se réduit en hauteur et
           le logo / avatar perdent 25% de leur taille.
           =========================================================== */
        .ui-topbar {
            transition: height .28s cubic-bezier(.4,0,.2,1), box-shadow .28s ease, background-color .28s ease;
        }
        .ui-logo {
            transform-origin: left center;
            transition: transform .28s cubic-bezier(.4,0,.2,1), opacity .28s ease;
        }
        .ui-avatar {
            transition: transform .28s cubic-bezier(.4,0,.2,1), width .28s ease, height .28s ease;
        }
        .ui-subtitle {
            transition: opacity .22s ease, max-height .28s ease, margin .28s ease;
            overflow: hidden;
            max-height: 2rem;
        }

        /* État compact : déclenché par le JS au scroll */
        html.ui-compact .ui-topbar {
            height: auto !important;
            min-height: 3.5rem;
            box-shadow: 0 4px 14px rgba(0,26,65,.14) !important;
        }
        html.ui-compact .ui-logo {
            transform: scale(.75);               /* -25% */
        }
        html.ui-compact .ui-avatar {
            transform: scale(.75);               /* -25% */
        }
        html.ui-compact .ui-subtitle {
            opacity: 0;
            max-height: 0;
            margin-top: 0;
        }
        /* Le bloc logo de la sidebar se réduit aussi de 25% */
        html.ui-compact .ui-sidebar-logo {
            transform: scale(.75);
            transform-origin: top left;
        }
        html.ui-compact .ui-sidebar-oeil {
            max-height: 96px;                    /* 130px -> 96px ≈ -25% */
            opacity: .92;
        }
        .ui-sidebar-logo,
        .ui-sidebar-oeil {
            transition: transform .3s cubic-bezier(.4,0,.2,1), max-height .3s ease, opacity .3s ease;
        }
    </style>


    <style>
        :root { --navbar-height: 80px; }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @vite(['resources/css/welcome-eye.css', 'resources/js/welcome-eye.js'])
    @stack('styles')
    @can('viewAny', \App\Models\WorkspaceDossier::class) @vite('resources/js/shared-workspace.js') @endcan
</head>
<body class="app-shell bg-background text-on-background font-body-sm antialiased h-screen flex overflow-hidden">

    <a href="#app-main" class="ui-skip-link">Aller au contenu principal</a>

    {{-- Sidebar gauche w-64 FIXED (inclus Logo + œil + slogan + 10 liens nav + User footer) --}}
    @include('_partials.sidebar')

    {{-- Overlay mobile quand le drawer est ouvert --}}
    <div id="sidebar-overlay" aria-hidden="true" class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden"></div>

    {{-- Zone principale FUNCTIONAL (STRICTE prototype HTML dashboard.html#L198-L237) --}}
    <div class="flex-1 flex flex-col lg:pl-64 w-full min-w-0 h-full relative">

        {{-- Compatibilité composant <x-app-layout> : le slot "header" devient le titre de la topbar --}}
        @php
            $titreDepuisSlot = ($header ?? null) instanceof \Illuminate\View\ComponentSlot
                ? trim(strip_tags((string) $header))
                : null;
        @endphp

        {{-- TopAppBar Header h-20 sticky (titre + breadcrumb + search global + boutons actions + avatar user) --}}
        @include('_partials.page-header', [
            'pageTitle'     => $pageTitle     ?? ($titreDepuisSlot !== null && $titreDepuisSlot !== '' ? $titreDepuisSlot : config('app.name', 'TV+A OS')),
            'pageSubtitle'  => $pageSubtitle  ?? null,
            'headerActions' => $headerActions ?? null,
            'showGlobalSearch' => $showGlobalSearch ?? true,
        ])

        {{-- Canvas / Contenu page (overflow-y-auto + scrollbar-hide + bg surface gris #F4F7F9 prototype) --}}
        <main id="app-main" tabindex="-1" class="flex-1 min-w-0 overflow-y-auto p-4 sm:p-6 lg:p-8 scrollbar-hide {{ request()->routeIs('dashboard') ? 'bg-[#fbfcfe]' : 'bg-[#F4F7F9]' }}">

            @include('_partials.notifications-stack')
            @include('_partials.notifications-globales')
            @include('_partials.flash-status')

            {{-- Contenu variable des pages CRUD (section @extends) ou du composant <x-app-layout> (slot) --}}
            @yield('content')
            @if(($slot ?? null) instanceof \Illuminate\View\ComponentSlot)
                {{ $slot }}
            @endif
        </main>
    </div>

    @auth
        @include('_partials.assistant')
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"
            referrerpolicy="no-referrer"></script>
    <script>
        window.SwalConfig = {
            allowOutsideClick: false,
            allowEscapeKey: true,
            buttonsStyling: false,
            reverseButtons: true,
            customClass: {
                confirmButton: 'px-5 py-2.5 rounded-lg bg-error text-white font-semibold shadow-sm hover:brightness-110 transition-all',
                cancelButton:  'px-5 py-2.5 rounded-lg bg-surface-container text-on-surface font-semibold border border-outline/20 hover:bg-surface-container-high transition-all',
                popup:         'rounded-2xl shadow-2xl border border-outline/10',
                header:        'pt-6',
                title:         '!font-semibold !text-on-surface !text-lg',
                htmlContainer: '!text-on-surface-variant !text-sm !leading-relaxed',
                closeButton:   'text-on-surface-variant hover:text-on-surface',
                icon:          '!mt-2'
            }
        };
        window.TvAlert = {
            /**
             * Confirmation générique de SUPPRESSION (SweetAlert).
             * @param {Object}  o
             * @param {string} [o.title]      Titre de la confirmation.
             * @param {string} [o.text]       Message détaillé (HTML accepté).
             * @param {string} [o.confirmText] Libellé du bouton de confirmation.
             * @returns {Promise<SweetAlertResult>}
             */
            confirmDelete(o) {
                o = o || {};
                return Swal.fire({
                    icon: 'warning',
                    title: o.title || 'Supprimer cet élément ?',
                    html: o.text || 'Cette action est irréversible.',
                    showCancelButton: true,
                    confirmButtonText: o.confirmText || '<i class="fa-solid fa-trash mr-1.5"></i> Supprimer',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#b3261e',
                    ...window.SwalConfig
                });
            },
            confirmDeleteConducteur(titre, details) {
                return window.TvAlert.confirmDelete({ title: titre, text: details });
            },
            /**
             * Confirmation neutre (action non destructive).
             */
            confirm(o) {
                o = o || {};
                return Swal.fire({
                    icon: o.icon || 'question',
                    title: o.title || 'Confirmer ?',
                    html: o.text || '',
                    showCancelButton: true,
                    confirmButtonText: o.confirmText || 'Confirmer',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#1f6fd1',
                    ...window.SwalConfig
                });
            },
            success(text, { timer = 2200, title = 'Effectué ✅' } = {}) {
                return Swal.fire({
                    icon: 'success',
                    title,
                    text: text || '',
                    timer,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: false,
                    ...window.SwalConfig
                });
            },
            error(text, { title = 'Erreur' } = {}) {
                return Swal.fire({ icon: 'error', title, text: text || '', showConfirmButton: true, confirmButtonText: 'OK', ...window.SwalConfig });
            }
        };

        // -------------------------------------------------------------
        // CONFIRMATION AVANT SOUMISSION — interception générique.
        //   <form data-confirm-delete="Titre">  → style danger (suppression)
        //   <form data-confirm="Titre">         → style neutre (autre action)
        // -------------------------------------------------------------
        document.addEventListener('submit', function (event) {
            const form = event.target;

            if (!form || typeof form.matches !== 'function') {
                return;
            }

            const isDelete = form.matches('form[data-confirm-delete]');
            const isConfirm = form.matches('form[data-confirm]');
            if (!isDelete && !isConfirm) {
                return;
            }

            // La soumission programmée après confirmation ne doit pas reboucler.
            if (form.dataset.confirmHandled === '1') {
                form.dataset.confirmHandled = '';
                return;
            }

            event.preventDefault();

            const titre = isDelete
                ? form.getAttribute('data-confirm-delete')
                : form.getAttribute('data-confirm');

            const promesse = isDelete
                ? window.TvAlert.confirmDelete({
                    title: titre,
                    text: form.getAttribute('data-confirm-delete-text'),
                    confirmText: form.getAttribute('data-confirm-delete-label')
                })
                : window.TvAlert.confirm({
                    title: titre,
                    text: form.getAttribute('data-confirm-text'),
                    confirmText: form.getAttribute('data-confirm-label')
                });

            promesse.then(function (result) {
                if (result.isConfirmed) {
                    form.dataset.confirmHandled = '1';
                    form.submit();
                }
            });
        }, true);
    </script>

    {{-- Drawer sidebar mobile : ouverture/fermeture + overlay --}}
    <script>
    (function () {
        'use strict';

        var sidebar = document.getElementById('app-sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var toggle = document.getElementById('sidebar-toggle');
        if (!sidebar) return;

        function estFerme() { return sidebar.classList.contains('-translate-x-full'); }

        function ouvrir() {
            sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.remove('hidden');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Fermer le menu de navigation');
            }
            if (window.innerWidth < 1024) {
                document.documentElement.classList.add('ui-sidebar-open');
                (sidebar.querySelector('[data-sidebar-close]') || sidebar).focus({ preventScroll: true });
            }
        }

        function fermer(restoreFocus) {
            sidebar.classList.add('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
            document.documentElement.classList.remove('ui-sidebar-open');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Ouvrir le menu de navigation');
            }
            if (restoreFocus && toggle && window.innerWidth < 1024) toggle.focus();
        }

        document.addEventListener('click', function (event) {
            var cible = event.target && typeof event.target.closest === 'function' ? event.target : null;
            if (!cible) return;
            if (cible.closest('#sidebar-toggle')) {
                event.preventDefault();
                if (estFerme()) ouvrir(); else fermer(true);
                return;
            }
            if (cible.closest('[data-sidebar-close]')) {
                event.preventDefault();
                fermer(true);
                return;
            }
            if (overlay && !overlay.classList.contains('hidden')
                && (cible === overlay || cible.closest('#app-sidebar a'))) {
                fermer(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay && !overlay.classList.contains('hidden')) {
                event.preventDefault();
                fermer(true);
            }
        });

        // Keep keyboard navigation inside the mobile drawer while it is open.
        sidebar.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || window.innerWidth >= 1024 || estFerme()) return;
            var candidates = Array.from(sidebar.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(function (el) { return el.getClientRects().length > 0; });
            if (!candidates.length) { event.preventDefault(); sidebar.focus(); return; }
            var first = candidates[0], last = candidates[candidates.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) fermer(false);
        }, { passive: true });
    })();
    </script>

    {{-- Navbar compacte au scroll : logo + avatar -25%, hauteur réduite --}}
    <script>
    (function () {
        'use strict';

        var SEUIL = 20;   // px de scroll avant passage en compact
        var actif = null; // null = jamais initialisé
        var ticking = false;

        // --- Cibles (recherchées à chaque tick : robuste aux changements de DOM) ---
        function cibles() {
            return {
                topbar:    document.querySelector('.ui-topbar'),
                logos:     document.querySelectorAll('.ui-logo'),
                avatars:   document.querySelectorAll('.ui-avatar'),
                sousTitres:document.querySelectorAll('.ui-subtitle'),
                logoSidebar:document.querySelectorAll('.ui-sidebar-logo'),
                oeilSidebar:document.querySelectorAll('.ui-sidebar-oeil'),
            };
        }

        function appliquer(compact) {
            var t = cibles();

            if (t.topbar) {
                // La hauteur reste libre pour les lignes d’actions sur mobile.
                if (compact) {
                    t.topbar.style.setProperty('min-height', '56px');
                    t.topbar.style.setProperty('box-shadow', '0 4px 14px rgba(0,26,65,.14)', 'important');
                } else {
                    t.topbar.style.removeProperty('min-height');
                    t.topbar.style.removeProperty('box-shadow');
                }
            }

            var i;
            for (i = 0; i < t.logos.length; i++) {
                t.logos[i].style.setProperty('transform', compact ? 'scale(0.75)' : 'scale(1)', 'important');
                t.logos[i].style.setProperty('transform-origin', 'left center', 'important');
            }
            for (i = 0; i < t.avatars.length; i++) {
                t.avatars[i].style.setProperty('transform', compact ? 'scale(0.75)' : 'scale(1)', 'important');
            }
            for (i = 0; i < t.sousTitres.length; i++) {
                t.sousTitres[i].style.setProperty('opacity', compact ? '0' : '1', 'important');
                t.sousTitres[i].style.setProperty('max-height', compact ? '0px' : '2rem', 'important');
                t.sousTitres[i].style.setProperty('margin-top', compact ? '0' : '0.125rem', 'important');
            }
            for (i = 0; i < t.logoSidebar.length; i++) {
                t.logoSidebar[i].style.setProperty('transform', compact ? 'scale(0.75)' : 'scale(1)', 'important');
                t.logoSidebar[i].style.setProperty('transform-origin', 'top left', 'important');
            }
            for (i = 0; i < t.oeilSidebar.length; i++) {
                t.oeilSidebar[i].style.setProperty('max-height', compact ? '96px' : '130px', 'important');
                t.oeilSidebar[i].style.setProperty('opacity', compact ? '0.92' : '1', 'important');
            }

            document.documentElement.classList.toggle('ui-compact', compact);
        }

        function hauteurScroll() {
            // Le scroll peut venir de <main>, de la sidebar, de la fenêtre…
            var main = document.querySelector('main');
            var nav  = document.querySelector('nav');
            return Math.max(
                main ? main.scrollTop : 0,
                nav ? nav.scrollTop : 0,
                window.scrollY || window.pageYOffset || 0,
                document.scrollingElement ? document.scrollingElement.scrollTop : 0
            );
        }

        function maj() {
            var compact = hauteurScroll() > SEUIL;
            if (compact !== actif) {
                actif = compact;
                appliquer(compact);
            }
            ticking = false;
        }

        function auScroll() {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(maj);
            }
        }

        function init() {
            // 1. Écouteur en phase de capture (les événements scroll ne remontent pas)
            document.addEventListener('scroll', auScroll, true);

            // 2. Gestionnaires DIRECTS sur chaque conteneur de scroll possible
            var conteneurs = [
                document.querySelector('main'),
                document.querySelector('nav'),
                document.scrollingElement || document.documentElement,
                window
            ];
            for (var k = 0; k < conteneurs.length; k++) {
                if (conteneurs[k] && typeof conteneurs[k].addEventListener === 'function') {
                    conteneurs[k].addEventListener('scroll', auScroll, { passive: true });
                }
            }

            window.addEventListener('resize', auScroll, { passive: true });

            // 3. GARDE-FOU : vérification périodique de la position de scroll.
            //    Indépendant de tout événement -> fonctionne dans TOUS les cas.
            setInterval(function () { maj(); }, 250);

            // 4. Transitions fluides (injectées en JS : aucune dépendance au CSS)
            if (!document.getElementById('ui-compact-transitions')) {
                var style = document.createElement('style');
                style.id = 'ui-compact-transitions';
                style.textContent =
                    '.ui-topbar,.ui-logo,.ui-avatar,.ui-subtitle,.ui-sidebar-logo,.ui-sidebar-oeil{' +
                    'transition:height .28s cubic-bezier(.4,0,.2,1),transform .28s cubic-bezier(.4,0,.2,1),' +
                    'opacity .22s ease,max-height .28s ease,margin .28s ease,box-shadow .28s ease;}';
                document.head.appendChild(style);
            }

            maj(); // état initial
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>

    @stack('scripts')

    {{-- PWA : enregistrement du service worker (cache statique + page hors-ligne). --}}
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js?v=2', { scope: '/' }).catch(function () {});
            });
        }
    </script>
</body>
</html>
