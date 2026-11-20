<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @include('partials.favicon')
    <title>Sondage · {{ $sondage->emission?->nom ?? 'TV+A OS' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-xl flex-col justify-center px-4 py-8 sm:px-6">
        <header class="mb-6 text-center">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary">TV+A OS · Sondage en direct</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $sondage->question }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $sondage->emission?->nom }}</p>
        </header>

        @if(session('success'))
            <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-medium text-amber-900">
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($sondage->estOuvert() && ! $aVote)
            <form action="{{ route('sondage.vote', $sondage->token) }}" method="POST"
                  class="mb-8 space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                @csrf
                <fieldset>
                    <legend class="mb-3 text-sm font-semibold text-slate-800">Qui avez-vous trouvé le plus convaincant ?</legend>
                    @foreach($candidats as $candidat)
                        <label for="candidat-{{ $candidat->id }}"
                               class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-primary hover:bg-slate-50">
                            <input type="radio" id="candidat-{{ $candidat->id }}" name="candidat_id"
                                   value="{{ $candidat->id }}" required
                                   class="h-4 w-4 accent-primary"
                                   @checked(old('candidat_id') == $candidat->id)>
                            <span class="text-sm font-semibold text-slate-900">{{ $candidat->nom }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <button type="submit"
                        class="mt-4 w-full rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    Envoyer mon vote
                </button>
                <p class="text-center text-xs text-slate-500">
                    Un seul vote par appareil. Votre choix est anonyme.
                </p>
            </form>
        @elseif($sondage->estOuvert() && $aVote)
            <div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center text-sm font-semibold text-emerald-900">
                Votre vote a été enregistré. Résultats en direct ci-dessous.
            </div>
        @else
            <div role="status" class="mb-6 rounded-xl border border-slate-200 bg-white p-4 text-center text-sm font-semibold text-slate-700">
                Le scrutin est clos. Résultats finaux :
            </div>
        @endif

        <section aria-labelledby="resultats-titre" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="mb-4 flex items-baseline justify-between gap-3">
                <h2 id="resultats-titre" class="text-sm font-bold uppercase tracking-wider text-slate-700">Résultats en direct</h2>
                <p class="text-xs text-slate-500"><span id="total-votes">{{ $sondage->totalVotes() }}</span> vote(s)</p>
            </div>
            <div id="resultats" class="space-y-3" aria-live="polite"></div>
        </section>
    </main>

    <script>
        (function () {
            const conteneur = document.getElementById('resultats');
            const total = document.getElementById('total-votes');
            const donneesInitiales = @json($candidats->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom, 'votes' => (int) $c->votes_count])->values());
            const urlResultats = @json(route('sondage.resultats', $sondage->token));

            function rendre(candidats, nombreTotal) {
                conteneur.textContent = '';
                total.textContent = String(nombreTotal);
                candidats.forEach(function (candidat) {
                    const pourcentage = nombreTotal > 0 ? Math.round((candidat.votes / nombreTotal) * 100) : 0;
                    const ligne = document.createElement('div');

                    const libelle = document.createElement('div');
                    libelle.className = 'mb-1 flex items-baseline justify-between gap-2';
                    const nom = document.createElement('span');
                    nom.className = 'text-sm font-semibold text-slate-800';
                    nom.textContent = candidat.nom;
                    const valeur = document.createElement('span');
                    valeur.className = 'text-xs font-bold text-primary';
                    valeur.textContent = candidat.votes + ' · ' + pourcentage + ' %';
                    libelle.append(nom, valeur);

                    const piste = document.createElement('div');
                    piste.className = 'h-2 w-full overflow-hidden rounded-full bg-slate-100';
                    const barre = document.createElement('div');
                    barre.className = 'h-full rounded-full bg-primary transition-all duration-500';
                    barre.style.width = pourcentage + '%';
                    piste.appendChild(barre);

                    ligne.append(libelle, piste);
                    conteneur.appendChild(ligne);
                });
            }

            rendre(donneesInitiales, {{ (int) $sondage->totalVotes() }});

            async function actualiser() {
                try {
                    const reponse = await fetch(urlResultats, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                    if (!reponse.ok) {
                        return;
                    }
                    const donnees = await reponse.json();
                    rendre(donnees.candidats, donnees.total);
                } catch (e) {
                    // Le sondage reste lisible avec les dernières données connues.
                }
            }

            setInterval(actualiser, 5000);
        })();
    </script>
</body>
</html>
