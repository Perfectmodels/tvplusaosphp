<?php

use App\Http\Controllers\IntervenantController;
use App\Http\Controllers\JournalisteController;
use App\Http\Controllers\ParcoursIntervenantController;
use App\Http\Controllers\ScannerQrController;
use App\Http\Controllers\SuiviIntervenantsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MODULE JOURNALISTES / PRESENTATEURS / INTERVENANTS / PARCOURS / SCANNER QR
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // ============ JOURNALISTES ============
    Route::prefix('journalistes')->name('journalistes.')->group(function () {
        Route::get('/', [JournalisteController::class, 'index'])->name('index');
        Route::get('/presentateurs', [JournalisteController::class, 'presentateurs'])->name('presentateurs');
        Route::get('/creer', [JournalisteController::class, 'create'])->name('create');
        Route::post('/', [JournalisteController::class, 'store'])->name('store');
        Route::post('/suppression-en-masse', [JournalisteController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::get('/{journaliste}', [JournalisteController::class, 'show'])->name('show');
        Route::get('/{journaliste}/editer', [JournalisteController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{journaliste}', [JournalisteController::class, 'update'])->name('update');
        Route::delete('/{journaliste}', [JournalisteController::class, 'destroy'])->name('destroy');
        Route::post('/{journaliste}/promouvoir-presentateur', [JournalisteController::class, 'promote'])->name('promouvoir-presentateur');
        Route::post('/{journaliste}/retrograder-presentateur', [JournalisteController::class, 'demote'])->name('retrograder-presentateur');
    });

    // ============ INTERVENANTS + QR ============
    Route::prefix('intervenants')->name('intervenants.')->group(function () {
        Route::get('/', [IntervenantController::class, 'index'])->name('index');
        Route::get('/creer', [IntervenantController::class, 'create'])->name('create');
        Route::post('/', [IntervenantController::class, 'store'])->name('store');
        Route::get('/{intervenant}', [IntervenantController::class, 'show'])->name('show');
        Route::get('/{intervenant}/editer', [IntervenantController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{intervenant}', [IntervenantController::class, 'update'])->name('update');
        Route::delete('/{intervenant}', [IntervenantController::class, 'destroy'])->name('destroy');

        // QR actions
        Route::get('/{intervenant}/qr', [IntervenantController::class, 'qr'])->name('qr');
        Route::get('/{intervenant}/qr/png', [IntervenantController::class, 'qrPng'])->name('qr.png');
        Route::get('/{intervenant}/qr/svg', [IntervenantController::class, 'qrSvg'])->name('qr.svg');
        Route::post('/{intervenant}/qr/regenerer', [IntervenantController::class, 'qrRegenerate'])->name('qr.regenerate');
        Route::post('/{intervenant}/whatsapp', [IntervenantController::class, 'envoyerWhatsApp'])->name('whatsapp');
    });

    // Check-in public (sans auth pour badge — accès via QR code seulement)
    Route::get('/intervenants/checkin/{code}/{t}', [IntervenantController::class, 'checkIn'])->name('intervenants.qr.checkin');

    // ============ PARCOURS INTERVENANT (workflow 9 étapes Invitation → Antenne) ============
    Route::get('/parcours-intervenants', [ParcoursIntervenantController::class, 'index'])
        ->name('parcours-intervenants.index');

    // ===== WORKFLOW PARCOURS — validation manuelle par boutons =====
    Route::post('/parcours-intervenants/programmer', [ParcoursIntervenantController::class, 'programmer'])
        ->name('parcours-intervenants.programmer');
    Route::get('/parcours-intervenants/{parcours}/message', [ParcoursIntervenantController::class, 'messagePreview'])
        ->name('parcours-intervenants.message');
    Route::post('/parcours-intervenants/{parcours}/suggest-message', [ParcoursIntervenantController::class, 'suggestMessage'])
        ->name('parcours-intervenants.suggest-message');
    Route::get('/parcours-intervenants/{parcours}/etat', [ParcoursIntervenantController::class, 'etat'])
        ->name('parcours-intervenants.etat');
    Route::post('/parcours-intervenants/{parcours}/etape', [ParcoursIntervenantController::class, 'avancer'])
        ->name('parcours-intervenants.etape');
    Route::post('/parcours-intervenants/{parcours}/retard', [ParcoursIntervenantController::class, 'marquerRetard'])
        ->name('parcours-intervenants.retard');
    Route::post('/parcours-intervenants/{parcours}/no-show', [ParcoursIntervenantController::class, 'marquerNoShow'])
        ->name('parcours-intervenants.no_show');
    Route::post('/parcours-intervenants/{parcours}/whatsapp', [ParcoursIntervenantController::class, 'envoyerWhatsApp'])
        ->name('parcours-intervenants.whatsapp');
    Route::post('/parcours-intervenants/{parcours}/qr/regenerer', [ParcoursIntervenantController::class, 'regenererQr'])
        ->name('parcours-intervenants.qr.regenerate');

    // ============ SUIVI INTERVENANTS (SPEC §5 TABLEAU — BC alias, redirige vers Parcours pour l'instant) ============
    Route::get('/suivi-intervenants', [SuiviIntervenantsController::class, 'index'])->name('suivi-intervenants.index');

    // ============ SCANNER QR (SPEC §6) ============
    Route::prefix('scanner-qr')->name('scanner-qr.')->group(function () {
        Route::get('/', [ScannerQrController::class, 'index'])->name('index');
        Route::post('/lookup', [ScannerQrController::class, 'lookup'])->middleware('throttle:60,1')->name('lookup');
        Route::get('/parcours/{parcours}', [ScannerQrController::class, 'tracking'])->middleware('throttle:60,1')->name('tracking');
        Route::post('/confirm', [ScannerQrController::class, 'confirm'])->middleware('throttle:60,1')->name('confirm');
        Route::post('/cancel', [ScannerQrController::class, 'cancel'])->middleware('throttle:60,1')->name('cancel');
        Route::post('/scan', [ScannerQrController::class, 'store'])->name('scan');
    });
});
