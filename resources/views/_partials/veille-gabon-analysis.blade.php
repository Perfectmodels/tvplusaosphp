@if($subject->watch_analysis)
    <section class="mb-5 space-y-4 rounded-xl border border-primary/30 bg-primary/5 p-5" aria-label="Préparation IA de la veille gabonaise">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="font-bold text-primary">Veille Gabon · Préparation IA</h2><p class="mt-1 text-xs text-on-surface-variant">À relire et vérifier par la rédaction. Les affirmations proviennent de l’article source.</p></div>
            <span class="text-xs text-on-surface-variant">{{ $subject->watch_analyzed_at?->format('d/m/Y H:i') }} · {{ $subject->watch_model }}</span>
        </div>
        <p class="text-sm leading-6">{{ $subject->watch_analysis['resume'] }}</p>
        @foreach(['points_cles' => 'Éléments rapportés par la source', 'points_a_verifier' => 'Points à vérifier', 'angles' => 'Pistes de traitement'] as $key => $label)
            @if(! empty($subject->watch_analysis[$key]))
                <div><h3 class="mb-2 text-sm font-semibold">{{ $label }}</h3><ul class="list-disc space-y-1 pl-5 text-sm">@foreach($subject->watch_analysis[$key] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
            @endif
        @endforeach
        <p class="text-xs text-on-surface-variant">Rubrique proposée : {{ \App\Enums\PoleRubriqueEnum::tryFrom($subject->watch_analysis['rubrique_proposee'])?->shortLabel() }}</p>
        <a href="{{ $subject->watch_analysis['source_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-primary underline">Article d’origine · {{ $subject->watch_analysis['source_name'] }}</a>
    </section>
@elseif($subject->watch_error)
    <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $subject->watch_error }} L’article source reste disponible ci-dessous.</p>
@endif
