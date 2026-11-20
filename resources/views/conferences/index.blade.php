@extends('layouts.app')

@section('title', 'Conférences')
@section('page-title', 'Liste des conférences')

@section('page-actions')
    <a href="{{ route('conferences.create') }}" class="btn-primary text-sm"><i class="fas fa-plus mr-2"></i>Nouvelle conférence</a>
@endsection

@section('content')
<div class="card-surface">
    <h2 class="text-2xl font-bold mb-4">=== CONFÉRENCES — LISTE ===</h2>
    <p class="text-outline">En cours d'intégration — prototype HTML : <code>conferences.html</code></p>
</div>
@endsection
