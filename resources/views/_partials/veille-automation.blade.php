<section id="veille-automation-panel" class="mb-6 rounded-xl border border-outline-variant bg-white p-4 sm:p-5" aria-label="Collecte automatique" aria-live="polite">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 text-sm font-bold text-primary">
                <span @class(['inline-block h-2.5 w-2.5 rounded-full', 'bg-emerald-500' => $automation['state'] === 'running', 'bg-amber-500' => $automation['state'] !== 'running']) aria-hidden="true"></span>
                Veille automatique
            </h2>
            <p class="mt-2 text-sm text-on-surface-variant">{{ $automation['message'] }}</p>
        </div>
        <span class="text-xs text-on-surface-variant">{{ $automation['sources_active'] }} source(s) active(s) · {{ $automation['articles_total'] }} article(s)</span>
    </div>
    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-on-surface-variant">
        <span>Dernière collecte réussie : {{ $automation['last_success_at'] ? \Illuminate\Support\Carbon::parse($automation['last_success_at'])->diffForHumans() : 'pas encore de résultat' }}</span>
        <span>{{ $automation['sources_due'] }} source(s) à collecter</span>
        @if($automation['sources_errors'])<a href="{{ route('veille.sources', ['status' => 'error']) }}" class="font-semibold text-amber-700 underline">{{ $automation['sources_errors'] }} source(s) à réessayer</a>@endif
        <span>Actualisation de l’affichage toutes les 30 secondes</span>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-outline-variant pt-3 text-xs text-on-surface-variant">
        <a href="{{ route('veille.sujets.index', ['country' => 'GA']) }}" class="inline-flex min-h-[44px] items-center font-semibold text-primary underline">Veille Gabon · {{ $automation['gabon_articles'] }} article(s)</a>
        <span>{{ $automation['gabon_analyzed'] }} préparation(s) IA disponible(s)</span>
        @if($automation['gabon_ai_enabled'] && $automation['ai_available'])
            <span>Agent IA actif · {{ $automation['gabon_ai_used'] }}/{{ $automation['gabon_ai_limit'] }} appels aujourd’hui · validation rédactionnelle requise</span>
            @if($automation['gabon_ai_used'] >= $automation['gabon_ai_limit'])<span class="text-amber-800">Plafond quotidien atteint : les analyses reprendront demain. La collecte continue.</span>@endif
        @else
            <span>Préparation IA indisponible ou désactivée · collecte des sources maintenue</span>
        @endif
    </div>
</section>
