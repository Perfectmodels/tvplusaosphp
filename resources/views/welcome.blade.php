@extends('layouts.guest')
@section('title', 'Bienvenue · TV+A OS')
@push('styles')
    @vite(['resources/css/welcome-eye.css', 'resources/js/welcome-eye.js', 'resources/css/welcome-intro.css', 'resources/js/welcome-intro.js'])
@endpush
@section('content')
    @include('_partials.welcome-intro')
@endsection
