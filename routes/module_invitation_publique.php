<?php

use App\Http\Controllers\EmissionInteractionController;
use App\Http\Controllers\InvitationPubliqueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| INVITATION PUBLIQUE — QR / lien WhatsApp (SANS authentification)
|--------------------------------------------------------------------------
|
| Ce fichier est chargé hors du groupe `auth` : un intervenant qui scanne
| son QR depuis son téléphone ne doit jamais tomber sur la page de connexion.
|
| Le jeton est permanent (1 par intervenant) et régénérable depuis l'app.
|
*/
Route::middleware(['throttle:30,1'])
    ->prefix('i')
    ->name('invitations.')
    ->group(function () {
        Route::get('/{token}', [InvitationPubliqueController::class, 'show'])
            ->where('token', '[A-Za-z0-9]{32,80}')
            ->name('show');

        Route::get('/{token}/qr.png', [InvitationPubliqueController::class, 'qr'])
            ->where('token', '[A-Za-z0-9]{32,80}')
            ->name('qr');

        Route::get('/checkin/{token}.png', [InvitationPubliqueController::class, 'checkinQr'])
            ->where('token', '[A-Za-z0-9]{48}')
            ->name('checkin-qr');
    });

Route::prefix('i/emission')
    ->name('emission-interactions.')
    ->group(function () {
        Route::get('/{token}', [EmissionInteractionController::class, 'show'])
            ->where('token', '[A-Za-z0-9]{48}')
            ->middleware('throttle:30,1')
            ->name('show');

        Route::post('/{token}', [EmissionInteractionController::class, 'store'])
            ->where('token', '[A-Za-z0-9]{48}')
            ->middleware('throttle:5,1')
            ->name('store');
    });
