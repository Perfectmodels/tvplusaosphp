<section class="ed-card ed-planning" aria-labelledby="ed-planning-title">
    <div class="ed-card-heading">
        <h2 id="ed-planning-title">Planning du jour</h2>
        <a href="{{ route('conducteurs.emission.index', ['date' => today()->toDateString()]) }}">Voir les conducteurs</a>
    </div>
    <div class="ed-operational-list" tabindex="0" role="region" aria-label="Planning des émissions du jour">
        @forelse($planningAujourdhui as $ligne)
            <article class="ed-planning-row">
                <time>{{ $ligne['heure'] }}</time>
                <div class="ed-operational-body">
                    <strong>{{ $ligne['emission'] }}</strong>
                    <small>Présenté par {{ $ligne['presentateur'] }}</small>
                    <small>{{ $ligne['plateau'] }}</small>
                </div>
                <span class="ed-operational-status">{!! $ligne['badge'] !!}</span>
            </article>
        @empty
            <div class="ed-empty ed-operational-empty">
                <p>Aucune émission programmée aujourd'hui.</p>
                <a class="ed-text-link" href="{{ route('emissions.index') }}">Voir les émissions</a>
            </div>
        @endforelse
    </div>
</section>
