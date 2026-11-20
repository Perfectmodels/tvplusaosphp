<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.favicon')
    <title>Invitation · TV+ OS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gradient-to-br from-[#001a41] via-[#00306e] to-[#0054cd] text-white antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-xl flex-col justify-center px-4 py-8 sm:px-6">

        <div class="mb-6 text-center">
            <div class="text-xs font-bold uppercase tracking-[0.35em] text-white/60">TV+A OS · Invitation</div>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Votre invitation</h1>
        </div>

        @if(! $intervenant)
            <section class="rounded-3xl border border-white/15 bg-white/10 p-8 text-center shadow-2xl backdrop-blur">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-500/20 text-2xl">!</div>
                <h2 class="mt-5 text-xl font-bold">Lien invalide</h2>
                <p class="mt-2 text-sm text-white/75">
                    Ce lien d'invitation n'existe pas ou a été régénéré.
                    Contactez la production pour obtenir un nouveau QR.
                </p>
            </section>
        @else
            <section class="rounded-3xl border border-white/15 bg-white/10 p-6 text-center shadow-2xl backdrop-blur sm:p-8">
                <img src="{{ $intervenant->photoUrl }}"
                     alt="Photo de {{ $intervenant->nom_complet }}"
                     referrerpolicy="no-referrer"
                     class="mx-auto h-28 w-28 rounded-full bg-white/20 object-cover ring-4 ring-white/25">

                <h2 class="mt-4 text-2xl font-bold">{{ $intervenant->nom_complet }}</h2>

                @if($intervenant->fonction || $intervenant->organisme)
                    <p class="mt-1 text-sm text-white/75">
                        {{ $intervenant->fonction }}{{ $intervenant->fonction && $intervenant->organisme ? ' · ' : '' }}{{ $intervenant->organisme }}
                    </p>
                @endif

                <div class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-wide">
                    {{ $intervenant->code_intervenant }}
                </div>
            </section>

            <div class="mt-5 space-y-4">
                <h3 class="px-1 text-xs font-bold uppercase tracking-[0.2em] text-white/60">Vos invitations</h3>

                @forelse($invitations as $invitation)
                    @php
                        $emission = $invitation->emission?->nom
                            ?? $invitation->sequence?->emission?->nom
                            ?? $invitation->conducteur?->emission?->nom
                            ?? 'Émission';
                        $qrExpire = $invitation->heure_passage_prevue
                            && $invitation->heure_passage_prevue->copy()->addHours(24)->isPast();
                    @endphp

                    <article class="rounded-3xl border border-white/15 bg-white p-5 text-[#001a41] shadow-xl">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#0054cd]">Invitation</p>
                                <h4 class="mt-1 text-lg font-extrabold leading-tight">{{ $emission }}</h4>
                            </div>
                            @if(! $invitation->heure_passage_prevue)
                                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                                    L’horaire de passage n’est pas encore programmé. Contactez la production pour obtenir votre QR d’accès.
                                </p>
                            @elseif($qrExpire)
                                <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">Délai dépassé</span>
                            @elseif($invitation->arrive_accueil_at)
                                <span class="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">Arrivée enregistrée</span>
                            @elseif($invitation->confirme_at)
                                <span class="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">Participation confirmée</span>
                            @else
                                <span class="shrink-0 rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">En attente</span>
                            @endif
                        </div>

                        @if($invitation->heure_passage_prevue)
                            <p class="mt-3 text-sm font-semibold text-slate-600">
                                Passage prévu le
                                <span class="font-bold text-[#001a41]">{{ $invitation->heure_passage_prevue->format('d/m/Y') }}</span>
                                à
                                <span class="font-bold text-[#001a41]">{{ $invitation->heure_passage_prevue->format('H:i') }}</span>
                            </p>
                        @endif

                        @if($invitation->sequence?->title)
                            <p class="mt-1 text-xs text-slate-500">Sujet : {{ $invitation->sequence->title }}</p>
                        @endif

                        @if($qrExpire)
                            <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                                Ce QR d’accès a expiré 24 heures après l’horaire prévu.
                                Contactez la production pour obtenir un nouveau QR.
                            </p>
                        @elseif($invitation->heure_passage_prevue)
                            <p class="mt-4 rounded-xl bg-blue-50 p-3 text-xs text-blue-800">
                                Présentez ce QR à l’accueil. Un membre de la production vérifiera votre invitation avant d’enregistrer votre arrivée.
                            </p>
                            <div class="mt-6 flex flex-col items-center gap-4 rounded-2xl bg-slate-50 p-5 sm:p-6">
                                <img src="{{ $invitation->urlQr() }}"
                                     alt="QR d’accès à {{ $emission }}"
                                     class="h-48 w-48 rounded-lg bg-white p-2"
                                     loading="lazy">
                                <a href="{{ $invitation->urlQr() }}"
                                   download="qr-acces-{{ $invitation->id }}.png"
                                   class="inline-flex min-h-11 items-center justify-center rounded-full bg-[#0054cd] px-5 py-2 text-sm font-semibold text-white hover:bg-[#003f9e]">
                                    Télécharger le QR d’accès
                                </a>
                                <div class="w-full rounded-xl border border-slate-300 bg-white p-5 text-center">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#0054cd]">Votre code d’accès personnel</p>
                                    <p class="mt-2 text-xs leading-5 text-slate-500">Ces 6 chiffres servent à valider votre arrivée si le QR ne peut pas être scanné.</p>
                                    <input type="text" readonly value="{{ $invitation->code_manuel_formate }}" aria-label="Code d’accès personnel à 6 chiffres pour {{ $emission }}" class="mt-3 w-full min-w-0 rounded-lg border border-slate-300 bg-white p-3 text-center font-mono text-2xl font-bold tracking-[0.15em] text-[#001a41] select-all" onclick="this.select()">
                                    <span class="mt-2 block text-xs font-medium leading-5 text-slate-500">Si le QR ne peut pas être scanné, dictez ces 6 chiffres à l’accueil.</span>
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="rounded-3xl border border-white/15 bg-white/10 p-6 text-center text-sm text-white/80">
                        <p class="font-semibold">Aucune invitation en attente.</p>
                        <p class="mt-1 text-white/60">
                            Si vous venez de recevoir un message, la production doit d'abord programmer votre passage.
                        </p>
                    </div>
                @endforelse
            </div>

        @endif

        <p class="mt-8 text-center text-xs leading-relaxed text-white/45">
            Ce lien est personnel et sécurisé. Ne le partagez pas.<br>
            TV+A OS · {{ date('Y') }}
        </p>
    </main>
</body>
</html>
