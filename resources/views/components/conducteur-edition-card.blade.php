@props([
    'conducteur',
    'title',
    'subtitle',
    'metrics' => [],
    'progress' => 0,
    'actionUrl',
    'actionLabel',
    'actionIcon' => 'fa-arrow-right',
    'secondaryActionUrl' => null,
    'secondaryActionLabel' => null,
    'secondaryActionIcon' => 'fa-pen-to-square',
    'activeSequence' => null,
    'showActiveSequenceState' => false,
    'bulkResource' => null,
    'deleteUrl' => null,
    'deleteItemType' => 'séquences',
])

@php($progress = min(100, max(0, (int) $progress)))

<article {{ $attributes->merge(['class' => 'group relative flex h-full flex-col overflow-hidden rounded-xl border border-outline/20 bg-white transition-colors hover:border-primary/35']) }}
         data-conducteur-id="{{ $conducteur->id }}"
         data-conducteur-code="{{ $conducteur->code_conducteur_affichage }}"
         data-conducteur-titre="{{ $title }}"
         data-conducteur-date="{{ $conducteur->date_edition?->format('d/m/Y') }}"
         data-conducteur-nb-sujets="{{ $conducteur->sequences->count() }}"
         @if($deleteUrl) data-url-delete="{{ $deleteUrl }}" @endif>
    <div class="flex-1 p-5">
        <div class="flex min-w-0 items-start justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                @if($bulkResource)
                    @include('_partials.bulk-delete-checkbox', [
                        'resource' => $bulkResource,
                        'record' => $conducteur,
                        'label' => $title,
                    ])
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-md bg-primary/8 px-2.5 py-1 text-xs font-semibold text-primary">
                    <i class="fa-solid fa-fingerprint" aria-hidden="true"></i>
                    {{ $conducteur->code_conducteur_affichage }}
                </span>
                {!! $conducteur->type_badge_html !!}
                {!! $conducteur->statut_badge_html !!}
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <time class="text-xs tabular-nums text-on-surface-variant"
                      datetime="{{ $conducteur->date_edition?->toDateString() }}">
                    {{ $conducteur->date_edition?->format('d/m/Y') ?? 'Date à renseigner' }}
                </time>
                @if($deleteUrl)
                    @can('delete', $conducteur)
                        <button type="button"
                                class="btn-delete-conducteur inline-flex h-11 w-11 items-center justify-center rounded-full text-error transition-colors hover:bg-error/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-error"
                                title="Supprimer ce conducteur et tous ses {{ $deleteItemType }}"
                                aria-label="Supprimer le conducteur {{ $conducteur->code_conducteur_affichage }}">
                            <i class="fa-solid fa-trash text-xs" aria-hidden="true"></i>
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        <h2 class="mt-4 break-words text-lg font-semibold leading-snug text-on-surface">
            {{ $title }}
        </h2>
        <p class="mt-1 min-h-5 break-words text-sm text-on-surface-variant">
            {{ $subtitle }}
        </p>

        @if($showActiveSequenceState)
            <p class="mt-4 flex min-h-10 items-center gap-2 rounded-lg bg-surface-container-low px-3 py-2 text-sm {{ $activeSequence ? 'text-primary' : 'text-on-surface-variant' }}">
                <i class="fa-solid {{ $activeSequence ? 'fa-circle-play' : 'fa-circle-pause' }} shrink-0" aria-hidden="true"></i>
                @if($activeSequence)
                    <span class="min-w-0 truncate">
                        En cours · #{{ $activeSequence->position }} · {{ $activeSequence->title }}
                    </span>
                @else
                    <span>Aucune séquence activée</span>
                @endif
            </p>
        @endif
    </div>

    <div class="mt-auto">
        <dl class="mx-5 grid {{ count($metrics) === 2 ? 'grid-cols-2' : 'grid-cols-3' }} divide-x divide-outline/15 border-y border-outline/10 py-3">
            @foreach($metrics as $metric)
                <div class="min-w-0 px-3 first:pl-0 last:pr-0">
                    <dt class="truncate text-xs text-on-surface-variant">{{ $metric['label'] }}</dt>
                    <dd class="mt-1 truncate text-base font-semibold tabular-nums text-on-surface">{{ $metric['value'] }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="px-5 py-4">
            <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                <span class="text-on-surface-variant">Avancement</span>
                <span class="font-semibold tabular-nums text-primary">{{ $progress }}%</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-surface-container-high"
                 role="progressbar"
                 aria-label="Avancement de {{ $title }}"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-valuenow="{{ $progress }}">
                <div class="h-full rounded-full bg-primary transition-[width] duration-300" style="width: {{ $progress }}%"></div>
            </div>
        </div>

        <div class="flex items-center gap-2 border-t border-outline/10 px-5 py-3">
            <a href="{{ $actionUrl }}"
               class="inline-flex min-h-11 {{ $secondaryActionUrl ? 'flex-1' : 'w-full' }} items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-container hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                {{ $actionLabel }}
                <i class="fa-solid {{ $actionIcon }} text-xs" aria-hidden="true"></i>
            </a>
            @if($secondaryActionUrl)
                <a href="{{ $secondaryActionUrl }}"
                   class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg px-3 text-sm font-medium text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <i class="fa-solid {{ $secondaryActionIcon }} text-xs" aria-hidden="true"></i>
                    <span>{{ $secondaryActionLabel }}</span>
                </a>
            @endif
        </div>
    </div>
</article>
