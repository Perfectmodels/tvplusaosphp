@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center py-10 px-4">
    <div class="w-full max-w-2xl">
        <div class="bg-white rounded-2xl shadow-sm border border-outline/10 p-6 sm:p-8">
            <div class="mb-6">
                <div class="flex items-center gap-3 text-xs text-on-surface-variant uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-user-plus text-primary"></i>
                    <span>Inscription personnel TV+ Afrique</span>
                </div>
                <h1 class="text-2xl font-bold text-on-surface">Demander un compte</h1>
                <p class="text-sm text-on-surface-variant mt-2">
                    Remplissez ce formulaire pour demander la création de votre compte. Un administrateur examinera votre demande et vous enverra vos identifiants par e-mail.
                </p>
            </div>

            {{-- Guide --}}
            <div class="mb-6 rounded-xl border border-primary/20 bg-primary/5 p-4">
                <h2 class="text-sm font-semibold text-primary mb-2">
                    <i class="fa-solid fa-circle-info mr-1.5"></i> Guide d'utilisation
                </h2>
                <ol class="text-xs text-on-surface-variant space-y-1.5 list-decimal list-inside">
                    <li>Remplissez tous les champs obligatoires (marqués d'un <span class="text-error">*</span>).</li>
                    <li>Utilisez une <strong>adresse e-mail professionnelle</strong> — vos identifiants y seront envoyés.</li>
                    <li>Indiquez votre <strong>pôle / service</strong> et votre <strong>fonction</strong> pour faciliter la validation.</li>
                    <li>Expliquez brièvement votre <strong>motivation</strong> (optionnel mais recommandé).</li>
                    <li>Cliquez sur « Envoyer ma demande ». Vous recevrez une confirmation.</li>
                    <li>Un administrateur validera votre profil et créera votre compte.</li>
                    <li>Vous recevrez un <strong>e-mail</strong> avec un mot de passe temporaire.</li>
                    <li>À la première connexion, vous devrez <strong>changer ce mot de passe</strong> pour un mot de passe personnel sécurisé.</li>
                </ol>
                <p class="text-xs text-on-surface-variant mt-3 italic">
                    <i class="fa-solid fa-shield-halved mr-1"></i>
                    Règles de sécurité du mot de passe : au moins 8 caractères, une majuscule, une minuscule, un chiffre et un symbole.
                </p>
            </div>

            {{-- Recherche de profil existant --}}
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h2 class="text-sm font-semibold text-amber-800 mb-2">
                    <i class="fa-solid fa-magnifying-glass mr-1.5"></i> Vous avez déjà un profil ?
                </h2>
                <p class="text-xs text-amber-700 mb-3">
                    Si vous avez déjà un profil dans le système (même avec une ancienne adresse e-mail), recherchez-le ici pour pré-remplir le formulaire et éviter les doublons.
                    <span class="block mt-1">Pour protéger les données personnelles, seule l’identité est affichée : saisissez vos coordonnées vous-même.</span>
                </p>
                <div class="relative">
                    <label for="profile-search" class="sr-only">Rechercher un profil existant</label>
                    <input type="search" id="profile-search" autocomplete="off"
                           placeholder="Tapez votre nom ou prénom…"
                           class="w-full px-3.5 py-2.5 rounded-lg border border-amber-300 bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 transition-all text-on-surface">
                    <div id="profile-results" class="hidden absolute z-30 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border border-amber-300 bg-white shadow-lg"></div>
                </div>
                <input type="hidden" name="journaliste_id" id="journaliste_id" value="{{ old('journaliste_id') }}">
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-error/30 bg-error/5 p-3 text-sm text-error">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('personnel.inscription.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Civilité
                        </label>
                        <select name="civilite" class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                            <option value="">—</option>
                            <option value="Mr" @selected(old('civilite') === 'Mr')>M.</option>
                            <option value="Mme" @selected(old('civilite') === 'Mme')>Mme</option>
                            <option value="Mlle" @selected(old('civilite') === 'Mlle')>Mlle</option>
                            <option value="Dr" @selected(old('civilite') === 'Dr')>Dr</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Prénom <span class="text-error">*</span>
                        </label>
                        <input type="text" name="prenom" required maxlength="100" value="{{ old('prenom') }}"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface"
                               placeholder="Ex: Jean">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                        Nom <span class="text-error">*</span>
                    </label>
                    <input type="text" name="nom" required maxlength="100" value="{{ old('nom') }}"
                           class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface"
                           placeholder="Ex: DUPONT">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                        Adresse e-mail professionnelle <span class="text-error">*</span>
                    </label>
                    <input type="email" name="email" required maxlength="200" value="{{ old('email') }}"
                           class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface"
                           placeholder="Ex: jean.dupont@tvplusafrique.com">
                    <p class="text-xs text-on-surface-variant mt-1">Vos identifiants de connexion seront envoyés à cette adresse.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Téléphone
                        </label>
                        <input type="tel" name="telephone" maxlength="30" value="{{ old('telephone') }}"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface"
                               placeholder="Ex: +241 06 12 34 56">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                            Pôle / Service
                        </label>
                        <input type="text" name="department" maxlength="100" value="{{ old('department') }}"
                               class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface"
                               placeholder="Ex: Rédaction, Technique, Administration">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                        Fonction / Rôle
                    </label>
                    @php
                        $fonctionsInscription = config('workspaces.roles');
                    @endphp
                    <select name="fonction" maxlength="100" class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface">
                        <option value="">—</option>
                        @foreach($fonctionsInscription as $roleKey => $roleLabel)
                            <option value="{{ $roleKey }}" @selected(old('fonction') === $roleKey)>{{ $roleLabel }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-on-surface-variant mt-1">Sélectionnez la fonction visée lors de la création du profil.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                        Motivation (optionnel)
                    </label>
                    <textarea name="motivation" rows="3" maxlength="1000"
                              class="w-full px-3.5 py-2.5 rounded-lg border border-outline/20 bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-on-surface resize-none"
                              placeholder="Expliquez brièvement pourquoi vous souhaitez rejoindre l'équipe TV+ Afrique…">{{ old('motivation') }}</textarea>
                </div>

                <div class="pt-4 border-t border-outline/10 flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('login') }}"
                       class="px-5 py-2.5 text-sm text-on-surface-variant hover:bg-surface-container rounded-lg transition-colors">
                        Retour à la connexion
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-container transition-colors shadow-sm">
                        <i class="fa-solid fa-paper-plane"></i> Envoyer ma demande
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const searchInput = document.getElementById('profile-search');
    const resultsDiv = document.getElementById('profile-results');
    const hiddenId = document.getElementById('journaliste_id');
    if (!searchInput || !resultsDiv) return;

    let debounceTimer = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            resultsDiv.classList.add('hidden');
            resultsDiv.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(async function () {
            try {
                const r = await fetch('/personnel/inscription/search?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' },
                });
                const j = await r.json();
                const results = j.results || [];
                if (results.length === 0) {
                    resultsDiv.innerHTML = '<div class="px-4 py-3 text-sm text-on-surface-variant italic">Aucun profil trouvé.</div>';
                    resultsDiv.classList.remove('hidden');
                    return;
                }
                resultsDiv.innerHTML = results.map(function (p) {
                    return '<button type="button" data-profile=\'' + JSON.stringify(p).replace(/'/g, "\\'") + '\''
                        + ' class="w-full text-left px-4 py-3 hover:bg-amber-50 transition-colors border-b border-amber-100 last:border-0">'
                        + '<div class="text-sm font-medium text-on-surface">' + escapeHtml(p.label || ((p.prenom || '') + ' ' + (p.nom || ''))) + '</div>'
                        + '<div class="text-xs text-on-surface-variant">Cliquez pour préremplir l’identité</div>'
                        + '</button>';
                }).join('');
                resultsDiv.classList.remove('hidden');

                resultsDiv.querySelectorAll('button[data-profile]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const p = JSON.parse(this.getAttribute('data-profile'));
                        document.querySelector('[name="civilite"]').value = p.civilite || '';
                        document.querySelector('[name="prenom"]').value = p.prenom || '';
                        document.querySelector('[name="nom"]').value = p.nom || '';
                        if (hiddenId) hiddenId.value = p.id;
                        resultsDiv.classList.add('hidden');
                        searchInput.value = p.label || ((p.prenom || '') + ' ' + (p.nom || ''));
                        // Highlight the form
                        document.querySelectorAll('input, select, textarea').forEach(function (el) {
                            el.classList.add('ring-1', 'ring-amber-300');
                            setTimeout(function () { el.classList.remove('ring-1', 'ring-amber-300'); }, 2000);
                        });
                    });
                });
            } catch (e) {
                resultsDiv.innerHTML = '<div class="px-4 py-3 text-sm text-red-600">Erreur de recherche.</div>';
                resultsDiv.classList.remove('hidden');
            }
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.classList.add('hidden');
        }
    });
})();
</script>
@endpush
