<?php

declare(strict_types=1);

use App\Http\Controllers\SondageAdminController;
use App\Http\Controllers\SondagePublicController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SONDAGE EN DIRECT — DÉBATS
|--------------------------------------------------------------------------
| Page publique accessible par le QR code (jeton opaque de 48 caractères)
| et administration réservée aux responsables de l'émission.
*/

Route::prefix('s')->name('sondage.')->group(function (): void {
    Route::get('/{token}', [SondagePublicController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{48}')
        ->middleware('throttle:30,1')
        ->name('show');

    Route::post('/{token}/vote', [SondagePublicController::class, 'vote'])
        ->where('token', '[A-Za-z0-9]{48}')
        ->middleware('throttle:10,1')
        ->name('vote');

    Route::get('/{token}/resultats', [SondagePublicController::class, 'resultats'])
        ->where('token', '[A-Za-z0-9]{48}')
        ->middleware('throttle:30,1')
        ->name('resultats');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/emissions/{emission}/sondage/create', [SondageAdminController::class, 'create'])
        ->name('sondages.create');

    Route::post('/emissions/{emission}/sondage', [SondageAdminController::class, 'store'])
        ->name('sondages.store');

    Route::get('/sondages/{sondage}', [SondageAdminController::class, 'show'])
        ->name('sondages.show');

    Route::post('/sondages/{sondage}/ouvrir', [SondageAdminController::class, 'ouvrir'])
        ->name('sondages.ouvrir');

    Route::post('/sondages/{sondage}/clore', [SondageAdminController::class, 'clore'])
        ->name('sondages.clore');

    Route::get('/sondages/{sondage}/qr.svg', [SondageAdminController::class, 'qr'])
        ->name('sondages.qr');
});
