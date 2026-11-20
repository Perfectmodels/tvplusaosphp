@extends('layouts.guest')
@section('title', 'Demande de création de compte')
@section('content')
<div class="min-h-screen bg-gradient-to-b from-surface-container-low to-white py-10 px-4">
    <div class="mx-auto max-w-3xl">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-white shadow-lg">
                <i class="fa-solid fa-user-plus text-xl"></i>
            </div>
            <h1 class="text-2xl font-black text-on-surface">Demander la création d’un compte</h1>
            <p class="mt-2 text-sm text-on-surface-variant">Renseignez vos informations. Votre demande sera examinée par l’administration ; le rôle et les accès seront définis lors de la validation.</p>
        </div>

        @if($submitted)
            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-5 text-green-900">
                <p class="font-bold"><i class="fa-solid fa-circle-check mr-2"></i>Demande envoyée</p>
                <p class="mt-1 text-sm">Votre demande a bien été transmise. Vous serez informé après validation par l’administration. Aucun compte n’est créé à ce stade.</p>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 rounded-xl border border-error/30 bg-error/5 p-4 text-sm text-error">
                <p class="font-bold mb-1">Veuillez corriger les erreurs ci-dessous.</p>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('account-requests.store') }}" class="space-y-6">
            @csrf

            <section class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface mb-4">Identité</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Civilité</label>
                        <select name="civilite" class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm">
                            <option value="">—</option>
                            @foreach(['Monsieur' => 'M.', 'Madame' => 'Mme', 'Mademoiselle' => 'Mlle'] as $k => $v)
                                <option value="{{ $k }}" @selected(old('civilite') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Prénom <span class="text-error">*</span></label>
                        <input name="prenom" value="{{ old('prenom') }}" required class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Nom <span class="text-error">*</span></label>
                        <input name="nom" value="{{ old('nom') }}" required class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface mb-4">Contact & affectation</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">E-mail professionnel / de connexion <span class="text-error">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm" placeholder="prenom.nom@..." />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Téléphone</label>
                        <input name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm" placeholder="+241 ..." />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Département / pôle</label>
                        <input name="department" value="{{ old('department') }}" class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm" placeholder="Rédaction, Production..." />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Fonction / poste occupé <span class="text-error">*</span></label>
                        <input name="fonction" value="{{ old('fonction') }}" required class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm" placeholder="Ex. Journaliste, Présentateur, Technicien plateau" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface mb-4">Profil & besoin (indicatif)</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Rattachement à une fiche journaliste existante (si applicable)</label>
                        <select name="journaliste_id" class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm">
                            <option value="">— Non concerné / aucune —</option>
                            @foreach($journalistes as $j)
                                <option value="{{ $j->id }}" @selected((string)old('journaliste_id') === (string)$j->id)>
                                    {{ trim(($j->prenom ?? '').' '.($j->nom ?? '')) }} @if($j->matricule) · {{ $j->matricule }} @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-on-surface-variant">Utile si vous êtes déjà référencé(e) sans compte.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Matricule (si connu)</label>
                        <input name="matricule" value="{{ old('matricule') }}" class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Type d’accès souhaité (indicatif)</label>
                        <select name="requested_access_type" class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm">
                            <option value="">—</option>
                            @foreach(['Rédaction', 'Présentation / antenne', 'Plateau / production', 'Consultation', 'Autre'] as $opt)
                                <option value="{{ $opt }}" @selected(old('requested_access_type') === $opt)>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-on-surface-variant">Indicatif uniquement : l’admin définit les accès à la validation.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-on-surface-variant mb-1">Motif / besoin d’accès</label>
                        <textarea name="motivation" rows="3" class="w-full rounded-lg border border-outline-variant px-3 py-2.5 text-sm">{{ old('motivation') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-outline-variant bg-white p-6 shadow-sm">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="charter_accepted" value="1" @checked(old('charter_accepted')) required class="mt-1 h-4 w-4 rounded border-outline-variant text-primary">
                    <span class="text-sm text-on-surface">Je m’engage à utiliser le compte de manière conforme à la charte et aux règles de l’organisation. <span class="text-error">*</span></span>
                </label>
            </section>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ url('/') }}" class="px-4 py-2.5 rounded-lg border border-outline-variant text-sm font-medium text-on-surface-variant hover:bg-surface-container-low">Annuler</a>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold shadow-sm hover:bg-primary-container hover:text-primary transition-colors">
                    Envoyer la demande
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
