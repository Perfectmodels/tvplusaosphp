@extends('layouts.app')
@php
    use App\Http\Controllers\UsersController;
    if (!isset($user)) abort(404, 'User manquant');
    if (!isset($roles)) $roles = UsersController::rolesAssignableTo(auth()->user());
    if (!isset($currentRoleName)) $currentRoleName = (string)old('role', optional($user->roles->first())->name ?: (string)$user->role);

    $pageTitle = 'Éditer utilisateur';
    $pageSubtitle = 'Profil · rôles · sécurité';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour liste', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('users.index'), 'variant' => 'ghost'],
    ];
@endphp
@section('content')

<div class="max-w-5xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Carte profil résumé --}}
        <div class="lg:col-span-1 bg-surface-container-low border border-outline-variant rounded-xl p-6 text-center">
            <img src="{{ $user->photoUrl }}" alt="" class="w-28 h-28 rounded-2xl object-cover mx-auto border-4 border-[#3B82F6]/30 shadow-lg shadow-[#0A2463]/20 mb-4 bg-[#001a41]/5">
            <h2 class="font-black text-xl text-on-surface">{{ $user->nomComplet }}</h2>
            <p class="text-xs text-on-surface-variant font-mono mt-1">ID #{{ $user->id }} · {{ $user->email }}</p>
            <div class="mt-4 space-y-1.5 text-left bg-surface-container-lowest border border-outline-variant/60 rounded-xl p-4 text-xs">
                <div class="flex items-center gap-2 justify-between">
                    <span class="text-on-surface-variant">Créé le</span>
                    <span class="font-bold">{{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-2 justify-between">
                    <span class="text-on-surface-variant">1ère co. changé</span>
                    @if($user->password_changed_at)
                        <span class="font-bold text-green-700">{{ $user->password_changed_at->diffForHumans() }}</span>
                    @else
                        <span class="font-bold text-orange-600">⚠️ En attente</span>
                    @endif
                </div>
                @if($user->journaliste)
                    <a href="{{ route('journalistes.show', $user->journaliste->id) }}" target="_blank" class="mt-2 block text-center badge badge-blue w-full hover:brightness-110">
                        🔗 Ouvrir fiche journaliste {{ $user->journaliste->matricule }} →
                    </a>
                @endif
                @can('resetPassword', $user)
                <form method="POST" action="{{ route('users.reset_password', $user) }}" class="mt-2 reset-pw-edit-form">
                    @csrf
                    <button type="submit" class="w-full px-3 py-2.5 rounded-lg bg-[#0A2463] text-white text-xs font-bold hover:brightness-110 transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-key"></i> Réinitialiser MDP + Forcer 1ère co.
                    </button>
                </form>
                @endcan
            </div>
        </div>

        {{-- Formulaire édition --}}
        <form method="POST" action="{{ route('users.update', $user) }}" class="lg:col-span-2 bg-surface-container-low border border-outline-variant rounded-xl p-6">
            @csrf
            @method('PUT')

            <h3 class="text-lg font-black mb-5 flex items-center gap-2 text-on-surface">
                <span class="w-8 h-8 rounded-lg bg-[#0A2463] text-white flex items-center justify-center text-sm">✎</span>
                Informations et permissions
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Nom complet</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                    @error('name')<p class="mt-1 text-xs text-error font-bold">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                    @error('email')<p class="mt-1 text-xs text-error font-bold">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Téléphone</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Département</label>
                    <input type="text" name="department" value="{{ old('department', $user->department) }}"
                           class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-[#0A2463] font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                </div>
                @can('assignRoles', $user)
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Rôle principal</label>
                    <select name="role" class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none">
                        @foreach($roles as $k => $r)
                            <option value="{{ $k }}" @selected(old('role', $currentRoleName) === $k)>{{ $r['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <fieldset class="sm:col-span-2 rounded-xl border border-outline-variant p-4 min-w-0">
                    <legend class="px-2 text-sm font-bold">Fonctions attribuées</legend>
                    <p class="mb-3 text-sm text-secondary">Cochez toutes les fonctions exercées, y compris le rôle principal. La personne pourra choisir son tableau de bord sans changer de compte.</p>
                    @php($checkedRoles = old('roles', $user->getRoleNames()->all()))
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">@foreach($roles as $value => $option)<label class="flex items-center gap-3 min-h-[44px] text-sm"><input type="checkbox" name="roles[]" value="{{ $value }}" @checked(in_array($value, $checkedRoles, true)) class="w-5 h-5 rounded border-outline-variant text-primary">{{ $option['label'] }}</label>@endforeach</div>
                    @error('roles')<p class="mt-2 text-xs font-bold text-error">{{ $message }}</p>@enderror
                </fieldset>
                @else
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Rôle</label>
                    <div class="w-full px-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface font-bold">
                        {{ $roles[$currentRoleName]['label'] ?? ucfirst($currentRoleName) }}
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant">Les rôles ne peuvent être modifiés que par un utilisateur autorisé.</p>
                </div>
                @endcan
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-on-surface-variant mb-2">Statut du compte</label>
                    <label class="flex items-center justify-between px-4 py-3 rounded-xl border border-outline-variant bg-surface-container-lowest cursor-pointer has-[:checked]:bg-green-50">
                        <div>
                            <p class="font-bold text-on-surface text-sm">Compte actif</p>
                            <p class="text-xs text-on-surface-variant">L'utilisateur peut se connecter</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="sr-only peer" @checked((bool) old('is_active', $user->is_active))>
                                <div class="w-11 h-6 bg-surface-container-high rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-green-600 border border-outline-variant relative after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </div>
                        </div>
                    </label>
                    @error('is_active')<p class="mt-1 text-xs text-error font-bold">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-7 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-end">
                <a href="{{ route('users.index') }}" class="px-5 py-3 rounded-xl border border-outline-variant bg-surface-container-low hover:bg-surface-container text-on-surface-variant hover:text-on-surface font-bold transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-xmark"></i> Annuler
                </a>
                <button type="submit" class="px-7 py-3 rounded-xl bg-[#0A2463] text-white font-black hover:brightness-110 transition shadow-lg shadow-[#0A2463]/20 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-save"></i> Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.reset-pw-edit-form').forEach(f => {
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            const { isConfirmed } = await window.TvAlert.confirmDeleteConducteur(
                '🔑 Réinitialiser le mot de passe de cet utilisateur ?',
                'Un nouveau mot de passe temporaire sera généré et affiché en toast.<br>À la prochaine connexion, l\'utilisateur sera <strong>obligé de le changer</strong>.'
            );
            if (isConfirmed) f.submit();
        });
    });
});
</script>
@endpush
