@php
    /** @var \App\Models\Conducteur $conducteur */
    /** @var \App\Models\Sondage|null $sondage */
    $emission = $conducteur->emission;
    $estDebat = $emission && $emission->type === \App\Enums\TypeEmissionEnum::Debat;
    $peutGerer = $emission && auth()->user()?->can('create', [\App\Models\Sondage::class, $emission]);
@endphp

@if($estDebat)
    <section class="h-full rounded-xl border border-outline/15 bg-white p-5 shadow-sm" aria-label="Sondage du débat">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-on-surface">
                    <i class="fa-solid fa-chart-bar text-primary" aria-hidden="true"></i>
                    Sondage du débat
                </h3>
                <p class="mt-0.5 text-xs text-on-surface-variant">Désignez le gagnant du débat avec un vote du public par QR code.</p>
            </div>
            @if($sondage)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $sondage->statut->badgeClass() }}">
                    {{ $sondage->statut->label() }}
                </span>
            @endif
        </div>

        @if(! $sondage)
            @if(! $estDebat)
                <p class="rounded-lg bg-surface-container-low px-3 py-3 text-sm text-on-surface-variant">
                    Le sondage est disponible uniquement pour les émissions de type <strong>Débat</strong>.
                    Modifiez le type de l’émission pour l’activer.
                </p>
            @elseif($peutGerer)
                <a href="{{ route('sondages.create', ['emission' => $emission, 'conducteur_id' => $conducteur->id]) }}"
                   class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-container hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Créer le sondage
                </a>
            @else
                <p class="rounded-lg bg-surface-container-low px-3 py-2.5 text-sm text-on-surface-variant">
                    Aucun sondage n'est encore configuré pour ce débat.
                </p>
            @endif
        @else
            <p class="mb-4 text-sm font-semibold text-on-surface">{{ $sondage->question }}</p>

            <div class="grid gap-5 lg:grid-cols-[220px_1fr]">
                <div class="flex flex-col items-center gap-2 rounded-lg border border-outline/15 bg-surface-container-low/50 p-4">
                    <img src="{{ route('sondages.qr', $sondage) }}"
                         alt="QR code de vote du sondage"
                         class="h-40 w-40 rounded-md border border-outline/15 bg-white p-1.5">
                    <a href="{{ route('sondage.show', $sondage->token) }}" target="_blank" rel="noopener"
                       class="break-all text-center text-xs font-medium text-primary underline underline-offset-2">
                        Page de vote
                    </a>
                    @if($peutGerer)
                        <a href="{{ route('sondages.qr', $sondage) }}" download
                           class="inline-flex min-h-9 items-center gap-2 rounded-md border border-outline/30 px-2.5 py-1 text-xs font-semibold text-on-surface hover:bg-white">
                            <i class="fa-solid fa-download" aria-hidden="true"></i> Télécharger le QR
                        </a>
                    @endif
                </div>

                <div class="min-w-0">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                        Résultats · {{ $sondage->totalVotes() }} vote(s)
                    </p>
                    <ul class="space-y-2">
                        @foreach($sondage->resultats() as $candidat)
                            @php
                                $total = max(1, $sondage->totalVotes());
                                $pourcentage = (int) round(((int) $candidat->votes_count / $total) * 100);
                            @endphp
                            <li>
                                <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                                    <span class="truncate font-medium text-on-surface">{{ $candidat->nom }}</span>
                                    <span class="font-mono text-xs font-bold text-primary">{{ (int) $candidat->votes_count }} · {{ $pourcentage }} %</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-surface-container-high">
                                    <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ $pourcentage }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @if($peutGerer)
                        <div class="mt-4 flex flex-wrap gap-2 border-t border-outline/10 pt-4">
                            @if($sondage->statut === \App\Enums\StatutSondageEnum::Brouillon)
                                <form method="POST" action="{{ route('sondages.ouvrir', $sondage) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-xs font-bold text-white hover:bg-green-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-green-600">
                                        <i class="fa-solid fa-play text-xs" aria-hidden="true"></i> Ouvrir le vote
                                    </button>
                                </form>
                            @elseif($sondage->statut === \App\Enums\StatutSondageEnum::Ouvert)
                                <form method="POST" action="{{ route('sondages.clore', $sondage) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-outline/30 px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                                        <i class="fa-solid fa-stop text-xs" aria-hidden="true"></i> Clore le scrutin
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('sondages.show', $sondage) }}"
                               class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-outline/30 px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container">
                                <i class="fa-solid fa-sliders text-xs" aria-hidden="true"></i> Gérer le sondage
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endif
