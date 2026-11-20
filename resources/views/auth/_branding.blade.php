{{--
    Colonne de marque des pages d'authentification (connexion, mot de passe, …).
--}}
<aside class="w-full md:w-1/2 h-full flex flex-col justify-between px-6 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-12">

    {{-- Marque --}}
    <header>
        <x-brand.wordmark class="tv-brand-logo text-[2.6rem]" />
        <div class="tv-brand-sub">TV+ Afrique</div>
    </header>

    {{-- Visuel de marque (masqué sur petit écran) --}}
    <div class="tv-eye-wrap hidden md:block" aria-hidden="true">
        <div class="tv-eye"></div>
        <svg class="tv-constellation" viewBox="0 0 100 100" fill="none" aria-hidden="true" focusable="false">
            <line x1="50" y1="20" x2="75" y2="35" />
            <line x1="75" y1="35" x2="70" y2="65" />
            <line x1="70" y1="65" x2="30" y2="65" />
            <line x1="30" y1="65" x2="25" y2="35" />
            <line x1="25" y1="35" x2="50" y2="20" />
            <circle cx="50" cy="20" r="2.2" />
            <circle cx="75" cy="35" r="2.2" />
            <circle cx="70" cy="65" r="2.2" />
            <circle cx="30" cy="65" r="2.2" />
            <circle cx="25" cy="35" r="2.2" />
            <circle cx="50" cy="50" r="2.8" />
        </svg>
    </div>

    {{-- Baseline --}}
    <footer>
        <p class="tv-tagline mb-3">{{ __('Une vision. Toutes vos équipes.') }}</p>
        <p class="tv-tagline-sub m-0 hidden sm:block">{{ __('INFORMER. COORDONNER. INSPIRER.') }}</p>
    </footer>
</aside>
