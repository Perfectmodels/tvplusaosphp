@extends('layouts.guest')

@section('title', 'Signal perdu — TV+ A OS')

@section('content')
    @include('errors._antenne', [
        'code'    => '404',
        'titre'   => 'Signal perdu',
        'message' => 'Cette page n\'est pas sur l\'antenne : elle a peut-être été déplacée ou supprimée.',
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
