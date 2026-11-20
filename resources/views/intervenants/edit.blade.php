@extends('layouts.app')
@php
    use App\Models\Intervenant;
    use Illuminate\Support\Facades\Gate;

    if (!($intervenant instanceof Intervenant)) {
        $intervenant = Intervenant::findOrFail($intervenant);
    }

    $pageTitle = 'Modifier l\'intervenant';
    $pageSubtitle = 'Mise à jour du profil • '.e($intervenant->nomComplet ?? '');
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour fiche', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('intervenants.show', $intervenant), 'variant' => 'ghost'],
        ['label' => 'Voir la fiche', 'icon' => 'fa-regular fa-eye', 'href' => route('intervenants.show', $intervenant), 'variant' => 'ghost'],
    ];
@endphp
@section('content')
@include('_partials.form-intervenant', ['mode' => 'edit', 'intervenant' => $intervenant])
@endsection
