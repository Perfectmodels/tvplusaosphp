@extends('layouts.app')
@php
    $pageTitle = 'Pointage QR';
    $pageSubtitle = 'Contrôle d’accès des invités';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Suivi des invités', 'icon' => 'fa-solid fa-chart-line', 'href' => route('suivi-intervenants.index'), 'variant' => 'ghost'],
        ['label' => 'Intervenants', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('intervenants.index'), 'variant' => 'ghost'],
    ];
@endphp

@section('content')
<main
    data-guest-qr-reader
    data-lookup-url="{{ route('scanner-qr.lookup') }}"
    data-tracking-url-template="{{ route('scanner-qr.tracking', ['parcours' => '__PARCOURS__']) }}"
    data-confirm-url="{{ route('scanner-qr.confirm') }}"
    data-cancel-url="{{ route('scanner-qr.cancel') }}"
    data-operator-name="{{ auth()->user()?->name }}"
    data-intervenant-id="{{ $intervenantSelectionne?->id }}"
    class="mx-auto max-w-[1500px] space-y-6"
>
    <header class="flex flex-col gap-3 rounded-2xl bg-[#001a41] p-4 text-white shadow-xl sm:flex-row sm:items-end sm:justify-between sm:gap-5 sm:p-7">
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight sm:text-3xl">Pointage des invités</h1>
            <p class="mt-1 max-w-2xl text-xs leading-5 text-blue-100 sm:mt-2 sm:text-sm sm:leading-6">
                Scannez ou photographiez le QR, vérifiez l’invitation, puis validez l’entrée.
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs text-blue-100 sm:gap-3 sm:text-sm">
            <span id="camera-indicator" class="h-2.5 w-2.5 rounded-full bg-slate-400" aria-hidden="true"></span>
            <span id="camera-status" role="status" aria-live="polite">Caméra inactive</span>
        </div>
    </header>

    @if($intervenantSelectionne)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface p-4">
            <p class="min-w-0 text-sm"><strong>{{ $intervenantSelectionne->nom_complet }}</strong> · {{ $intervenantSelectionne->code_intervenant }}</p>
            <a href="{{ route('intervenants.show', $intervenantSelectionne) }}" class="min-h-11 text-sm font-semibold text-primary underline">Retour à la fiche de l’intervenant</a>
        </div>
    @endif

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(330px,0.8fr)]">
        <div class="space-y-5">
            <div class="rounded-2xl border border-outline-variant/50 bg-surface p-5 shadow-sm sm:p-6">
                <label for="emission-select" class="block text-sm font-semibold text-on-surface">Émission ou événement</label>
                <select id="emission-select" class="mt-2 min-h-12 w-full rounded-xl border-outline-variant bg-surface text-on-surface focus:border-primary focus:ring-primary" @disabled($emissions->isEmpty())>
                    <option value="">Sélectionner une émission</option>
                    @foreach($emissions as $emission)
                        <option value="{{ $emission->id }}">{{ $emission->nom }}</option>
                    @endforeach
                </select>
                @if($emissions->isEmpty())
                    <p class="mt-2 text-sm text-error">Aucune émission active n’est disponible pour le pointage.</p>
                @endif
            </div>

            <section class="overflow-hidden rounded-2xl border border-outline-variant/50 bg-[#07182b] text-white shadow-lg" aria-label="Scanner QR">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
                    <div>
                        <h2 class="font-semibold">Caméra</h2>
                        <p class="mt-1 text-xs text-blue-100/70">Démarrez le scan et autorisez la caméra, puis présentez le QR code. Vous pouvez aussi lire une photo.</p>
                    </div>
                    <button id="camera-switch" type="button" class="min-h-11 rounded-lg border border-white/20 px-3 text-sm font-medium text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-blue-300" aria-label="Changer de caméra" disabled>
                        <i class="fa-solid fa-camera-rotate mr-2" aria-hidden="true"></i>Changer de caméra
                    </button>
                </div>

                <div class="relative min-h-64 bg-black">
                    <video id="qr-video" class="block h-64 w-full bg-black object-cover sm:h-80" playsinline muted autoplay aria-label="Aperçu de la caméra"></video>
                    <div id="camera-placeholder" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-[#0b1d32] px-6 text-center">
                        <i class="fa-solid fa-qrcode text-5xl text-blue-200/80" aria-hidden="true"></i>
                        <p class="max-w-sm text-sm leading-6 text-blue-100/80">Sélectionnez une émission, puis démarrez le scan ou lisez une photo du QR code.</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 p-5">
                    <button id="camera-start" type="button" class="min-h-12 flex-1 rounded-xl bg-emerald-400 px-5 font-bold text-slate-950 transition hover:bg-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-50" @disabled($emissions->isEmpty())>
                        <i class="fa-solid fa-camera mr-2" aria-hidden="true"></i>Démarrer le scan
                    </button>
                    <button id="camera-stop" type="button" class="hidden min-h-12 rounded-xl border border-white/25 px-5 font-semibold text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/50">
                        Arrêter
                    </button>
                    <button id="photo-button" type="button" class="min-h-12 rounded-xl border border-white/25 px-5 font-semibold text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/50 disabled:cursor-not-allowed disabled:opacity-50" @disabled($emissions->isEmpty())>
                        <i class="fa-solid fa-image mr-2" aria-hidden="true"></i>Choisir dans la galerie
                    </button>
                    <input id="photo-input" type="file" accept="image/*" class="hidden" aria-label="Choisir une image du QR code dans la galerie">
                </div>
            </section>

            <section class="rounded-2xl border border-outline-variant/50 bg-surface p-5 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-on-surface">Saisie manuelle</h2>
                <p class="mt-2 text-sm leading-6 text-on-surface-variant">Saisissez le numéro INT inscrit sur la fiche, le code à 6 chiffres affiché sous le QR, ou collez le lien d’invitation.</p>
                <form id="manual-form" class="mt-4 flex flex-col gap-3 sm:flex-row items-stretch">
                    @csrf
                    <label class="sr-only" for="manual-code">Numéro INT, code à 6 chiffres ou lien d’invitation</label>
                    <input id="manual-code" type="text" autocomplete="off" maxlength="2048" value="{{ $intervenantSelectionne?->code_intervenant }}" placeholder="Numéro INT, code à 6 chiffres ou lien" class="min-h-12 min-w-0 flex-1 rounded-xl border-outline-variant bg-surface text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:ring-primary text-lg font-semibold">
                    <button type="submit" class="min-h-12 rounded-xl bg-primary px-5 font-semibold text-on-primary hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50">
                        Vérifier l’invitation
                    </button>
                </form>
                <p class="mt-2 text-xs leading-5 text-on-surface-variant">Le code à 6 chiffres peut être saisi avec ou sans espace.</p>
            </section>

            <div id="scan-message" class="hidden rounded-xl border p-4 text-sm" role="status" aria-live="polite"></div>
            <div id="resume-container" class="hidden">
                <button id="scan-resume" type="button" class="min-h-12 rounded-xl border border-primary px-5 font-semibold text-primary hover:bg-primary/5 focus:outline-none focus:ring-2 focus:ring-primary/30">
                    Reprendre le scan
                </button>
            </div>
        </div>

        <aside class="flex flex-col rounded-2xl border border-outline-variant/50 bg-surface shadow-sm">
            <div class="border-b border-outline-variant/50 p-5">
                <h2 class="text-lg font-semibold text-on-surface">Pointages récents</h2>
                <p class="mt-1 text-sm text-on-surface-variant">Les heures et résultats proviennent des pointages enregistrés.</p>
                <dl class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-surface-container-low p-3">
                        <dt class="text-xs font-medium text-on-surface-variant">Scans aujourd’hui</dt>
                        <dd id="today-scan-count" class="mt-1 text-2xl font-bold tabular-nums text-on-surface">{{ $scansAujourdhui }}</dd>
                    </div>
                    <div class="rounded-xl bg-surface-container-low p-3">
                        <dt class="text-xs font-medium text-on-surface-variant">Entrées validées</dt>
                        <dd id="today-accepted-count" class="mt-1 text-2xl font-bold tabular-nums text-on-surface">{{ $entreesConfirmees }}</dd>
                    </div>
                </dl>
            </div>

            <ol id="recent-checkins" class="flex-1 divide-y divide-outline-variant/50">
                @forelse($tentativesRecentes as $tentative)
                    @php
                        $statusLabels = [
                            'accepted' => 'Entrée validée',
                            'already_recorded' => 'Déjà enregistré',
                            'invalid' => 'QR invalide',
                            'expired' => 'QR expiré',
                            'cancelled' => 'Annulé',
                            'pending' => 'En attente',
                        ];
                        $statusClasses = [
                            'accepted' => 'bg-emerald-100 text-emerald-800',
                            'already_recorded' => 'bg-amber-100 text-amber-900',
                            'invalid' => 'bg-red-100 text-red-800',
                            'expired' => 'bg-red-100 text-red-800',
                            'cancelled' => 'bg-slate-100 text-slate-700',
                            'pending' => 'bg-blue-100 text-blue-800',
                        ];
                    @endphp
                    <li class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if($tentative->intervenant && auth()->user()->can('view', $tentative->intervenant))
                                    <a href="{{ route('intervenants.show', $tentative->intervenant) }}" class="block truncate font-semibold text-primary underline">{{ $tentative->intervenant->nom_complet }}</a>
                                @else
                                    <p class="truncate font-semibold text-on-surface">{{ $tentative->intervenant?->nom_complet ?? 'QR non identifié' }}</p>
                                @endif
                                <p class="mt-1 truncate text-sm text-on-surface-variant">{{ $tentative->emission?->nom ?? 'Émission indisponible' }}</p>
                            </div>
                            <time class="shrink-0 text-xs tabular-nums text-on-surface-variant" datetime="{{ $tentative->scanned_at?->toIso8601String() }}">
                                {{ $tentative->scanned_at?->format('H:i:s') ?? '—' }}
                            </time>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$tentative->result] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ $statusLabels[$tentative->result] ?? $tentative->result }}
                            </span>
                            <span class="text-xs text-on-surface-variant">{{ $tentative->scannerBy?->name ?? 'Agent inconnu' }}</span>
                        </div>
                    </li>
                @empty
                    <li data-empty-state class="px-5 py-12 text-center">
                        <i class="fa-solid fa-inbox text-3xl text-on-surface-variant/60" aria-hidden="true"></i>
                        <p class="mt-3 font-semibold text-on-surface">Aucun pointage récent</p>
                        <p class="mt-1 text-sm text-on-surface-variant">Les scans apparaîtront ici après leur vérification.</p>
                    </li>
                @endforelse
            </ol>
        </aside>
    </section>
</main>

<dialog id="checkin-dialog" class="w-[min(94vw,34rem)] rounded-2xl border-0 bg-surface p-0 text-on-surface shadow-2xl backdrop:bg-black/70">
    <form method="dialog" class="p-6 sm:p-7">
        <h2 class="text-xl font-bold">Confirmer l’entrée</h2>
        <p class="mt-2 text-sm text-on-surface-variant">Vérifiez l’invitation avant d’enregistrer le passage.</p>
        <dl class="mt-5 space-y-3 rounded-xl bg-surface-container-low p-4 text-sm">
            <div><dt class="text-on-surface-variant">Invité</dt><dd id="dialog-guest" class="mt-0.5 font-semibold"></dd></div>
            <div><dt class="text-on-surface-variant">Émission</dt><dd id="dialog-emission" class="mt-0.5 font-semibold"></dd></div>
            <div><dt class="text-on-surface-variant">Passage prévu</dt><dd id="dialog-time" class="mt-0.5 font-semibold"></dd></div>
            <div><dt class="text-on-surface-variant">QR détecté à</dt><dd id="dialog-scan-time" class="mt-0.5 font-semibold tabular-nums"></dd></div>
            <div><dt class="text-on-surface-variant">Invitation</dt><dd id="dialog-status" class="mt-0.5 font-semibold"></dd></div>
        </dl>
        <a id="dialog-profile-link" hidden target="_blank" rel="noopener" class="hidden mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-primary underline">
            <i class="fa-solid fa-address-card" aria-hidden="true"></i>Ouvrir la fiche de l’intervenant
        </a>
        <section class="mt-5" aria-labelledby="dialog-journey-heading">
            <div class="flex items-center justify-between gap-3">
                <h3 id="dialog-journey-heading" class="text-sm font-bold">Parcours de l’invité</h3>
                <span id="dialog-current-stage" class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">Chargement…</span>
            </div>
            <ol id="dialog-timeline" class="mt-3 max-h-56 divide-y divide-outline-variant/50 overflow-y-auto rounded-xl border border-outline-variant/50" aria-live="polite">
                <li class="p-3 text-sm text-on-surface-variant">Chargement des étapes…</li>
            </ol>
            <p id="dialog-feedback" class="mt-3 text-sm text-on-surface-variant" role="status" aria-live="polite"></p>
        </section>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button id="checkin-cancel" type="button" class="min-h-11 rounded-xl border border-outline-variant px-4 font-semibold hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-primary/30">Annuler</button>
            <button id="checkin-confirm" type="button" class="min-h-11 rounded-xl bg-primary px-4 font-semibold text-on-primary hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-primary/40">Valider l’entrée</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
    @vite('resources/js/scanner-qr.js')
@endpush
