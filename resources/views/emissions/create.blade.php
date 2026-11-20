@extends('layouts.app')
@php
    $pageTitle = 'Créer une émission';
    $pageSubtitle = 'Module émissions · Suivez les 4 étapes pour configurer votre programme';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour catalogue', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('emissions.index'), 'variant' => 'ghost'],
    ];
@endphp
@section('content')
<div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8 px-4 sm:px-6 lg:px-8 py-6 bg-white border-b border-slate-200 mb-6">
    <p class="text-sm text-slate-500">Suivez les 4 étapes pour configurer votre programme</p>
</div>
@include('_partials.form-emission')
@endsection
