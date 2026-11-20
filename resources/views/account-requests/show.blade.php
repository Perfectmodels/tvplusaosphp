@extends('layouts.app')
@php
    $pageTitle = 'Examen de la demande';
    $pageSubtitle = $accountRequest->nom_complet . ' · ' . $accountRequest->email;
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour aux demandes', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('admin.account-requests.index'), 'variant' => 'ghost'],
    ];
    $isPending = $accountRequest->isPending();
    $defaultRole = old('role', 'journaliste');
    $selectedRoles = collect(old('roles', []))->map(fn($v)=>(string)$v)->all();
@endphp
@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="accountRequestReview()" x-init="init()">
    <div class="rounded-xl border border-outline-variant bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wider text-on-surface-variant font-semibold">Statut</p>
                <div class="mt-1">
                    @if($accountRequest->status === 'pending')
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">En attente de validation</span>
                    @elseif($accountRequest->status === 'approved')
                        <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-800">Validée</span>
                        @if($accountRequest->createdUser)
                            <span class="ml-2 text-sm text-on-surface-variant">Compte créé : {{ $accountRequest->createdUser->email }}</span>
                        @endif
                    @else
                        <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-800">Refusée</span>
                    @endif
                </div>
                <p class="mt-2 text-sm text-on-surface-variant">Soumise le {{ $accountRequest->created_at?->format('d/m/Y à H:i') }} @if($accountRequest->reviewed_at) · Traitée le {{ $accountRequest->reviewed_at->format('d/m/Y à H:i') }} @endif</p>
                @if($accountRequest->status === 'rejected' && $accountRequest->rejection_reason)
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900"><strong>Motif :</strong> {{ $accountRequest->rejection_reason }}</div>
                @endif
            </div>
            <div class="text-sm text-on-surface-variant space-y-1">
                @if($accountRequest->requested_access_type)<p><span class="font-semibold text-on-surface">Accès souhaité (indicatif) :</span> {{ $accountRequest->requested_access_type }}</p>@endif
                @if($accountRequest->matricule)<p><span class="font-semibold text-on-surface">Matricule :</span> {{ $accountRequest->matricule }}</p>@endif
            </div>
        </div>
        @if($accountRequest->motivation)
            <div class="mt-4 rounded-lg bg-surface-container-low p-4 text-sm text-on-surface">
                <p class="font-semibold text-on-surface-variant text-xs uppercase tracking-wider mb-1">Motif / besoin</p>
                <p class="whitespace-pre-line">{{ $accountRequest->motivation }}</p>
            </div>
        @endif
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-error/30 bg-error/5 p-4 text-sm text-error">
            <p class="font-bold mb-1">Erreurs :</p>
            <ul class="list-disc pl-5 space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($isPending)
    <form method="POST" action="{{ route('admin.account-requests.approve', $accountRequest) }}" class="space-y-6">
        @csrf

        <section class="rounded-xl border border-outline-variant bg-surface-container-low p-6">
            <h2 class="text-lg font-black mb-4 flex items-center gap-2 text-on-surface"><span class="w-8 h-8 rounded-lg bg-[#0A2463] text-white flex items-center justify-center text-sm">1</span>Identité & contact (vérifiables)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">Prénom</label>
                    <input name="prenom" value="{{ old('prenom', $accountRequest->prenom) }}" required class="w-full rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">Nom</label>
                    <input name="nom" value="{{ old('nom', $accountRequest->nom) }}" required class="w-full rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm font-semibold">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">E-mail de connexion</label>
                    <input type="email" name="email" value="{{ old('email', $accountRequest->email) }}" required class="w-full rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">Téléphone</label>
                    <input name="phone" value="{{ old('phone', $accountRequest->phone) }}" class="w-full rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">Département / pôle</label>
                    <input name="department" value="{{ old('department', $accountRequest->department) }}" class="w-full rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">Fonction / poste</label>
                    <input name="fonction" value="{{ old('fonction', $accountRequest->fonction) }}" required class="w-full rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">Rattachement journaliste (optionnel)</label>
                    <select name="journaliste_id" class="w-full rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm">
                        <option value="">— Aucun —</option>
                        @foreach($journalistes as $j)
                            <option value="{{ $j->id }}" @selected((string)old('journaliste_id', $accountRequest->journaliste_id) === (string)$j->id)>
                                {{ trim(($j->prenom ?? '').' '.($j->nom ?? '')) }} @if($j->matricule) · {{ $j->matricule }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface-container-low p-6">
            <h2 class="text-lg font-black mb-4 flex items-center gap-2 text-on-surface"><span class="w-8 h-8 rounded-lg bg-[#3B82F6] text-white flex items-center justify-center text-sm">2</span>Rôle principal & fonctions additionnelles (définis à la validation)</h2>

            <div class="mb-6">
                <label for="approve-role" class="block text-sm font-bold text-on-surface mb-2">Rôle principal</label>
                <select id="approve-role" name="role" x-model="role" required class="w-full min-h-[44px] rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm">
                    @foreach($roles as $value => $option)
                        <option value="{{ $value }}" @selected($defaultRole === $value)>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-secondary">Le rôle principal définit l’accès de base. Vous pouvez cumuler des fonctions additionnelles ci-dessous.</p>
            </div>

            <fieldset class="mb-6 rounded-xl border border-outline-variant bg-white p-4">
                <legend class="px-2 text-sm font-bold text-on-surface">Fonctions additionnelles attribuées</legend>
                <p class="mb-3 text-sm text-on-surface-variant">Cochez les fonctions exercées en plus du rôle principal (le rôle principal est inclus automatiquement).</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach($roles as $value => $option)
                        <label class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm text-on-surface transition-colors hover:bg-surface-container has-[:checked]:bg-primary-container/10">
                            <input type="checkbox" name="roles[]" value="{{ $value }}" @checked(in_array($value, $selectedRoles, true)) class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary">
                            <span>{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="mb-6 flex items-center gap-3">
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-on-surface cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') == '1') class="h-4 w-4 rounded border-outline-variant text-primary">
                    Activer le compte à la création
                </label>
            </div>

            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant flex flex-wrap items-center gap-2">
                        <i class="fa-solid fa-key text-[#3B82F6]"></i>
                        Mot de passe temporaire
                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800">À changer à la 1ère connexion</span>
                    </label>
                </div>
                <div class="bg-[#0A2463]/5 border border-[#0A2463]/15 rounded-xl p-4 flex flex-col md:flex-row items-stretch md:items-center gap-3">
                    <input type="hidden" name="temp_password" x-model="tempPassword">
                    <div class="flex-1 bg-[#0A2463] rounded-xl px-5 py-3.5 flex items-center gap-3 font-mono font-bold text-lg tracking-wider text-white shadow-inner select-all">
                        <i class="fa-solid fa-lock text-sm opacity-70"></i>
                        <span x-text="tempPassword" class="flex-1 text-center"></span>
                        <i class="fa-solid fa-shield-halved text-sm opacity-70"></i>
                    </div>
                    <div class="flex items-stretch gap-2">
                        <button type="button" @click="regenerer()" class="flex-1 md:flex-none px-4 py-3 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant font-bold text-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-dice"></i> Régénérer
                        </button>
                        <button type="button" @click="copier()" id="btn-copier-temp" class="flex-1 md:flex-none px-4 py-3 rounded-xl bg-[#3B82F6] text-white font-bold text-sm flex items-center justify-center gap-2 shadow-md">
                            <i class="fa-regular fa-copy"></i> Copier
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <button type="submit" class="px-6 py-3 rounded-xl bg-[#0A2463] text-white font-black hover:brightness-110 transition shadow-lg flex items-center gap-2">
                <i class="fa-solid fa-check"></i> Valider et créer le compte
            </button>
        </div>
    </form>

    <section class="rounded-xl border border-red-200 bg-white p-6">
        <h2 class="text-lg font-black mb-2 text-red-800 flex items-center gap-2"><i class="fa-solid fa-xmark"></i> Refuser la demande</h2>
        <p class="text-sm text-on-surface-variant mb-4">Aucun compte ne sera créé. Le motif sera conservé.</p>
        <form method="POST" action="{{ route('admin.account-requests.reject', $accountRequest) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">Motif du refus <span class="text-error">*</span></label>
                <textarea name="rejection_reason" rows="3" required class="w-full rounded-xl border border-outline-variant px-4 py-3 text-sm">{{ old('rejection_reason') }}</textarea>
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-lg border border-red-300 bg-red-50 text-red-800 font-semibold hover:bg-red-100 transition-colors">
                Refuser la demande
            </button>
        </form>
    </section>
    @endif
</div>
@endsection

@if($isPending)
@push('scripts')
<script>
function accountRequestReview() {
    return {
        role: @json($defaultRole),
        tempPassword: @json(old('temp_password', $tempPasswordDefault)),
        init() {},
        regenerer() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
            let s = '';
            const a = new Uint32Array(12);
            (window.crypto || window.msCrypto).getRandomValues(a);
            for (let i = 0; i < 12; i++) s += chars[a[i] % chars.length];
            this.tempPassword = s;
        },
        async copier() {
            try {
                await navigator.clipboard.writeText(this.tempPassword);
                const b = document.getElementById('btn-copier-temp');
                if (b) { const o = b.innerHTML; b.innerHTML = '<i class="fa-solid fa-check"></i> Copié !'; setTimeout(()=> b.innerHTML = o, 1200); }
            } catch(e) {}
        }
    }
}
</script>
@endpush
@endif
