@extends('layouts.app')
@php
    use App\Models\Journaliste;
    use Illuminate\Support\Facades\Gate;

    if (!($journaliste instanceof Journaliste)) {
        $journaliste = Journaliste::findOrFail($journaliste);
    }

    $pageTitle = 'Modifier un journaliste';
    $pageSubtitle = 'Mise à jour du profil • '.e($journaliste->nomComplet ?? '');
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('journalistes.show', $journaliste), 'variant' => 'ghost'],
        ['label' => 'Voir la fiche', 'icon' => 'fa-regular fa-eye', 'href' => route('journalistes.show', $journaliste), 'variant' => 'ghost'],
    ];
@endphp
@section('content')
@include('_partials.form-journaliste', ['mode' => 'edit', 'journaliste' => $journaliste])
@endsection
