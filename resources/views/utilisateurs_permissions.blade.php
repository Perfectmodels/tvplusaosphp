@extends('layouts.app')
@php
    $pageTitle = 'Droits par rôle';
    $pageSubtitle = 'Matrice des permissions par fonction — Admin & Superadministrateur';
    $showGlobalSearch = false;
    $headerActions = [];
@endphp
@section('content')
    <div class="space-y-6">
        <div class="bg-surface-container-low border border-outline-variant rounded-xl p-5">
            <h2 class="text-xl font-bold text-on-surface mb-1">Matrice des permissions par rôle</h2>
            <p class="text-sm text-on-surface-variant">
                Cochez les droits à attribuer à chaque fonction. Les permissions techniques
                (hors matrice) sont conservées automatiquement lors de chaque mise à jour.
            </p>
        </div>

        @foreach($roles as $role)
            <div class="bg-surface-container_lowest border border-outline-variant rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-on-surface truncate">{{ $role['label'] }}</h3>
                        <p class="text-xs text-on-surface-variant mt-0.5">
                            {{ count($role['permissions']) }} droit(s) actif(s)
                        </p>
                    </div>
                    @if($role['name'] === 'superadmin')
                        <span class="badge badge-red">Système — protégé</span>
                    @elseif($role['name'] === 'admin')
                        <span class="badge badge-blue">Tous les droits applicatifs</span>
                    @endif
                </div>

                @if($role['name'] === 'superadmin')
                    <div class="p-5">
                        <p class="text-sm text-on-surface-variant">
                            Le rôle <strong>Superadministrateur</strong> conserve automatiquement les droits
                            protégés (tableau de bord, gestion des accès, attribution des rôles).
                        </p>
                    </div>
                @else
                    <form method="POST" action="{{ route('utilisateurs.permissions.update', $role['name']) }}" class="p-5 space-y-6">
                        @csrf
                        @method('PUT')

                        @foreach($modules as $module)
                            <fieldset class="border border-outline-variant/60 rounded-xl p-4">
                                <legend class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-3">
                                    {{ $module['label'] }}
                                </legend>
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                                    @foreach($module['permissions'] as $permission => $label)
                                        <label class="flex items-start gap-3 p-3 rounded-lg border border-outline-variant/40 bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer">
                                            <input type="checkbox"
                                                   name="permissions[]"
                                                   value="{{ $permission }}"
                                                   @checked(in_array($permission, $role['permissions'], true))
                                                   class="mt-1 rounded border-outline-variant text-primary focus:ring-2 focus:ring-primary/30"
                                            >
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-on-surface">{{ $label }}</span>
                                                <span class="block text-[11px] text-on-surface-variant font-mono">{{ $permission }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach

                        <div class="flex items-center gap-4">
                            <button type="submit" class="inline-flex items-center justify-center h-10 px-5 text-sm font-semibold rounded-lg bg-primary text-white hover:bg-primary-container transition-colors">
                                <i class="fa-solid fa-floppy-disk mr-2 text-xs"></i> Enregistrer les droits
                            </button>
                            <p class="text-xs text-on-surface-variant">
                                Les permissions hors matrice (personnalisées) sont conservées.
                            </p>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endsection
