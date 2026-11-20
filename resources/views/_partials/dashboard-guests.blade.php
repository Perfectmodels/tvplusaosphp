<section class="ed-card ed-guests" aria-labelledby="ed-guests-title">
    <div class="ed-card-heading">
        <h2 id="ed-guests-title">Suivi des invités</h2>
        <a href="{{ route('suivi-intervenants.index') }}">Voir tout le suivi</a>
    </div>
    <div class="ed-operational-list" tabindex="0" role="region" aria-label="Invités programmés aujourd’hui">
        @forelse($suiviIntervenants as $parcours)
            @php $inv = $parcours->intervenant; @endphp
            <a href="{{ route('intervenants.show', $inv) }}" class="ed-guest-row">
                <img alt="" src="{{ $inv->photoUrl }}" class="ed-guest-avatar" loading="lazy">
                <div class="ed-operational-body">
                    <strong>{{ $inv->nomComplet }}</strong>
                    <small>{{ $inv->fonction }}@if($inv->organisme) · {{ $inv->organisme }}@endif</small>
                    @if($parcours->heure_passage_prevue)
                        <small>Passage {{ $parcours->heure_passage_prevue->format('H:i') }}</small>
                    @endif
                </div>
                <span class="ed-operational-status">{!! $parcours->badgeStatutActuel !!}</span>
            </a>
        @empty
            <div class="ed-empty ed-operational-empty">
                <p>Aucun invité programmé aujourd'hui.</p>
                <a class="ed-text-link" href="{{ route('suivi-intervenants.index') }}">Voir le suivi des intervenants</a>
            </div>
        @endforelse
    </div>
</section>
