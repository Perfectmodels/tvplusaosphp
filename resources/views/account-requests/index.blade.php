@extends('layouts.app')
@php
    $pageTitle = 'Demandes de comptes';
    $pageSubtitle = 'Validation et création des accès utilisateurs';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Utilisateurs', 'icon' => 'fa-solid fa-users', 'href' => route('users.index'), 'variant' => 'ghost'],
    ];
@endphp
@section('content')
<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-wrap items-center gap-2">
        @php
            $tabs = [
                'pending' => ['label' => 'En attente', 'count' => $counts['pending'] ?? 0],
                'approved' => ['label' => 'Validées', 'count' => $counts['approved'] ?? 0],
                'rejected' => ['label' => 'Refusées', 'count' => $counts['rejected'] ?? 0],
                'all' => ['label' => 'Toutes', 'count' => null],
            ];
        @endphp
        @foreach($tabs as $key => $tab)
            <a href="{{ route('admin.account-requests.index', ['status' => $key]) }}"
               class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold transition-colors {{ $status === $key ? 'bg-primary text-white border-primary' : 'bg-white border-outline-variant text-on-surface hover:bg-surface-container-low' }}">
                {{ $tab['label'] }}
                @if($tab['count'] !== null)
                    <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full px-1.5 py-0.5 text-xs {{ $status === $key ? 'bg-white/20 text-white' : 'bg-surface-container text-on-surface-variant' }}">{{ $tab['count'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="bg-surface-container-low border border-outline-variant rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-container-lowest text-left text-xs uppercase tracking-wider text-on-surface-variant">
                    <tr>
                        <th class="px-4 py-3">Demandeur</th>
                        <th class="px-4 py-3">E-mail</th>
                        <th class="px-4 py-3">Fonction</th>
                        <th class="px-4 py-3">Département</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Statut</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40 bg-white">
                @forelse($requests as $r)
                    <tr class="hover:bg-surface-container-low/40">
                        <td class="px-4 py-3 font-semibold text-on-surface">
                            {{ $r->nom_complet }}
                            @if($r->journaliste)
                                <div class="text-xs font-normal text-on-surface-variant mt-0.5">Journaliste lié</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $r->email }}</td>
                        <td class="px-4 py-3">{{ $r->fonction }}</td>
                        <td class="px-4 py-3 text-on-surface-variant">{{ $r->department ?: '—' }}</td>
                        <td class="px-4 py-3 text-on-surface-variant whitespace-nowrap">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if($r->status === 'pending')
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">En attente</span>
                            @elseif($r->status === 'approved')
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-bold text-green-800">Validée</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-800">Refusée</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.account-requests.show', $r) }}" class="inline-flex items-center gap-2 rounded-lg border border-outline-variant px-3 py-2 text-xs font-semibold text-primary hover:bg-primary/5">
                                <i class="fa-solid fa-eye"></i> Examiner
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">Aucune demande.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="border-t border-outline-variant/40 p-3">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection
