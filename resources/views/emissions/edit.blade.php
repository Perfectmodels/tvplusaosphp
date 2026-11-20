@extends('layouts.app')
@php
    $pageTitle = "Modifier : {$emission->nom}";
    $pageSubtitle = 'Module émissions · Mise à jour de la fiche programme';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Voir la fiche', 'icon' => 'fa-solid fa-eye', 'href' => route('emissions.show', $emission), 'variant' => 'ghost'],
    ];
@endphp
@section('content')
<div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8 px-4 sm:px-6 lg:px-8 py-6 bg-white border-b border-slate-200 mb-6">
    <p class="text-sm text-slate-500">Mettez à jour les informations de l'émission {{ $emission->nom }}</p>
</div>
@include('_partials.form-emission')
@endsection
