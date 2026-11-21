<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ConducteurEmissionController;
use App\Http\Controllers\ConducteurExportController;
use App\Http\Controllers\ConducteurLifecycleController;
use App\Http\Controllers\ConducteurPresenceController;
use App\Http\Controllers\ConducteurPresentateurController;
use App\Http\Controllers\ConducteurRedactionController;
use App\Http\Controllers\ConducteurRedactionVraiController;
use App\Http\Controllers\ConferenceController;
use App\Http\Controllers\DigitalPublicationController;
use App\Http\Controllers\EditorialWorkflowController;
use App\Http\Controllers\EmissionChatController;
use App\Http\Controllers\EmissionController;
use App\Http\Controllers\EmissionInteractionController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ReglageController;
use App\Http\Controllers\RessourceController;
use App\Http\Controllers\SequenceController;
use App\Http\Controllers\UtilisateurPermissionController;
use App\Http\Controllers\VeilleAutomationController;
use App\Http\Controllers\VeilleRemixController;
use App\Http\Controllers\VeilleSourceController;
use App\Http\Controllers\VeilleSubjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modules : Émissions · Conférences · Conducteurs · Planning · Veille
|           Ressources · Réglages · Archives · Utilisateurs & Permissions
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // ====================== ÉMISSIONS ======================
    Route::prefix('emissions')->name('emissions.')->group(function () {
        Route::get('/', [EmissionController::class, 'index'])->name('index');
        Route::post('/', [EmissionController::class, 'store'])->name('store');
        Route::put('/{emission}', [EmissionController::class, 'update'])->name('update');
        Route::delete('/{emission}', [EmissionController::class, 'destroy'])->name('destroy');
        Route::get('/create', [EmissionController::class, 'create'])->name('create');
        Route::get('/creer', [EmissionController::class, 'create']);
        Route::get('/{emission}/edit', [EmissionController::class, 'edit'])->name('edit');
        Route::get('/{emission}/editer', [EmissionController::class, 'edit']);
        Route::get('/{emission}', [EmissionController::class, 'show'])->name('show');
    });

    // ====================== CONFÉRENCES ======================
    // 🚧 MODULE MASQUÉ POUR PLUS TARD (vues vides) — 14/10/2026.
    // Décommenter pour réactiver + réafficher l'entrée dans _partials/sidebar.blade.php.
    // Route::prefix('conferences')->name('conferences.')->group(function () {
    //     Route::get('/', [ConferenceController::class, 'index'])->name('index');
    //     Route::get('/creer', [ConferenceController::class, 'create'])->name('create');
    //     Route::post('/', [ConferenceController::class, 'store'])->name('store');
    //     Route::get('/{id}', [ConferenceController::class, 'show'])->name('show');
    //     Route::get('/{id}/editer', [ConferenceController::class, 'edit'])->name('edit');
    //     Route::put('/{id}', [ConferenceController::class, 'update'])->name('update');
    //     Route::delete('/{id}', [ConferenceController::class, 'destroy'])->name('destroy');
    // });

    // ====================== CONDUCTEURS ======================
    Route::prefix('conducteurs')->name('conducteurs.')->group(function () {

        // Lifecycle validé : conserve les séquences, y compris à l'archivage.
        Route::patch('/{conducteur}/soumettre', [ConducteurLifecycleController::class, 'soumettre'])->name('lifecycle.soumettre');
        Route::patch('/{conducteur}/valider', [ConducteurLifecycleController::class, 'valider'])->name('lifecycle.valider');
        Route::patch('/{conducteur}/cloturer', [ConducteurLifecycleController::class, 'cloturer'])->name('lifecycle.cloturer');
        Route::patch('/{conducteur}/archiver', [ConducteurLifecycleController::class, 'archiver'])->name('lifecycle.archiver');
        Route::patch('/{conducteur}/restaurer', [ConducteurLifecycleController::class, 'restaurer'])->name('lifecycle.restaurer');

        // ===== CONDUCTEUR ÉMISSION (1 row unique partagé Chef ↔ Présentateur) =====
        Route::get('/emission', [ConducteurEmissionController::class, 'index'])->name('emission.index');
        Route::get('/emission/create', [ConducteurEmissionController::class, 'create'])->name('emission.create');
        Route::post('/emission', [ConducteurEmissionController::class, 'store'])->name('emission.store');
        Route::get('/emission/{conducteur}', [ConducteurEmissionController::class, 'show'])->name('emission.show');
        Route::get('/emission/{conducteur}/export-pdf', [ConducteurExportController::class, 'export'])->name('emission.export-pdf');
        Route::get('/emission/{conducteur}/export-script-pdf', [ConducteurExportController::class, 'script'])->name('emission.export-script-pdf');
        Route::get('/emission/{conducteur}/export-dossier-pdf', [ConducteurExportController::class, 'dossier'])->name('emission.export-dossier-pdf');
        Route::post('/emission/{conducteur}/presentateur', [ConducteurEmissionController::class, 'updatePresentateur'])->name('presentateur.update');
        Route::get('/emission/{conducteur}/interactions', [ConducteurPresentateurController::class, 'interactionMessages'])->name('emission.interactions.index');
        Route::post('/emission/{conducteur}/qr', [EmissionInteractionController::class, 'generateQr'])->name('emission.interactions.generate-qr');
        Route::get('/emission/{conducteur}/qr/image', [EmissionInteractionController::class, 'showQr'])->name('emission.interactions.qr');
        Route::get('/emission/{conducteur}/qr/download', [EmissionInteractionController::class, 'downloadQr'])->name('emission.interactions.qr.download');

        // ===== DISCUSSION INTERNE DE L'ÉMISSION (entre les rôles du conducteur) =====
        // Lecture incrémentale (?after=ID) : l'interface rafraîchit par polling léger
        // et, si le temps réel est activé, reçoit aussi les messages par broadcast.
        Route::get('/emission/{conducteur}/chat', [EmissionChatController::class, 'index'])->name('emission.chat.index');
        Route::post('/emission/{conducteur}/chat', [EmissionChatController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('emission.chat.store');
        Route::delete('/emission/{conducteur}', [ConducteurEmissionController::class, 'destroy'])->name('emission.destroy');

        // ===== CONDUCTEUR CHEF ÉDITION (timeline plateau) — sur type=emission =====
        Route::get('/chef-edition/{conducteurId?}', [ConducteurRedactionController::class, 'index'])->name('chef_edition');
        Route::get('/chef-edition/{conducteur}/live-state', [ConducteurRedactionController::class, 'liveState'])->name('chef_edition.live_state');
        Route::post('/chef-edition', [ConducteurRedactionController::class, 'store'])->name('chef_edition.store');
        Route::match(['put', 'patch'], '/chef-edition/{conducteur}', [ConducteurRedactionController::class, 'update'])->name('chef_edition.update');
        Route::match(['put', 'patch'], '/{conducteur}/reorder', [ConducteurRedactionController::class, 'reorder'])->name('reorder');
        Route::post('/sequences', [SequenceController::class, 'store'])->name('sequences.store');
        Route::match(['put', 'patch'], '/sequences/{sequence}', [SequenceController::class, 'update'])->name('sequences.update');
        Route::delete('/sequences/{sequence}', [SequenceController::class, 'destroy'])->name('sequences.destroy');
        // workflow-next + payload + notes-presentateur CONSERVÉS (utilisés par VRAI Conducteur Rédaction)
        Route::patch('/sequences/{sequence}/workflow-next', [SequenceController::class, 'workflowNext'])->name('sequences.workflow_next');
        Route::get('/sequences/{sequence}/payload', [SequenceController::class, 'ajaxPayload'])->name('sequences.payload');
        Route::patch('/sequences/{sequence}/notes-presentateur', [SequenceController::class, 'updateNotesPresentateur'])->name('sequences.notes_presentateur.update');

        // ===== COMPTE À REBOURS ANTENNE =====
        // Un SEUL chrono pour toute l'émission (démarrage / pause / reset / bascule)
        // → voir ConducteurPresentateurController (voir groupe « presentateur » plus bas).

        // ===== CONDUCTEUR RÉDACTION (VRAI — Suivi production éditoriale pipeline 5 étapes) =====
        Route::get('/redaction', [ConducteurRedactionVraiController::class, 'index'])->name('redaction.index');
        Route::get('/redaction/create', [ConducteurRedactionVraiController::class, 'create'])->name('redaction.create');
        Route::post('/redaction', [ConducteurRedactionVraiController::class, 'storeConducteur'])->name('redaction.store');
        Route::get('/redaction/{conducteur}/live-state', [ConducteurRedactionVraiController::class, 'liveState'])->name('redaction.live-state');
        Route::get('/redaction/{conducteur}', [ConducteurRedactionVraiController::class, 'show'])->name('redaction.show');
        Route::delete('/redaction/{conducteur}', [ConducteurRedactionVraiController::class, 'destroyConducteur'])->name('redaction.destroy');
        // CRUD Sujets dans Vue Rédaction 11 cols
        // NB: 'reorder' DOIT être déclaré AVANT '{sequence}' sinon le binding capture "reorder"
        Route::patch('/redaction/{conducteur}/sujets/reorder', [ConducteurRedactionVraiController::class, 'reorderSujets'])->name('redaction.sujet.reorder');
        Route::post('/redaction/{conducteur}/sujets', [ConducteurRedactionVraiController::class, 'storeSujet'])->name('redaction.sujet.store');
        Route::patch('/redaction/{conducteur}/sujets/{sequence}', [ConducteurRedactionVraiController::class, 'updateSujet'])->name('redaction.sujet.update');
        Route::delete('/redaction/{conducteur}/sujets/{sequence}', [ConducteurRedactionVraiController::class, 'destroySujet'])->name('redaction.sujet.destroy');

        // ===== PRÉSENCES À LA RÉUNION DE RÉDACTION (personnes internes uniquement) =====
        Route::get('/redaction/{conducteur}/presences', [ConducteurPresenceController::class, 'index'])->name('redaction.presence.index');
        Route::patch('/redaction/{conducteur}/conference', [ConducteurPresenceController::class, 'updateConference'])->name('redaction.conference.update');
        Route::patch('/redaction/{conducteur}/presences/{presence}/ponctualite', [ConducteurPresenceController::class, 'markPunctuality'])->name('redaction.presence.ponctualite');
        Route::put('/redaction/{conducteur}/presences', [ConducteurPresenceController::class, 'reconcile'])->name('redaction.presence.reconcile');
        Route::post('/redaction/{conducteur}/presences', [ConducteurPresenceController::class, 'store'])->name('redaction.presence.store');
        Route::post('/redaction/{conducteur}/presences/bulk', [ConducteurPresenceController::class, 'storeBulk'])->name('redaction.presence.bulk');
        Route::patch('/redaction/{conducteur}/presences/{presence}', [ConducteurPresenceController::class, 'update'])->name('redaction.presence.update');
        Route::delete('/redaction/{conducteur}/presences/{presence}', [ConducteurPresenceController::class, 'destroy'])->name('redaction.presence.destroy');

        // ===== CONDUCTEUR PRÉSENTATEUR (Phase 2 — sur type=emission même row que Chef) =====
        Route::get('/presentateur', [ConducteurPresentateurController::class, 'index'])->name('presentateur.index');
        Route::get('/presentateur/create', [ConducteurPresentateurController::class, 'create'])->name('presentateur.create');
        Route::post('/presentateur', [ConducteurPresentateurController::class, 'store'])->name('presentateur.store');
        Route::get('/presentateur/{conducteur}/live-state', [ConducteurPresentateurController::class, 'liveState'])->name('presentateur.live-state');
        Route::get('/presentateur/{conducteur}', [ConducteurPresentateurController::class, 'show'])->name('presentateur.show');
        // CRUD Séquence dans Vue Présentateur 6 cols
        // NB: 'reorder' DOIT être déclaré AVANT '{sequence}' sinon le binding capture "reorder"
        Route::patch('/presentateur/{conducteur}/sequences/reorder', [ConducteurPresentateurController::class, 'reorderSequences'])->name('presentateur.sequence.reorder');
        Route::post('/presentateur/{conducteur}/sequences', [ConducteurPresentateurController::class, 'storeSequence'])->name('presentateur.sequence.store');
        Route::patch('/presentateur/{conducteur}/sequences/{sequence}', [ConducteurPresentateurController::class, 'updateSequence'])->name('presentateur.sequence.update');
        Route::delete('/presentateur/{conducteur}/sequences/{sequence}', [ConducteurPresentateurController::class, 'destroySequence'])->name('presentateur.sequence.destroy');

        // ===== CHRONO ANTENNE — UN SEUL COMPTE À REBOURS POUR TOUTE L'ÉMISSION =====
        Route::post('/presentateur/{conducteur}/antenne/start', [ConducteurPresentateurController::class, 'demarrerAntenne'])->name('presentateur.antenne.start');
        Route::post('/presentateur/{conducteur}/antenne/pause', [ConducteurPresentateurController::class, 'pauseAntenne'])->name('presentateur.antenne.pause');
        Route::post('/presentateur/{conducteur}/antenne/reset', [ConducteurPresentateurController::class, 'reinitialiserAntenne'])->name('presentateur.antenne.reset');
        Route::post('/presentateur/{conducteur}/antenne/sequence', [ConducteurPresentateurController::class, 'marquerSequenceCourante'])->name('presentateur.antenne.sequence');
    });

    // ====================== PLANNING ======================
    // 🚧 MODULE MASQUÉ POUR PLUS TARD (vue vide) — 14/10/2026.
    // Route::get('/planning', [PlanningController::class, 'index'])->name('planning');

    // ====================== WORKFLOW ÉDITORIAL ======================
    Route::post('/veille/sujets/{subject}/promouvoir', [EditorialWorkflowController::class, 'storeEventFromVeille'])
        ->name('veille.sujets.promote');
    Route::get('/evenements-editoriaux/{event}', [EditorialWorkflowController::class, 'show'])
        ->name('evenements-editoriaux.show');
    Route::post('/evenements-editoriaux/{event}/verifications', [EditorialWorkflowController::class, 'storeVerification'])
        ->name('evenements-editoriaux.verifications.store');
    Route::patch('/verifications-editoriales/{verification}/terminer', [EditorialWorkflowController::class, 'finishVerification'])
        ->name('verifications-editoriales.finish');
    Route::post('/evenements-editoriaux/{event}/valider', [EditorialWorkflowController::class, 'validateEvent'])
        ->name('evenements-editoriaux.validate');
    Route::post('/evenements-editoriaux/{event}/rejeter', [EditorialWorkflowController::class, 'rejectEvent'])
        ->name('evenements-editoriaux.reject');
    Route::post('/evenements-editoriaux/{event}/sujets', [EditorialWorkflowController::class, 'storeStory'])
        ->name('evenements-editoriaux.stories.store');
    Route::patch('/sujets-editoriaux/{story}/affecter', [EditorialWorkflowController::class, 'assignStory'])
        ->name('sujets-editoriaux.assign');
    Route::patch('/sujets-editoriaux/{story}/production', [EditorialWorkflowController::class, 'startProduction'])
        ->name('sujets-editoriaux.production.start');
    Route::patch('/sujets-editoriaux/{story}/soumettre-validation', [EditorialWorkflowController::class, 'submitForValidation'])
        ->name('sujets-editoriaux.validation.submit');
    Route::post('/sujets-editoriaux/{story}/valider', [EditorialWorkflowController::class, 'approveStory'])
        ->name('sujets-editoriaux.approve');
    Route::post('/sujets-editoriaux/{story}/rejeter', [EditorialWorkflowController::class, 'rejectStory'])
        ->name('sujets-editoriaux.reject');
    Route::post('/sujets-editoriaux/{story}/conducteur', [EditorialWorkflowController::class, 'placeStory'])
        ->name('sujets-editoriaux.conducteur.store');

    // ====================== VEILLE ======================
    Route::prefix('veille')->name('veille.')->group(function () {
        Route::get('/automatisation', VeilleAutomationController::class)->name('automation');
        Route::post('/sujets', [VeilleSubjectController::class, 'store'])->name('sujets.store');
        Route::get('/sujets', [VeilleSubjectController::class, 'index'])->name('sujets.index');
        Route::get('/sujets/{subject}', [VeilleSubjectController::class, 'show'])->name('sujets.show');
        Route::match(['put', 'patch'], '/sujets/{subject}', [VeilleSubjectController::class, 'update'])->name('sujets.update');
        Route::delete('/sujets/{subject}', [VeilleSubjectController::class, 'destroy'])->name('sujets.destroy');
        // La route de promotion crée maintenant un événement éditorial et ouvre son workflow.
        Route::post('/sujets/{subject}/archiver', [VeilleSubjectController::class, 'archive'])->name('sujets.archive');
        Route::get('/sources', [VeilleSourceController::class, 'index'])->name('sources');
        Route::get('/sources/nouvelle', [VeilleSourceController::class, 'index']);
        Route::post('/sources', [VeilleSourceController::class, 'store'])->name('sources.store');
        Route::post('/sources/trigger-scrape', [VeilleSourceController::class, 'triggerScrape'])->name('sources.trigger');
        Route::match(['put', 'patch'], '/sources/{sourceveille}', [VeilleSourceController::class, 'update'])->name('sources.update');
        Route::delete('/sources/{sourceveille}', [VeilleSourceController::class, 'destroy'])->name('sources.destroy');
        Route::get('/{id?}/detail', [VeilleRemixController::class, 'index'])->name('detail');

        Route::get('/remixes/create/{subject}', [VeilleRemixController::class, 'create'])->name('remixes.create');
        Route::get('/remixes/{remix}/edit', [VeilleRemixController::class, 'edit'])->name('remixes.edit');
        Route::post('/remixes', [VeilleRemixController::class, 'store'])->name('remixes.store');
        Route::match(['put', 'patch'], '/remixes/{remix}', [VeilleRemixController::class, 'update'])->name('remixes.update');
        Route::patch('/remixes/{remix}/validate', [VeilleRemixController::class, 'validateRemix'])->name('remixes.validate');
        Route::delete('/remixes/{remix}', [VeilleRemixController::class, 'destroy'])->name('remixes.destroy');
        Route::post('/remixes/ai-stream', [VeilleRemixController::class, 'aiStream'])->name('remixes.ai_stream');
        Route::post('/remixes/ai-action', [VeilleRemixController::class, 'aiAction'])->name('remixes.ai_action');
    });

    // ====================== RESSOURCES ======================
    // 🚧 MODULES MASQUÉS POUR PLUS TARD (vues vides) — 14/10/2026.
    // Route::get('/ressources', [RessourceController::class, 'index'])->name('ressources');

    // ====================== RÉGLAGES ======================
    // Route::get('/reglages', [ReglageController::class, 'index'])->name('reglages');

    // ====================== ARCHIVES ======================
    // Route::get('/archives', [ArchiveController::class, 'index'])->name('archives');

    // ====================== UTILISATEURS & PERMISSIONS ======================
    Route::get('/utilisateurs-permissions', [UtilisateurPermissionController::class, 'index'])->name('utilisateurs.permissions');
    Route::put('/utilisateurs-permissions/{role}', [UtilisateurPermissionController::class, 'update'])->name('utilisateurs.permissions.update');

});

Route::middleware(['auth', 'verified'])->prefix('digital')->name('digital.')->group(function () {
    Route::get('/', [DigitalPublicationController::class, 'index'])->name('index');
    Route::get('/creer', [DigitalPublicationController::class, 'create'])->name('create');
    Route::post('/', [DigitalPublicationController::class, 'store'])->name('store');
    Route::get('/{publication}', [DigitalPublicationController::class, 'show'])->name('show');
    Route::get('/{publication}/modifier', [DigitalPublicationController::class, 'edit'])->name('edit');
    Route::patch('/{publication}', [DigitalPublicationController::class, 'update'])->name('update');
    Route::post('/{publication}/etat', [DigitalPublicationController::class, 'transition'])->name('transition');
});
