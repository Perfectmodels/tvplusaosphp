@extends('layouts.app')
@php
    use App\Models\User;

    if (!isset($users)) {
        $users = User::query()->latest()->paginate(25);
    }
    if (!isset($roles)) {
        $roles = \App\Http\Controllers\UsersController::ROLES_ASSIGNABLES_UI;
    }
    if (!isset($filters)) {
        $filters = ['q' => request('q',''), 'role' => request('role',''), 'type' => request('type','')];
    }

    $totalU = User::count();
    $totalActifs = User::where('is_active', true)->count();
    $totalStandalone = User::whereNull('journaliste_id')->count();
    $totalLiensJournaliste = User::whereNotNull('journaliste_id')->count();
    $waitingFirstLogin = User::whereNull('password_changed_at')->count();

    $pageTitle = 'Gestion des utilisateurs';
    $pageSubtitle = 'Création de comptes • Rôles & permissions • Sécurité';
    $showGlobalSearch = false;
    $headerActions = [];
    if (auth()->user()->can('create', \App\Models\User::class)) {
        $headerActions[] = ['label' => 'Nouvel utilisateur', 'icon' => 'fa-solid fa-user-plus', 'href' => route('users.create'), 'variant' => 'primary'];
        $headerActions[] = ['label' => 'Demandes d\'inscription', 'icon' => 'fa-solid fa-clipboard-list', 'href' => route('personnel.inscriptions.index'), 'variant' => 'ghost'];
    }
@endphp
@section('content')

{{-- ===== KPIs ===== --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">Total comptes</p>
                <p class="text-3xl font-bold text-[#0A2463]">{{ $totalU }}</p>
                <p class="text-xs text-secondary mt-2">{{ $totalActifs }} actifs · {{ $totalU - $totalActifs }} désactivés</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-[#0A2463]/10 flex items-center justify-center text-[#0A2463]">
                <i class="fa-solid fa-users text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">Liés à un journaliste</p>
                <p class="text-3xl font-bold text-green-700">{{ $totalLiensJournaliste }}</p>
                <p class="text-xs text-secondary mt-2">{{ $totalU > 0 ? round($totalLiensJournaliste*100/$totalU, 0) : 0 }}% de l'effectif</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center text-green-700">
                <i class="fa-solid fa-id-badge text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">Comptes autonomes</p>
                <p class="text-3xl font-bold text-indigo-700">{{ $totalStandalone }}</p>
                <p class="text-xs text-secondary mt-2">Pas liés à une fiche RH journaliste</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-700">
                <i class="fa-solid fa-user-shield text-lg"></i>
            </div>
        </div>
    </div>
    <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-secondary uppercase tracking-wider mb-1">1ère connexion en attente</p>
                <p class="text-3xl font-bold text-orange-600">{{ $waitingFirstLogin }}</p>
                <p class="text-xs text-secondary mt-2">Devront changer leur mot de passe temporaire</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center text-orange-600">
                <i class="fa-solid fa-key text-lg"></i>
            </div>
        </div>
    </div>
</div>

{{-- ===== BARRE FILTRES ===== --}}
<div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col mb-8">
<form method="GET" action="{{ route('users.index') }}">
    <div class="p-4 border-b border-outline-variant flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center bg-surface-container-low">
        <div class="flex flex-col md:flex-row gap-3 w-full lg:w-auto flex-1">
            <div class="relative flex-1 md:max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                <input type="text" name="q" value="{{ e($filters['q']) }}" placeholder="Rechercher nom, email, téléphone..." class="w-full pl-10 pr-4 py-2 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-sm"/>
            </div>
            <select name="role" class="px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-sm min-w-[180px]">
                <option value="">Tous les rôles</option>
                @foreach($roles as $k => $r)
                    <option value="{{ $k }}" @selected($filters['role'] === $k)>{{ $r['label'] }}</option>
                @endforeach
            </select>
            <select name="type" class="px-3 py-2 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-sm min-w-[180px]">
                <option value="">Tous les types</option>
                <option value="journaliste" @selected($filters['type'] === 'journaliste')>🔗 Lié à un journaliste</option>
                <option value="standalone"   @selected($filters['type'] === 'standalone')>🧍 Compte autonome</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="w-11 h-11 flex items-center justify-center border border-outline rounded-lg text-secondary hover:bg-surface-container transition-colors" title="Filtrer">
                <i class="fa-solid fa-filter"></i>
            </button>
            <a href="{{ route('users.index') }}" class="w-11 h-11 flex items-center justify-center border border-outline rounded-lg text-secondary hover:bg-surface-container transition-colors" title="Réinitialiser">
                <i class="fa-solid fa-arrows-rotate"></i>
            </a>
        </div>
    </div>

</form>
    @if($users->contains(fn ($item) => $item->id !== auth()->id() && auth()->user()->can('delete', $item)))
    @include('_partials.bulk-delete-toolbar', ['resource' => 'utilisateurs'])
@endif

<div class="overflow-x-auto">
        <table data-mobile-cards class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-outline-variant bg-surface-container-lowest">
                    <th class="p-4 table-header font-semibold w-16">Photo</th>
                    <th class="p-4 table-header font-semibold">Utilisateur</th>
                    <th class="p-4 table-header font-semibold">Rôle</th>
                    <th class="p-4 table-header font-semibold">Type</th>
                    <th class="p-4 table-header font-semibold">Contact</th>
                    <th class="p-4 table-header font-semibold text-center">État</th>
                    <th class="p-4 table-header font-semibold text-center">1ère co.</th>
                    <th class="p-4 table-header font-semibold text-right w-56">Actions</th>
                </tr>
            </thead>
            <tbody data-bulk-list="bulk-utilisateurs">
            @forelse($users as $u)
                @php
                    $roleName = optional($u->roles->first())->name ?: (string)$u->role;
                    $roleCfg  = $roles[$roleName] ?? ['label' => ucfirst((string)$roleName), 'badge' => 'badge-gray'];
                    $aLien = (bool)$u->journaliste_id;
                @endphp
                <tr class="border-b border-outline-variant hover:bg-surface-container-low transition-colors">
                    <td class="p-4">
                        @include('_partials.bulk-delete-checkbox', ['resource' => 'utilisateurs', 'record' => $u, 'label' => $u->nomComplet])
                        <img src="{{ $u->photoUrl }}" alt="{{ e($u->nomComplet) }}" class="w-10 h-10 rounded-full object-cover border-2 border-surface-container bg-[#001a41]/10">
                    </td>
                    <td class="p-4">
                        <p class="font-semibold text-on-surface">{{ $u->nomComplet }}</p>
                        <p class="text-xs text-on-surface-variant font-medium">ID #{{ $u->id }} · créé {{ $u->created_at?->diffForHumans() ?? '—' }}</p>
                        @if($u->created_by)
                            @php($by = $u->createdByUser)
                            <p class="text-xs text-on-surface-variant/70 mt-0.5">Créé par {{ $by?->nomComplet ?? 'Admin' }}</p>
                        @endif
                    </td>
                    <td class="p-4">
                        <span class="badge {{ $roleCfg['badge'] }}">{{ $roleCfg['label'] }}</span>
                    </td>
                    <td class="p-4">
                        @if($aLien)
                            <a href="{{ route('journalistes.show', $u->journaliste_id) }}" class="inline-flex items-center gap-1.5 badge badge-green hover:bg-green-200 transition-colors" target="_blank" rel="noopener">
                                <i class="fa-solid fa-link"></i> Journaliste {{ $u->journaliste?->matricule ? "· {$u->journaliste->matricule}" : '' }}
                            </a>
                        @else
                            <span class="badge badge-indigo">🧍 Autonome</span>
                        @endif
                    </td>
                    <td class="p-4">
                        <p class="text-sm text-on-surface-variant flex items-center gap-1.5"><i class="fa-solid fa-envelope text-xs opacity-70"></i>{{ $u->email }}</p>
                        @if(!empty($u->phone))<p class="text-xs text-on-surface-variant/70 mt-0.5 flex items-center gap-1.5"><i class="fa-solid fa-phone text-xs opacity-60"></i>{{ $u->phone }}</p>@endif
                    </td>
                    <td class="p-4 text-center">
                        <form method="POST" action="{{ route('users.toggle_active', $u) }}" class="inline toggle-active-form" data-id="{{ $u->id }}">
                            @csrf
                            <label class="inline-flex items-center cursor-pointer" title="Activer / désactiver le compte">
                                <input type="checkbox" class="sr-only peer" @checked((bool)$u->is_active)>
                                <div class="relative w-11 h-6 bg-surface-container-high rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-outline-variant after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600 border border-outline-variant"></div>
                                @if($u->is_active)
                                    <span class="badge badge-green ml-2 !py-0.5">Activé</span>
                                @else
                                    <span class="badge badge-red ml-2 !py-0.5">Désactivé</span>
                                @endif
                            </label>
                        </form>
                    </td>
                    <td class="p-4 text-center">
                        @if($u->password_changed_at !== null)
                            <span class="inline-flex items-center gap-1 text-green-700 text-xs font-semibold">
                                <i class="fa-solid fa-circle-check"></i> Fait
                            </span>
                            <p class="text-xs text-on-surface-variant/60 mt-0.5">
                                {{ $u->password_changed_at?->diffForHumans() }}
                            </p>
                        @else
                            <span class="inline-flex items-center gap-1 text-orange-600 text-xs font-bold">
                                <i class="fa-solid fa-clock"></i> En attente
                            </span>
                        @endif
                    </td>
                    <td class="p-4">
                        <div class="flex items-center justify-end gap-2">
                            @can('update', $u)
                            <a href="{{ route('users.edit', $u) }}" class="px-3 py-2.5 min-h-[44px] rounded-lg bg-surface-container hover:bg-primary-container text-on-surface-variant hover:text-on-primary-container text-xs font-semibold transition" title="Éditer">
                                <i class="fa-solid fa-pen-to-square mr-1"></i>Éditer
                            </a>
                            @endcan
                            @can('resetPassword', $u)
                            <form method="POST" action="{{ route('users.reset_password', $u) }}" class="inline reset-pw-form">
                                @csrf
                                <button type="submit" class="px-3 py-2.5 min-h-[44px] rounded-lg bg-[#0A2463]/10 hover:bg-[#0A2463] text-[#0A2463] hover:text-white text-xs font-bold transition" title="Réinitialiser mot de passe + forcer 1ère connexion">
                                    <i class="fa-solid fa-key mr-1"></i>Reset MDP
                                </button>
                            </form>
                            @endcan
                            @can('delete', $u)
                            <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline delete-user-form" data-nom="{{ e($u->nomComplet) }}">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn-del-user px-3 py-2.5 min-h-[44px] rounded-lg bg-error-container/70 hover:bg-error text-on-error-container hover:text-white text-xs font-bold transition" title="Supprimer le compte utilisateur">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="p-16 text-center text-on-surface-variant text-sm">
                        <div class="mx-auto mb-4 w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-2xl opacity-50"><i class="fa-solid fa-users-gear"></i></div>
                        <p class="font-semibold">Aucun utilisateur ne correspond aux critères.</p>
                        @can('create', \App\Models\User::class)
                            <a href="{{ route('users.create') }}" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-bold hover:brightness-110">
                                <i class="fa-solid fa-user-plus"></i>Créer le premier utilisateur
                            </a>
                        @endcan
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($users, 'hasPages') && $users->hasPages())
        <div class="px-5 py-4 border-t border-outline-variant bg-surface-container-lowest flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs text-on-surface-variant">
                Affichage {{ $users->firstItem() }} à {{ $users->lastItem() }} sur {{ $users->total() }}
            </span>
            {{ $users->links() }}
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle actif/inactif submit au changement
    document.querySelectorAll('.toggle-active-form input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => cb.closest('form').submit());
    });
    // Reset password confirmation
    document.querySelectorAll('.reset-pw-form').forEach(f => {
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            const { isConfirmed } = await window.TvAlert.confirmDeleteConducteur(
                '🔑 Réinitialiser le mot de passe ?',
                'Un nouveau mot de passe temporaire sera généré, et l\'utilisateur sera <strong>obligé de le changer à sa prochaine connexion</strong>.<br>Le nouveau mot de passe s\'affichera dans une alerte à copier-coller.'
            );
            if (isConfirmed) f.submit();
        });
    });
    // Delete user confirmation
    document.querySelectorAll('.delete-user-form').forEach(f => {
        const btn = f.querySelector('.btn-del-user');
        btn?.addEventListener('click', async () => {
            const nom = f.dataset.nom || 'cet utilisateur';
            const { isConfirmed } = await window.TvAlert.confirmDeleteConducteur(
                'Supprimer le compte ' + nom + ' ?',
                '<strong>⚠️ Le journaliste RH lié (si existe) ne sera PAS supprimé</strong>, uniquement dissocié du compte.<br>Cette action est irréversible.'
            );
            if (isConfirmed) f.submit();
        });
    });
});
</script>
@endpush
