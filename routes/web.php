<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\BulkDeleteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Models\Emission;
use App\Models\ParcoursIntervenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // KPIs RÉELS du jour — même base que la page « Parcours intervenant » :
    // invités programmés aujourd'hui (par heure de passage ou conducteur du jour).
    $parcoursJour = ParcoursIntervenant::query()->where(static function ($q) {
        $q->whereDate('heure_passage_prevue', today())
            ->orWhereHas('conducteur', static fn ($cq) => $cq->whereDate('date_edition', today()));
    });

    // Émissions RÉELLES actives — affichées dans le bandeau publicitaire.
    $emissions = Emission::query()
        ->where('is_active', true)
        ->orderBy('nom')
        ->get(['id', 'nom', 'heure_debut'])
        ->map(static function ($e): array {
            $heure = null;
            try {
                if ($e->heure_debut) {
                    $h = is_object($e->heure_debut) && method_exists($e->heure_debut, 'format')
                        ? $e->heure_debut->format('H:i')
                        : Carbon::parse((string) $e->heure_debut)->format('H:i');
                    $heure = $h ?: null;
                }
            } catch (Throwable) {
                $heure = null;
            }

            return ['nom' => (string) $e->nom, 'heure' => $heure];
        });

    return view('welcome', [
        'kpiJour' => [
            'invites' => (clone $parcoursJour)->count(),
            'confirmes' => (clone $parcoursJour)->whereNotNull('confirme_at')->count(),
            'retards' => (clone $parcoursJour)->enRetard()->count(),
        ],
        'emissions' => $emissions,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('/suppression-en-masse/{resource}', BulkDeleteController::class)
    ->middleware(['auth', 'verified', 'throttle:30,1'])
    ->name('bulk.destroy');

Route::post('/assistant/messages', AssistantController::class)
    ->middleware(['auth', 'verified', 'throttle:assistant'])
    ->name('assistant.messages');

Route::get('/dashboard/pointage', [DashboardController::class, 'pointage'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.pointage');

Route::get('/notifications/etat', [NotificationController::class, 'etat'])
    ->middleware(['auth', 'verified'])
    ->name('notifications.etat');

Route::get('/admin/journal-activite', [ActivityLogController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('admin.activity-logs');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/module_invitation_publique.php';
require __DIR__.'/module_rh.php';
require __DIR__.'/module_autres.php';
require __DIR__.'/module_users.php';
require __DIR__.'/module_sondage.php';
require __DIR__.'/module_alertes.php';

require __DIR__.'/module_workspace.php';
