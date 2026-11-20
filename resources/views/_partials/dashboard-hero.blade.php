@php
    $heroStats = collect($heroStats ?? []);
    $heroTitle = $heroTitle ?? 'Indicateurs de votre espace';
@endphp
@if($heroStats->isNotEmpty())
<section class="ed-kpis ed-role-kpis" aria-label="{{ $heroTitle }}" style="grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));">
    @foreach($heroStats as $stat)
        @php $heroStatVide = (int) $stat['value'] === 0; @endphp
        <article class="ed-kpi">
            <span class="ed-kpi-icon ed-tone-{{ $stat['tone'] ?? ['blue', 'green', 'purple', 'red'][$loop->index % 4] }}"><span class="material-symbols-outlined" aria-hidden="true">{{ $stat['icon'] }}</span></span>
            <div>
                <h2>{{ $stat['label'] }}</h2>
                <strong>{{ $stat['value'] }}</strong>
                @if($heroStatVide)
                    <small>Aucun enregistrement</small>
                @else
                    <a href="{{ $stat['href'] ?? '#workspace-details' }}">{{ $stat['detail'] ?? 'Voir les détails' }}</a>
                @endif
            </div>
        </article>
    @endforeach
</section>
@endif
