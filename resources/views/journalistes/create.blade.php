@extends('layouts.app')
@php
    use App\Models\Journaliste;
    use Illuminate\Support\Facades\Gate;

    if (!isset($journaliste) || !($journaliste instanceof Journaliste)) {
        $journaliste = new Journaliste();
    }

    $pageTitle = 'Créer un journaliste';
    $pageSubtitle = 'Ressources humaines • Ajout d\'un nouveau membre';
    $showGlobalSearch = false;
    $headerActions = [
        ['label' => 'Retour', 'icon' => 'fa-solid fa-arrow-left', 'href' => route('journalistes.index'), 'variant' => 'ghost'],
    ];
    if (Gate::check('viewAny', Journaliste::class)) {
        // déjà présent
    }
@endphp
@section('content')
@include('_partials.form-journaliste', ['mode' => 'create', 'journaliste' => $journaliste])
@endsection
