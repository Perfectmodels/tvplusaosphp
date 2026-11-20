@extends('layouts.app')

@section('title', 'Planning')
@section('page-title', 'Planning de production')

@section('page-actions')
    <button class="btn-primary text-sm"><i class="fas fa-plus mr-2"></i>Nouvel événement</button>
@endsection

@section('content')
<div class="card-surface">
    <h2 class="text-2xl font-bold mb-4">=== PLANNING ===</h2>
    <p class="text-outline mb-4">En cours d'intégration — prototype HTML : <code>planning.html</code></p>
    <div class="h-96 rounded-xl border-2 border-dashed border-outline-variant flex items-center justify-center">
        <div class="text-center text-outline">
            <i class="fas fa-calendar-days text-5xl mb-4 opacity-30"></i>
            <div class="font-medium">Calendrier FullCalendar — À intégrer</div>
        </div>
    </div>
</div>
@endsection
