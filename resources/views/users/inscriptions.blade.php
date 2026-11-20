@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 md:px-6 py-8">
    <div class="mb-6">
        <div class="flex items-center gap-3 text-xs text-on-surface-variant uppercase tracking-wider mb-2">
            <i class="fa-solid fa-user-check text-primary"></i>
            <span>Gestion des utilisateurs</span>
        </div>
        <h1 class="text-2xl font-bold text-on-surface">Demandes d'inscription du personnel</h1>
        <p class="text-sm text-on-surface-variant mt-1">Examinez et validez les demandes d'inscription.</p>
    </div>

    {{-- Onglets statut --}}
    <div class="flex gap-2 mb-6 flex-wrap">
        @foreach(['pending' => 'En attente', 'approved' => 'Approuvées', 'rejected' => 'Rejetées'] as $key => $label)
            <a href="{{ route('personnel.inscriptions.index', ['statut' => $key]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors
                      {{ $statut === $key ? 'bg-primary text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                {{ $label }}
                <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-bold
                      {{ $statut === $key ? 'bg-white/20 text-white' : 'bg-surface-container-high text-on-surface-variant' }}">
                    {{ $counts[$key] }}
                </span>
            </a>
        @endforeach
    </div>

    @if ($demandes->isEmpty())
        <div class="bg-white rounded-2xl border border-outline/10 p-8 text-center">
            <i class="fa-solid fa-inbox text-4xl text-on-surface-variant/30 mb-3"></i>
            <p class="text-on-surface-variant">Aucune demande dans cette catégorie.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-outline/10 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-container border-b border-outline/10">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase">Nom</th>
                            <th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase">E-mail</th>
                            <th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase">Pôle</th>
                            <th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase">Fonction</th>
                            <th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase">Date</th>
                            <th class="px-4 py-3 text-xs font-semibold text-on-surface-variant uppercase text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($demandes as $d)
                            <tr class="border-b border-outline/10 hover:bg-surface-container-low transition-colors">
                                <td class="px-4 py-3 font-medium text-on-surface">
                                    {{ $d->nomComplet }}
                                    @if ($d->journalisteExistant)
                                        <span class="ml-1 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-800 font-semibold" title="Profil journaliste existant (sera réutilisé)">
                                            <i class="fa-solid fa-link text-[8px]"></i> Profil existant
                                        </span>
                                    @endif
                                    @if ($d->utilisateurExistant)
                                        <span class="ml-1 inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-semibold" title="Compte utilisateur existant (sera lié)">
                                            <i class="fa-solid fa-user-check text-[8px]"></i> Compte existant
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $d->email }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $d->department ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ $d->fonction ?? '—' }}</td>
                                <td class="px-4 py-3 text-on-surface-variant text-xs">{{ $d->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($d->isPending())
                                        <form method="POST" action="{{ route('personnel.inscriptions.approve', $d) }}" class="inline-flex items-center gap-2">
                                            @csrf
                                            <select name="role" required class="text-xs border border-outline/20 rounded-lg px-2 py-1.5">
                                                @foreach ($roles as $r)
                                                    <option value="{{ $r }}">{{ $r }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-container transition-colors">
                                                <i class="fa-solid fa-check mr-1"></i> Approuver
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('personnel.inscriptions.reject', $d) }}" class="inline-block ml-2">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 border border-error/30 text-error text-xs font-semibold rounded-lg hover:bg-error/10 transition-colors">
                                                <i class="fa-solid fa-xmark mr-1"></i> Rejeter
                                            </button>
                                        </form>
                                    @elseif ($d->isApproved())
                                        <span class="text-xs text-emerald-600 font-semibold">
                                            <i class="fa-solid fa-check-circle mr-1"></i> Approuvé
                                        </span>
                                        @if ($d->user)
                                            <span class="text-xs text-on-surface-variant ml-2">({{ $d->user->email }})</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-red-600 font-semibold">
                                            <i class="fa-solid fa-xmark-circle mr-1"></i> Rejeté
                                        </span>
                                        @if ($d->rejection_reason)
                                            <p class="text-xs text-on-surface-variant mt-1">{{ $d->rejection_reason }}</p>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @if ($d->motivation)
                                <tr class="border-b border-outline/10 bg-surface-container-lowest">
                                    <td colspan="6" class="px-4 py-2 text-xs text-on-surface-variant">
                                        <strong>Motivation :</strong> {{ $d->motivation }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-outline/10">
                {{ $demandes->links() }}
            </div>
        </div>
    @endif
</div>
@endsection
