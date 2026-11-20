<?php

declare(strict_types=1);

use App\Http\Controllers\AlerteInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ALERTES INFO GLOBALES
|--------------------------------------------------------------------------
| Créées par l'administration, affichées sur toutes les pages internes.
| Aucune dépendance avec un conducteur ou une séquence.
*/
Route::middleware(['auth', 'verified', 'role:admin|superadmin'])->group(function (): void {
    Route::post('/alertes-info', [AlerteInfoController::class, 'store'])->name('alertes-info.store');
    Route::delete('/alertes-info/{alerteInfo}', [AlerteInfoController::class, 'destroy'])->name('alertes-info.destroy');
});
