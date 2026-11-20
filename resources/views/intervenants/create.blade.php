@extends('layouts.app')
@php
    use App\Models\Intervenant;
    use Illuminate\Support\Facades\Gate;

    if (!isset($intervenant) || !($intervenant instanceof Intervenant)) {
        $intervenant = new Intervenant();
    }

    $pageTitle = 'Nouvel intervenant';
    $pageSubtitle = 'Ressources humaines • Ajout d\'un nouvel intervenant';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour liste', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('intervenants.index'), 'variant' => 'ghost'],
    ];
@endphp
@section('content')
@include('_partials.form-intervenant', ['mode' => 'create', 'intervenant' => $intervenant])
@endsection
