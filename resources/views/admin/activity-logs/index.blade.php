@extends('layouts.app')
@php
    $pageTitle = 'Journal d’activité';
    $pageSubtitle = 'Actions de modification authentifiées, sans conserver les valeurs sensibles.';
    $headerActions = [
        ['label' => 'Retour au tableau global', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('dashboard'), 'variant' => 'secondary'],
    ];
@endphp

@section('content')
    <div class="space-y-6">
        <section class="rounded-2xl border border-outline-variant/70 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('admin.activity-logs') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-outline text-sm"></i>
                    <input type="search" name="q" value="{{ $search }}"
                           placeholder="Filtrer par utilisateur, action, route ou objet…"
                           class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-3 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
                </div>
                <button class="rounded-xl bg-primary-container px-5 py-3 text-sm font-bold text-white transition hover:brightness-110">Rechercher</button>
                @if($search !== '')
                    <a href="{{ route('admin.activity-logs') }}" class="rounded-xl border border-outline-variant px-5 py-3 text-center text-sm font-semibold text-on-surface-variant transition hover:bg-surface-container">Effacer</a>
                @endif
            </form>
            <p class="mt-3 text-xs text-on-surface-variant">{{ $logs->total() }} action(s) enregistrée(s) · valeurs de formulaire, mots de passe et jetons exclus.</p>
        </section>

        <section class="overflow-hidden rounded-2xl border border-outline-variant/70 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table data-mobile-cards class="w-full min-w-[900px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-outline-variant bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Utilisateur</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Objet</th>
                            <th class="px-4 py-3">Route</th>
                            <th class="px-4 py-3">Champs</th>
                            <th class="px-4 py-3">HTTP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60 text-sm">
                        @forelse($logs as $log)
                            <tr class="transition-colors hover:bg-surface-container-lowest">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-on-surface-variant">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td class="px-4 py-3 font-semibold text-on-surface">{{ $log->actor_name ?? 'Compte supprimé' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-on-surface">{{ $log->event }}</div>
                                    <span class="text-xs font-bold uppercase text-on-surface-variant">{{ $log->http_method }}</span>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant">
                                    @if($log->subject_type)
                                        {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                    @else
                                        Action de route
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-on-surface-variant">{{ $log->route_name ?? $log->route_uri ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-on-surface-variant">
                                    @if($log->request_fields)
                                        {{ collect($log->request_fields)->implode(', ') }}
                                    @elseif($log->context)
                                        {{ collect($log->context)->map(fn ($value, $key) => $key.': '.$value)->implode(' · ') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-bold {{ $log->http_status >= 400 ? 'bg-error/10 text-error' : 'bg-tertiary/10 text-tertiary' }}">{{ $log->http_status ?? '—' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-14 text-center text-on-surface-variant">
                                    <span class="material-symbols-outlined mb-2 block text-3xl">history</span>
                                    Aucune action ne correspond à ce filtre.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="border-t border-outline-variant p-4">{{ $logs->onEachSide(1)->links() }}</div>
            @endif
        </section>
    </div>
@endsection
