@extends('layouts.app')

@section('title', 'Scanner QR')
@section('page-title', 'Scanner — Code QR Intervenant')

@section('content')
<div class="card-surface">
    <h2 class="text-2xl font-bold mb-4">=== SCANNER QR INTERVENANT ===</h2>
    <p class="text-outline mb-4">En cours d'intégration — prototype HTML : <code>scanner_qr.html</code></p>
    <div class="h-96 rounded-xl bg-primary/5 border-2 border-dashed border-primary/30 flex items-center justify-center">
        <div class="text-center">
            <i class="fas fa-qrcode text-6xl text-primary opacity-40 mb-4"></i>
            <div class="font-semibold text-primary">Caméra QR Scanner @nimiq</div>
            <div class="text-sm text-outline mt-1">À intégrer — module Nimiq QR Scanner</div>
        </div>
    </div>
</div>
@endsection
