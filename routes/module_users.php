<?php

use App\Http\Controllers\Auth\ResetFirstPasswordController;
use App\Http\Controllers\InscriptionPersonnelAdminController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MODULE GESTION DES UTILISATEURS TV+A OS
|--------------------------------------------------------------------------
*/

// ====== CRUD GESTION UTILISATEURS (Admin / Directeur Général) ======
Route::middleware(['auth', 'verified', 'password.changed'])
    ->group(function () {
        Route::resource('users', UsersController::class)->except(['show']);
        Route::post('/users/{user}/reset-password', [UsersController::class, 'resetPassword'])
            ->name('users.reset_password');
        Route::post('/users/{user}/toggle-active', [UsersController::class, 'toggleActive'])
            ->name('users.toggle_active');
    });

// ====== INSCRIPTION PERSONNEL (Admin) ======
Route::middleware(['auth', 'verified', 'password.changed'])
    ->group(function () {
        Route::get('/personnel/inscriptions', [InscriptionPersonnelAdminController::class, 'index'])
            ->middleware('role:admin|superadmin|directeur_general')
            ->name('personnel.inscriptions.index');
        Route::post('/personnel/inscriptions/{inscription}/approve', [InscriptionPersonnelAdminController::class, 'approve'])
            ->middleware('role:admin|superadmin')
            ->name('personnel.inscriptions.approve');
        Route::post('/personnel/inscriptions/{inscription}/reject', [InscriptionPersonnelAdminController::class, 'reject'])
            ->middleware('role:admin|superadmin')
            ->name('personnel.inscriptions.reject');
    });
// IMPORTANT: middleware password.changed volontairement ABSENT ici (sinon
// redirection infinie vers /first-login-change-password)
Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('/first-login-change-password', [ResetFirstPasswordController::class, 'show'])
            ->name('first-login-change-password.show');
        Route::post('/first-login-change-password', [ResetFirstPasswordController::class, 'store'])
            ->name('first-login-change-password.store');
    });
