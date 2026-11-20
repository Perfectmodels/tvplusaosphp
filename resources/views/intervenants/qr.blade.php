@extends('layouts.app')
@php
    $nomEmission = static fn ($parcours) => $parcours?->emission?->nom
        ?? $parcours?->sequence?->emission?->nom
        ?? $parcours?->conducteur?->emission?->nom
        ?? 'Émission';
    $qrPayload = $intervenant->urlQrAcces();
@endphp
@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">📲 QR d'invitation · {{ $intervenant->nom_complet }}</h1>
            <p class="mt-1 text-sm text-on-surface-variant">Badge scannable par le module Pointage QR de TV+A OS.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('intervenants.show', $intervenant) }}" class="text-sm text-gray-500 hover:underline px-2 py-2 inline-flex items-center min-h-[44px]">← Retour fiche</a>
            <a href="{{ $intervenant->urlInvitation() }}" target="_blank" rel="noopener" class="text-sm px-3 py-1.5 min-h-[44px] inline-flex items-center bg-[#25d366] text-white rounded-lg hover:bg-[#1da851] shadow-sm">🔗 Aperçu public</a>
            <form method="POST" action="{{ route('intervenants.whatsapp', $intervenant) }}" target="_blank" rel="noopener">
                @csrf
                <button type="submit" class="text-sm px-3 py-1.5 min-h-[44px] inline-flex items-center bg-[#25d366] text-white rounded-lg hover:bg-[#1da851] shadow-sm">💬 WhatsApp</button>
            </form>
            <form method="POST" action="{{ route('intervenants.qr.regenerate', $intervenant) }}">
                @csrf
                <button type="submit" class="text-sm px-3 py-1.5 min-h-[44px] inline-flex items-center bg-amber-600 text-white rounded-lg hover:bg-amber-700 shadow-sm">♻️ Régénérer</button>
            </form>
            <a href="{{ route('intervenants.qr.png', $intervenant) }}" class="text-sm px-3 py-1.5 min-h-[44px] inline-flex items-center bg-[#001a41] text-white rounded-lg hover:bg-[#002b6c] shadow-sm">⬇️ PNG</a>
            <a href="{{ route('intervenants.qr.svg', $intervenant) }}" class="text-sm px-3 py-1.5 min-h-[44px] inline-flex items-center bg-[#0054cd] text-white rounded-lg hover:bg-[#0043a3] shadow-sm">⬇️ SVG</a>
        </div>
    </div>

    <div class="mb-5 rounded-xl border px-4 py-3 text-sm {{ $parcoursActifs->isNotEmpty() ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-950' }}">
        @if($parcoursActifs->isNotEmpty())
            <strong>QR connecté au pointage.</strong>
            Le scanner reconnaît ce badge et ouvre le parcours de l’invité pour l’émission sélectionnée.
        @else
            <strong>Aucune invitation active.</strong>
            Le badge est prêt, mais le scan ouvrira un parcours dès qu’un passage sera programmé pour cet invité.
        @endif
    </div>

    <div id="badge" class="bg-gradient-to-br from-[#001a41] to-[#0054cd] text-white rounded-3xl p-8 shadow-2xl relative overflow-hidden print:shadow-none print:rounded-none">
        <div class="absolute -top-12 -right-12 w-48 h-48 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-12 -left-12 w-56 h-56 bg-white/10 rounded-full blur-3xl"></div>
        <div class="relative flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <div class="text-xs tracking-[0.3em] text-white/70 uppercase">TV+A OS · Invité</div>
                <div class="text-xl sm:text-2xl font-bold mt-1">INFORMER · COORDONNER · INSPIRER</div>
            </div>
            <span class="text-xs px-3 py-1 rounded-full bg-white/10 border border-white/20 backdrop-blur">
                {{ optional($intervenant->type)->label() ?? $intervenant->type }}
            </span>
        </div>
        <div class="relative grid grid-cols-1 sm:grid-cols-5 gap-5 items-center">
            <div class="sm:col-span-2">
                <img src="{{ $intervenant->photoUrl }}" class="w-full aspect-square rounded-2xl ring-4 ring-white/30 object-cover bg-white/20" alt="{{ $intervenant->nom_complet }}">
                <div class="mt-4 space-y-0.5 text-sm">
                    <div class="text-xs uppercase text-white/60 tracking-wider">Nom</div>
                    <div class="text-lg font-bold">{{ $intervenant->nom_complet }}</div>
                </div>
                <div class="mt-3 space-y-0.5 text-xs text-white/80">
                    <div>{{ $intervenant->fonction ?? '' }}</div>
                    <div class="font-semibold text-white">{{ $intervenant->organisme ?? '' }}</div>
                    <div>{{ $intervenant->ville_residence ? $intervenant->ville_residence.', ' : '' }}{{ $intervenant->pays_residence ?? '' }}</div>
                </div>
            </div>
            <div class="sm:col-span-3 flex flex-col items-center">
                <div class="bg-white p-5 rounded-2xl shadow-inner">
                    <div class="w-full max-w-[340px] mx-auto [&>svg]:w-full [&>svg]:h-auto">
                    @php
                        try { echo \SimpleSoftwareIO\QrCode\Facades\QrCode::size(340)->errorCorrection('H')->margin(1)->generate($qrPayload); }
                        catch (\Throwable $e) { echo '<div class="w-full aspect-square max-w-[340px] flex items-center justify-center text-xs text-gray-500 bg-gray-100 rounded-lg">QR indisponible</div>'; }
                    @endphp
                    </div>
                </div>
                <div class="mt-3 w-full text-center font-mono text-sm tracking-widest bg-white/10 py-1.5 rounded-lg border border-white/20">
                    {{ $intervenant->code_intervenant }}
                </div>
                <div class="mt-2 inline-flex items-center gap-1.5 text-xs text-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                    Badge scannable · {{ $parcoursActifs->count() }} invitation{{ $parcoursActifs->count() > 1 ? 's' : '' }} active{{ $parcoursActifs->count() > 1 ? 's' : '' }}
                </div>
                @if($prochainParcours)
                    <div class="mt-3 text-center text-xs text-white/80">
                        Prochain passage : <span class="font-semibold text-white">{{ $nomEmission($prochainParcours) }}</span>
                        @if($prochainParcours->heure_passage_prevue)
                            · {{ $prochainParcours->heure_passage_prevue->translatedFormat('d/m/Y · H\hi') }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($parcoursActifs->isNotEmpty())
    <div class="mt-6 bg-surface rounded-xl shadow-sm border border-outline-variant p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <h2 class="font-headline-sm text-headline-sm text-primary-container">Invitations actives</h2>
                <p class="mt-1 text-sm text-on-surface-variant">Chaque invitation garde son propre QR d’accès. Le badge ci-dessus reste valable pour toutes.</p>
            </div>
            <a href="{{ route('parcours-intervenants.index', ['q' => $intervenant->nom_complet]) }}" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-outline-variant px-3 text-sm font-medium hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-[18px]">route</span>
                Voir les parcours
            </a>
        </div>
        <div class="divide-y divide-outline-variant/60">
            @foreach($parcoursActifs as $parcours)
                <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                    <div class="min-w-0">
                        <div class="font-semibold text-on-surface">{{ $nomEmission($parcours) }}</div>
                        <div class="mt-0.5 text-xs text-on-surface-variant">
                            @if($parcours->heure_passage_prevue)
                                {{ $parcours->heure_passage_prevue->translatedFormat('l j F Y · H\hi') }}
                            @else
                                Horaire à confirmer
                            @endif
                            · Accès {{ $parcours->qr_token ? 'prêt' : 'indisponible' }}
                        </div>
                        <div class="mt-2">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Code manuel</p>
                            <p class="mt-1 font-mono text-lg font-extrabold tracking-[0.15em] text-on-surface">{{ $parcours->code_manuel_formate }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('intervenants.qr.png', ['intervenant' => $intervenant, 'parcours' => $parcours->id]) }}" class="inline-flex min-h-[40px] items-center rounded-lg border border-outline-variant px-3 text-xs font-semibold hover:bg-surface-container-high">PNG</a>
                        <a href="{{ route('intervenants.qr.svg', ['intervenant' => $intervenant, 'parcours' => $parcours->id]) }}" class="inline-flex min-h-[40px] items-center rounded-lg border border-outline-variant px-3 text-xs font-semibold hover:bg-surface-container-high">SVG</a>
                        <form method="POST" action="{{ route('intervenants.whatsapp', ['intervenant' => $intervenant, 'parcours' => $parcours->id]) }}" target="_blank" rel="noopener">
                            @csrf
                            <button type="submit" class="inline-flex min-h-[40px] items-center rounded-lg bg-[#25d366] px-3 text-xs font-semibold text-white hover:bg-[#1da851]">WhatsApp</button>
                        </form>
                        <form method="POST" action="{{ route('parcours-intervenants.qr.regenerate', $parcours) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-[40px] items-center rounded-lg bg-amber-600 px-3 text-xs font-semibold text-white hover:bg-amber-700">Régénérer l’accès</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="text-center mt-5">
        <button type="button" onclick="window.print()" class="px-5 py-2 bg-white border border-gray-300 rounded-lg shadow-sm text-sm hover:bg-gray-50">🖨️ Imprimer le badge</button>
    </div>
</div>
@endsection
