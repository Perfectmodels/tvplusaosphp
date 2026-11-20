<?php

use App\Http\Controllers\SharedWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('espace-metier')->name('workspace.')->controller(SharedWorkspaceController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/notifications', 'notifications')->name('notifications');
    Route::get('/notifications/compteur', 'unread')->name('unread');
    Route::post('/notifications/lues', 'markRead')->name('notifications.read');
    Route::get('/{dossier}', 'show')->whereNumber('dossier')->name('show');
    Route::get('/{dossier}/actualisation', 'state')->whereNumber('dossier')->name('state');
    Route::middleware('throttle:60,1')->group(function (): void {
        Route::post('/{dossier}/commentaires', 'comment')->name('comments.store');
        Route::post('/{dossier}/taches', 'task')->name('tasks.store');
        Route::patch('/{dossier}/taches/{task}', 'updateTask')->name('tasks.update');
        Route::post('/{dossier}/fichiers', 'attach')->name('files.store');
        Route::post('/{dossier}/equipe', 'addMember')->name('members.store');
        Route::delete('/{dossier}/equipe/{member}', 'removeMember')->name('members.destroy');
    });
    Route::get('/{dossier}/fichiers/{attachment}', 'download')->name('files.download');
});
