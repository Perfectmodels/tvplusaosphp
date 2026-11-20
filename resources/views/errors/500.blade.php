@extends('layouts.guest')

@section('title', 'Coupure technique — TV+ A OS')

@section('content')
    @include('errors._antenne', [
        'code'    => '500',
        'titre'   => 'Coupure technique',
        'message' => 'Une erreur est survenue côté serveur. Réessayez dans quelques instants ; si le problème persiste, contactez l\'équipe technique.',
        'actions' => [
            [
                'label'  => 'Réessayer',
                'reload' => true,
            ],
            [
                'label' => auth()->check() ? 'Retour au tableau de bord' : 'Aller à la connexion',
                'href'  => auth()->check() ? route('dashboard') : route('login'),
            ],
        ],
    ])
@endsection
