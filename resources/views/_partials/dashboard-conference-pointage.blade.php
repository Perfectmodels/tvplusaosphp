@if($pointageConference !== null)
<section id="dashboard-conference-pointage" data-dashboard-pointage data-url="{{ route('dashboard.pointage') }}" class="ed-pointage mb-4 rounded-xl border border-outline-variant bg-white" aria-label="Pointage des conférences de rédaction">
    <details id="pointage-conference-details">
        <summary id="pointage-summary" class="ed-pointage-summary">
            <span class="ed-pointage-heading">
                <span class="ed-pointage-title">Pointage · Conférences de rédaction</span>
                <span class="ed-pointage-subtitle">{{ \Illuminate\Support\Carbon::parse($pointageConference['jour'])->translatedFormat('l j F') }} · {{ $pointageConference['totaux']['presences'] }} présence(s)</span>
            </span>
            <span class="ed-pointage-totals" aria-label="Bilan du pointage">
                <span class="rounded-full bg-green-100 px-2 py-1 text-green-800"><strong data-pointage-total="a_l_heure">{{ $pointageConference['totaux']['a_l_heure'] }}</strong> à l’heure</span>
                <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-800"><strong data-pointage-total="en_retard">{{ $pointageConference['totaux']['en_retard'] }}</strong> en retard</span>
                <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700"><strong data-pointage-total="a_pointer">{{ $pointageConference['totaux']['a_pointer'] }}</strong> à pointer</span>
            </span>
            <span class="ed-pointage-action">
                <span>Voir personne</span>
                <span class="material-symbols-outlined ed-pointage-chevron" aria-hidden="true">expand_more</span>
            </span>
        </summary>
        <div class="ed-pointage-content">
            <p class="ed-pointage-meta">{{ $pointageConference['totaux']['conferences'] }} conférence(s) · Mis à jour à <time data-pointage-updated>{{ now()->format('H:i:s') }}</time></p>
            <div id="pointage-personnes-list" data-pointage-list class="ed-pointage-list divide-y divide-outline-variant/50" tabindex="0" aria-label="Détail des personnes et de leur ponctualité">
        @forelse($pointageConference['conferences'] as $conference)
            <article class="py-3 first:pt-0 last:pb-0" aria-label="{{ $conference['nom'] }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 text-sm">
                        <a id="pointage-conference-{{ $conference['id'] }}" href="{{ $conference['href'] }}" class="font-semibold text-primary hover:underline">{{ $conference['nom'] }}</a>
                        <p class="mt-1 text-xs text-on-surface-variant">Édition : {{ $conference['edition'] ?? 'date à renseigner' }} · {{ $conference['debut'] ? 'Début : '.$conference['debut'] : 'Conférence non démarrée' }}</p>
                    </div>
                    <a id="pointage-action-{{ $conference['id'] }}" href="{{ $conference['href'] }}" class="inline-flex min-h-[44px] items-center text-xs font-semibold text-primary underline">Ouvrir le pointage</a>
                </div>
                @if($conference['personnes'] !== [])
                        <ul class="mt-2 space-y-1" aria-label="Personnes présentes à {{ $conference['nom'] }}">
                            @foreach($conference['personnes'] as $personne)
                                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                    <div class="min-w-0"><span class="break-words font-medium text-primary">{{ $personne['nom_complet'] }}</span><span class="mt-1 block text-xs text-on-surface-variant">Arrivée : {{ $personne['arrivee_complete_fr'] }}</span></div>
                                    <span @class(['rounded-full px-2 py-1 text-xs font-semibold', 'bg-green-100 text-green-800' => $personne['ponctualite'] === 'a_l_heure', 'bg-amber-100 text-amber-800' => $personne['ponctualite'] === 'en_retard', 'bg-slate-200 text-slate-700' => $personne['ponctualite'] === null])>{{ $personne['ponctualite_label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                @else
                    <p class="mt-2 text-xs text-on-surface-variant">Aucune présence enregistrée.</p>
                @endif
            </article>
        @empty
            <p class="text-sm text-on-surface-variant">Aucune conférence de rédaction pour aujourd’hui.</p>
        @endforelse
            </div>
        </div>
    </details>
    <p data-pointage-connection hidden class="px-4 pb-3 text-xs text-amber-700" role="status">Actualisation interrompue. Nouvelle tentative automatique.</p>
</section>
@endif
