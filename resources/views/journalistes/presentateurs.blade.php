@extends('layouts.app')
@php
    $pageTitle = '🎙️ Présentateurs';
@endphp
@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($presentateurs as $p)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
                <img src="{{ $p->photoUrl }}" class="w-16 h-16 rounded-full ring-2 ring-amber-200 object-cover bg-gray-200">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $p->nom_complet }}</div>
                    <div class="text-xs text-gray-500">{{ $p->matricule }} · {{ optional($p->pole)->label() ?? $p->pole ?? '—' }}</div>
                    <div class="text-xs mt-1 text-amber-700">📺 Depuis {{ $p->date_promotion_presentateur?->format('d/m/Y') ?? '—' }}</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('journalistes.show',$p) }}" class="text-xs text-[#0054cd] hover:underline px-3 py-2.5 inline-flex items-center min-h-[44px] rounded-lg">Fiche</a>
                        @can('demote',$p)
                            <form method="POST" action="{{ route('journalistes.retrograder-presentateur',$p) }}"
                                  data-confirm="Rétrograder {{ $p->nom_complet }} ?"
                                  data-confirm-text="Le statut Présentateur sera retiré.">
                                @csrf
                                <button class="text-xs text-red-600 hover:underline px-3 py-2.5 inline-flex items-center min-h-[44px] rounded-lg">Rétrograder</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-10 text-center text-sm text-gray-500">
                Aucun présentateur pour le moment.
            </div>
        @endforelse
    </div>
    {{ $presentateurs->links() }}
</div>
@endsection
