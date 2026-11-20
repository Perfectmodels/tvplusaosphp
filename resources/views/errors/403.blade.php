@extends('layouts.guest')

@section('title', 'Hors cadre — TV+ A OS')

@section('content')
    @include('errors._antenne', [
        'code'    => '403',
        'titre'   => 'Hors cadre',
        'message' => 'Cette zone est réservée à un autre rôle. Contactez votre chef d\'édition si vous pensez qu\'il s\'agit d\'une erreur.',
        'actions' => [
            [
                'label' => auth()->check() ? 'Retour au tableau de bord' : 'Aller à la connexion',
                'href'  => auth()->check() ? route('dashboard') : route('login'),
            ],
            [
                'label' => 'Revenir en arrière',
                'back'  => true,
            ],
        ],
    ])
@endsection
