@extends('layouts.app')
@php
    $pageTitle = 'Sondage en direct';
    $pageSubtitle = $sondage->emission?->nom ?? 'Émission';
@endphp
@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        @if(session('success'))
            <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">{{ session('success') }}</div>
        @endif

        <div class="card-surface p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Question du sondage</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">{{ $sondage->question }}</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Statut : {!! $sondage->statut->badge() !!}
                        @if($sondage->ouvert_at) · Ouvert le {{ $sondage->ouvert_at->format('d/m/Y H:i') }}@endif
                        @if($sondage->clos_at) · Clos le {{ $sondage->clos_at->format('d/m/Y H:i') }}@endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($sondage->statut === \App\Enums\StatutSondageEnum::Brouillon)
                        <form method="POST" action="{{ route('sondages.ouvrir', $sondage) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:opacity-90">Ouvrir le vote</button>
                        </form>
                    @elseif($sondage->statut === \App\Enums\StatutSondageEnum::Ouvert)
                        <form method="POST" action="{{ route('sondages.clore', $sondage) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-bold text-white hover:opacity-90">Clore le scrutin</button>
                        </form>
                    @endif
                    <a href="{{ route('emissions.show', $sondage->emission) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour à l'émission</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-[220px_1fr]">
            <div class="card-surface p-5 text-center">
                <h3 class="mb-3 text-sm font-bold text-slate-800">QR code du vote</h3>
                <img src="{{ route('sondages.qr', $sondage) }}" alt="QR code du sondage" class="mx-auto w-full max-w-[200px]">
                <p class="mt-3 break-all text-xs text-slate-500">{{ $urlVote }}</p>
                <a href="{{ route('sondages.qr', $sondage) }}" download class="mt-3 inline-block rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Télécharger le QR</a>
            </div>

            <div class="card-surface p-5">
                <div class="mb-4 flex items-baseline justify-between gap-3">
                    <h3 class="text-sm font-bold text-slate-800">Résultats</h3>
                    <p class="text-xs text-slate-500">{{ $sondage->totalVotes() }} vote(s)</p>
                </div>
                <ul class="space-y-3">
                    @foreach($resultats as $candidat)
                        @php
                            $total = max(1, $sondage->totalVotes());
                            $pourcentage = (int) round(((int) $candidat->votes_count / $total) * 100);
                        @endphp
                        <li>
                            <div class="mb-1 flex items-baseline justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-800">{{ $candidat->nom }}</span>
                                <span class="text-xs font-bold text-primary">{{ (int) $candidat->votes_count }} · {{ $pourcentage }} %</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-primary" style="width: {{ $pourcentage }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
