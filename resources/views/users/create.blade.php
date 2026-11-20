@extends('layouts.app')
@php
    use App\Http\Controllers\UsersController;
    use App\Models\Journaliste;

    if (!isset($journalistes)) {
        $journalistes = Journaliste::whereNull('user_id')->where('is_active', true)->orderBy('nom')->get(['id','matricule','civilite','nom','prenom','fonction','pole','email','telephone','photo']);
    }
    if (!isset($roles)) {
        $roles = UsersController::rolesAssignableTo(auth()->user());
    }
    if (!isset($tempPasswordDefault)) {
        $tempPasswordDefault = UsersController::genererMotDePasseTemporaire();
    }

    $defaultTemp = $tempPasswordDefault;
    $pageTitle = 'Nouvel utilisateur';
    $pageSubtitle = 'Création de compte · TV+A OS';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour liste', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('users.index'), 'variant' => 'ghost'],
    ];
@endphp
@section('content')

<div class="max-w-5xl mx-auto" x-data="gestionNouvelUtilisateur()" x-init="init()">

    {{-- Onglets --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden mb-6">
        <div class="flex flex-col sm:flex-row border-b border-outline-variant bg-surface-container-lowest">
            <button type="button"
                    @click="tabMode = 'from_journaliste'"
                    :class="tabMode === 'from_journaliste' ? 'bg-primary-container text-on-primary-container border-b-2 border-primary' : 'hover:bg-surface-container text-on-surface-variant'"
                    class="flex-1 px-4 sm:px-6 py-4 font-bold text-sm flex items-center gap-2 justify-center transition-colors">
                <i class="fa-solid fa-id-badge"></i> Depuis un journaliste
                <span class="hidden sm:inline-flex ml-1.5 text-xs font-black px-2 py-0.5 rounded-full bg-[#3B82F6]/10 text-[#3B82F6] uppercase tracking-widest">PRÉCONISÉ</span>
            </button>
            <button type="button"
                    @click="tabMode = 'manual'"
                    :class="tabMode === 'manual' ? 'bg-primary-container text-on-primary-container border-b-2 border-primary' : 'hover:bg-surface-container text-on-surface-variant'"
                    class="flex-1 px-4 sm:px-6 py-4 font-bold text-sm flex items-center gap-2 justify-center transition-colors">
                <i class="fa-solid fa-user-plus"></i> Création manuelle
            </button>
        </div>
    </div>

    <form method="POST" action="{{ route('users.store') }}" id="create-user-form" novalidate>
        @csrf
        <input type="hidden" name="mode" x-model="tabMode">

        {{-- ============== CARD 1 · MODE SELECTION (A PARTIR JOURNALISTE) ============== --}}
        <div x-show="tabMode === 'from_journaliste'" x-transition class="bg-surface-container-low border border-outline-variant rounded-xl p-6 mb-6">
            <h2 class="text-lg font-black mb-5 flex items-center gap-2 text-on-surface">
                <span class="w-8 h-8 rounded-lg bg-[#0A2463] text-white flex items-center justify-center text-sm">1</span>
                Sélectionnez un journaliste (sans compte)
            </h2>

            <div x-data="selectJournaliste()" x-init="initJournalisteList(@json($journalistes))" class="space-y-4">
                {{-- Combobox typeahead --}}
                <div class="relative">
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Journaliste</label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-secondary z-10"></i>
                        <input type="text"
                               id="jrSearch"
                               name="journaliste_id_display"
                               x-model="jrSearch"
                               @focus="jrDropdownOpen = true"
                               @click.outside="jrDropdownOpen = false"
                               placeholder="Rechercher un journaliste par nom, prénom, matricule..."
                               class="w-full pl-10 pr-10 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface font-semibold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-sm">
                        <button type="button" @click="jrDropdownOpen = !jrDropdownOpen" class="absolute right-3 top-1/2 -translate-y-1/2 text-secondary p-2.5 min-w-[44px] min-h-[44px] inline-flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px] transition-transform" :class="jrDropdownOpen ? 'rotate-180' : ''">expand_more</span>
                        </button>
                    </div>
                    <div x-show="jrDropdownOpen && jrFiltered.length > 0" class="absolute z-50 mt-2 w-full bg-surface rounded-xl border border-outline-variant shadow-xl max-h-72 overflow-y-auto p-2">
                        <template x-for="j in jrFiltered" :key="j.id">
                            <button type="button"
                                    @click="pickJr(j)"
                                    class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-surface-container text-left group transition-colors">
                                <img :src="j.photoUrl" alt="" class="w-9 h-9 rounded-full object-cover border border-outline-variant">
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-on-surface truncate" x-text="j.civilite + ' ' + j.prenom + ' ' + j.nom"></p>
                                    <p class="text-xs text-on-surface-variant truncate">
                                        <span x-text="j.matricule" class="font-mono mr-2"></span>
                                        <span x-text="j.fonction_label"></span>
                                    </p>
                                </div>
                                <span class="text-xs badge badge-blue opacity-0 group-hover:opacity-100 transition">Sélectionner →</span>
                            </button>
                        </template>
                    </div>
                    <div x-show="jrDropdownOpen && jrFiltered.length === 0" class="absolute z-50 mt-2 w-full bg-surface rounded-xl border border-outline-variant shadow-xl p-6 text-center text-on-surface-variant text-sm">
                        <i class="fa-solid fa-circle-exclamation text-lg mb-2 text-orange-500"></i>
                        <p class="font-bold">Aucun journaliste disponible</p>
                        <p class="text-xs mt-1">Tous les journalistes ont déjà un compte utilisateur, ou le champ ne correspond à aucun résultat.</p>
                    </div>
                </div>

                {{-- Aperçu --}}
                <template x-if="jrSelected">
                    <div class="bg-[#3B82F6]/5 border border-[#3B82F6]/30 rounded-xl p-5 flex flex-wrap items-start gap-4">
                        <img :src="jrSelected.photoUrl" class="w-16 h-16 rounded-2xl object-cover border-2 border-[#3B82F6]/40 shadow-md">
                        <input type="hidden" name="journaliste_id" :value="jrSelected.id" x-model="jrSelected.id">
                        <label class="block text-xs">Adresse e-mail de connexion
                            <input type="email" name="email" x-model="jrSelected.email" :disabled="tabMode !== 'from_journaliste'" required class="form-input mt-1" placeholder="Adresse e-mail réelle du journaliste">
                        </label>
                        <div class="flex-1 min-w-0">
                            <p class="font-black text-lg text-on-surface" x-text="(jrSelected.civilite ? jrSelected.civilite + ' ' : '') + jrSelected.prenom + ' ' + jrSelected.nom"></p>
                            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-on-surface-variant">
                                <p class="break-all">📧 <span class="font-semibold text-on-surface" x-text="jrSelected.email || 'Adresse à renseigner'"></span></p>
                                <p class="break-all">📱 <span class="font-semibold text-on-surface" x-text="jrSelected.phone || jrSelected.telephone || '—'"></span></p>
                                <p>🏷️ Matricule <span class="font-mono font-semibold text-on-surface" x-text="jrSelected.matricule"></span></p>
                                <p>🧑‍💼 Fonction <span class="font-semibold text-on-surface" x-text="jrSelected.fonction_label"></span></p>
                            </div>
                        </div>
                        <button type="button" @click="clearJr()" class="text-on-surface-variant hover:text-error text-xs font-bold px-3 py-2.5 min-h-[44px] rounded-md hover:bg-error-container/40 transition">
                            <i class="fa-solid fa-xmark mr-1"></i>Changer
                        </button>
                    </div>
                </template>

                <p x-show="!jrSelected && tabMode === 'from_journaliste'" class="text-xs text-orange-600 font-semibold flex items-center gap-1.5 bg-orange-50 border border-orange-200 px-4 py-3 rounded-xl">
                    <i class="fa-solid fa-circle-exclamation"></i> Vous devez sélectionner un journaliste avant de continuer.
                </p>
            </div>
        </div>

        {{-- ============== CARD 1b · MODE MANUEL ============== --}}
        <div x-show="tabMode === 'manual'" x-transition class="bg-surface-container-low border border-outline-variant rounded-xl p-6 mb-6">
            <h2 class="text-lg font-black mb-5 flex items-center gap-2 text-on-surface">
                <span class="w-8 h-8 rounded-lg bg-[#0A2463] text-white flex items-center justify-center text-sm">1</span>
                Identité et contact (Mode manuel — non journaliste)
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Civilité</label>
                    <div class="flex items-center gap-3 px-3 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest">
                        @foreach(['Monsieur' => 'M.', 'Madame' => 'Mme', 'Mademoiselle' => 'Mlle'] as $k => $v)
                            <label class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg cursor-pointer hover:bg-surface-container text-sm transition has-[:checked]:bg-[#0A2463] has-[:checked]:text-white">
                                <input type="radio" name="civilite" value="{{ $k }}" class="sr-only" x-model="form.civilite" @if($k==='Monsieur') checked @endif>
                                <span class="font-bold">{{ $v }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Prénom</label>
                    <input type="text" name="prenom" x-model="form.prenom" placeholder="Ex: Alain"
                           class="w-full pl-4 pr-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Nom</label>
                    <input type="text" name="nom" x-model="form.nom" placeholder="Ex: OYEMI"
                           class="w-full pl-4 pr-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Email professionnel</label>
                    <input type="email" name="email" x-model="form.email" :disabled="tabMode !== 'manual'" placeholder="prenom.nom@tvplusos.tv"
                           class="w-full pl-4 pr-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                    @error('email')<p class="mt-1 text-xs text-error font-bold">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Téléphone</label>
                    <input type="tel" name="phone" x-model="form.phone" placeholder="+241 07 .. .. .. .."
                           class="w-full pl-4 pr-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Département / Pôle</label>
                    <input type="text" name="department" x-model="form.department" placeholder="Ex: Rédaction, Direction, IT, Production..."
                           class="w-full pl-4 pr-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                </div>
            </div>
        </div>

        {{-- ============== CARD 2 · RÔLE + MDP ============== --}}
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-6 mb-6">
            <h2 class="text-lg font-black mb-5 flex items-center gap-2 text-on-surface">
                <span class="w-8 h-8 rounded-lg bg-[#3B82F6] text-white flex items-center justify-center text-sm">2</span>
                Rôle et sécurité
            </h2>

            <div class="mb-6">
                <label for="account-primary-role" class="block text-sm font-bold text-on-surface mb-3">Rôle principal de l’utilisateur</label>
                <select id="account-primary-role" name="role" x-model="form.role" required class="w-full min-h-[44px] rounded-xl border border-outline-variant bg-white px-4 py-3 text-sm">
                    @foreach($roles as $value => $option)<option value="{{ $value }}" @selected(old('role', 'journaliste') === $value)>{{ $option['label'] }}</option>@endforeach
                </select>
                <p class="mt-2 text-sm text-secondary">Le rôle principal est inclus automatiquement. Cochez les autres fonctions exercées pour cumuler leurs accès dès la création.</p>
            </div>

            <fieldset class="mb-6 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                <legend class="px-2 text-sm font-bold text-on-surface">Fonctions attribuées</legend>
                <p class="mb-2 text-sm text-on-surface-variant">Cochez les fonctions exercées en plus du rôle principal (le rôle principal est inclus automatiquement).</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach($roles as $value => $option)
                        <label class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm text-on-surface transition-colors hover:bg-surface-container has-[:checked]:bg-primary-container/10">
                            <input type="checkbox" name="roles[]" value="{{ $value }}" @checked(in_array($value, old('roles', old('additional_roles', [])), true)) class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary">
                            <span>{{ $option['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('roles')<p class="mt-2 text-xs font-bold text-error">{{ $message }}</p>@enderror
                @error('roles.*')<p class="mt-2 text-xs font-bold text-error">{{ $message }}</p>@enderror
                @error('additional_roles')<p class="mt-2 text-xs font-bold text-error">{{ $message }}</p>@enderror
                @error('additional_roles.*')<p class="mt-2 text-xs font-bold text-error">{{ $message }}</p>@enderror
            </fieldset>

            {{-- Mot de passe temporaire --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <label class="text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant flex flex-wrap items-center gap-2">
                        <i class="fa-solid fa-key text-[#3B82F6]"></i>
                        Mot de passe temporaire
                        <span class="badge badge-orange ml-2">Sera changé à la 1ère connexion</span>
                    </label>
                </div>
                <div class="bg-[#0A2463]/5 border border-[#0A2463]/15 rounded-xl p-4 flex flex-col md:flex-row items-stretch md:items-center gap-3">
                    <input type="hidden" name="temp_password" x-model="form.tempPassword">
                    <div class="flex-1 bg-[#0A2463] rounded-xl px-5 py-3.5 flex items-center gap-3 font-mono font-bold text-lg tracking-wider text-white shadow-inner select-all">
                        <i class="fa-solid fa-lock text-sm opacity-70"></i>
                        <span x-text="form.tempPassword" class="flex-1 text-center">{{ $defaultTemp }}</span>
                        <i class="fa-solid fa-shield-halved text-sm opacity-70"></i>
                    </div>
                    <div class="flex items-stretch gap-2">
                        <button type="button" @click="regenererMdp()" class="flex-1 md:flex-none px-4 py-3 rounded-xl bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-[#0A2463] transition-colors font-bold text-sm flex items-center justify-center gap-2">
                            <i class="fa-solid fa-dice"></i> Régénérer
                        </button>
                        <button type="button" @click="copierMdp()" id="btn-copier-mdp" class="flex-1 md:flex-none px-4 py-3 rounded-xl bg-[#3B82F6] text-white hover:brightness-110 transition-colors font-bold text-sm flex items-center justify-center gap-2 shadow-md shadow-blue-500/20">
                            <i class="fa-regular fa-copy"></i> Copier
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============== CTA SUBMIT ============== --}}
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-end p-4 md:p-0">
            <a href="{{ route('users.index') }}" class="px-5 py-3.5 rounded-xl border border-outline-variant bg-surface-container-low hover:bg-surface-container text-on-surface-variant hover:text-on-surface font-bold transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-xmark"></i> Annuler
            </a>
            <button type="submit" :disabled="(tabMode === 'from_journaliste' && !jrSelectedId) || !form.role"
                    class="px-7 py-3.5 rounded-xl bg-[#0A2463] text-white font-black hover:brightness-110 transition shadow-lg shadow-[#0A2463]/30 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                <i class="fa-solid fa-check"></i> Créer l'utilisateur
            </button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
function gestionNouvelUtilisateur() {
    return {
        tabMode: 'from_journaliste',
        jrSelectedId: null,
        form: {
            civilite: 'Monsieur',
            prenom: '', nom: '', email: '', phone: '', department: '',
            role: @json(old('role', 'journaliste')),
            tempPassword: @json($defaultTemp),
        },
        init() {
            // Persistence temp password across component
            this.form.tempPassword = this.form.tempPassword || @json($defaultTemp);
        },
        regenererMdp() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
            let s = '';
            const a = new Uint32Array(12);
            (window.crypto || window.msCrypto).getRandomValues(a);
            for (let i = 0; i < 12; i++) s += chars[a[i] % chars.length];
            this.form.tempPassword = s;
        },
        async copierMdp() {
            try {
                await navigator.clipboard.writeText(this.form.tempPassword);
                const b = document.getElementById('btn-copier-mdp');
                if (b) {
                    const orig = b.innerHTML;
                    b.innerHTML = '<i class="fa-solid fa-check"></i> Copié !';
                    b.classList.add('!bg-green-600');
                    setTimeout(() => { b.innerHTML = orig; b.classList.remove('!bg-green-600'); }, 1300);
                }
            } catch(e) {
                alert('Impossible de copier : ' + e.message);
            }
        }
    };
}

function selectJournaliste() {
    return {
        jrSearch: '',
        jrDropdownOpen: false,
        jrList: [],
        jrSelected: null,
        initJournalisteList(rawList) {
            this.jrList = (rawList || []).map(j => {
                const prenomNom = ((j.prenom||'') + ' ' + (j.nom||'')).trim();
                const email = j.email || '';
                const telephone = j.telephone || j.phone || '';
                // Photo url fallback (même logique que Journaliste::photoUrl)
                const initiales = (j.prenom ? j.prenom.trim()[0] : '') + (j.nom ? j.nom.trim()[0] : 'JV');
                const photo = j.photo || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(initiales || 'JV') + '&background=001a41&color=ffffff&size=256');
                // Fonction label mapping FonctionJournalisteEnum (ex: ChefEdition -> Chef d'édition)
                const f = String(j.fonction || 'Journaliste');
                const fnMap = {Journaliste:'Journaliste', ChefEdition:"Chef d'édition", ChefRubrique:'Chef de rubrique', Redacteur:'Rédacteur', Presentateur:'Présentateur', Autre:'Autre'};
                const fonction_label = fnMap[f] || f.replace(/_/g, ' ');
                return {...j, email, initiales, photoUrl: photo, fonction_label, phone: telephone};
            });
            // Synchronisation parent selected
            this.$watch('jrSelected', (v) => {
                try { this.$root.jrSelectedId = v?.id || null; } catch(e){}
                if (v) this.jrSearch = ((v.civilite ? v.civilite + ' ' : '') + v.prenom + ' ' + v.nom + (v.matricule ? ` · ${v.matricule}` : '')).trim();
            });
        },
        pickJr(j) {
            this.jrSelected = j;
            this.jrDropdownOpen = false;
        },
        clearJr() {
            this.jrSelected = null;
            this.jrSelectedId = null;
            this.jrSearch = '';
            this.jrDropdownOpen = false;
        },
        get jrFiltered() {
            const q = (this.jrSearch || '').trim().toLowerCase();
            if (!q) return this.jrList.slice(0, 60);
            return this.jrList.filter(j => {
                const hay = ((j.civilite||'') + ' ' + (j.prenom||'') + ' ' + (j.nom||'') + ' ' + (j.matricule||'') + ' ' + (j.email||'') + ' ' + (j.fonction_label||'')).toLowerCase();
                return hay.includes(q);
            }).slice(0, 60);
        }
    };
}
</script>
@endpush
