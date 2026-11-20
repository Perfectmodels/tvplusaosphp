@php
    $distributionTotal = collect($distribution)->sum('total');
    $distributionOffset = 0;
    $distributionSegments = [];
    foreach($distribution as $entry) {
        $start = $distributionTotal ? $distributionOffset / $distributionTotal * 100 : 0;
        $distributionOffset += $entry['total'];
        $end = $distributionTotal ? $distributionOffset / $distributionTotal * 100 : 0;
        $distributionSegments[] = $entry['color'].' '.$start.'% '.$end.'%';
    }
@endphp
<section class="ed-card ed-state">
    <div class="ed-card-heading"><h2>État de l’activité</h2></div>
    @if($distributionTotal > 0)
        <div class="ed-production">
            <div class="ed-donut" style="background: conic-gradient({{ implode(', ', $distributionSegments) }})" role="img" aria-label="{{ $distributionTotal }} enregistrements répartis par statut">
                <div><strong>{{ $distributionTotal }}</strong><small>Total</small></div>
            </div>
            <ul class="ed-legend">
                @foreach($distribution as $entry)
                    <li><span class="ed-dot" style="background: {{ $entry['color'] }}"></span><span>{{ $entry['label'] }}</span><b>{{ $entry['total'] }}</b></li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="ed-role-empty ed-role-empty--soft">Aucun enregistrement correspondant dans votre périmètre.</div>
    @endif
</section>
