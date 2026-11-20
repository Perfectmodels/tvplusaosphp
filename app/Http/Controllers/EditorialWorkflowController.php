<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatutConducteurEnum;
use App\Enums\StatutEvenementEditorialEnum;
use App\Enums\StatutVerificationEditorialEnum;
use App\Models\Conducteur;
use App\Models\EditorialEvent;
use App\Models\EditorialStory;
use App\Models\EditorialVerification;
use App\Models\Journaliste;
use App\Models\User;
use App\Models\VeilleSubject;
use App\Services\EditorialWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EditorialWorkflowController extends Controller
{
    private const LECTURE_ROLES = [
        'admin',
        'directeur_general',
        'chef_edition',
        'chef_redaction',
        'journaliste',
        'correspondant',
        'presentateur',
        'habilleur',
        'lecture',
    ];

    public function storeEventFromVeille(
        Request $request,
        VeilleSubject $subject,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $event = $workflow->ouvrirEvenementDepuisVeille(
            $subject,
            $request->user(),
            $validated['title'] ?? null,
            $validated['description'] ?? null
        );

        return redirect()
            ->route('evenements-editoriaux.show', $event)
            ->with('status', 'Workflow éditorial ouvert à partir de cet élément de veille.');
    }

    public function storeEventFromVeilleApi(
        Request $request,
        VeilleSubject $subject,
        EditorialWorkflowService $workflow
    ): JsonResponse {
        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $event = $workflow->ouvrirEvenementDepuisVeille(
            $subject,
            $request->user(),
            $validated['title'] ?? null,
            $validated['description'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Événement créé ou workflow déjà existant pour cet élément de veille.',
            'data' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status->value,
                'url' => route('evenements-editoriaux.show', $event),
            ],
        ]);
    }

    public function show(Request $request, EditorialEvent $event): View
    {
        $this->autoriserLecture($request->user());

        $event->load([
            'createur',
            'validePar',
            'sourcesVeille.source',
            'verifications.createur',
            'verifications.verifiePar',
            'rejetePar',
            'sujets.journalisteAssigne.user',
            'sujets.affectePar',
            'sujets.soumisPourValidationPar',
            'sujets.rejetePar',
            'sujets.sequences.conducteur',
            'sujets.sharedDossier',
        ]);

        $peutValiderEvenement = $event->status === StatutEvenementEditorialEnum::EnVerification
            && $event->verifications->isNotEmpty()
            && $event->verifications->every(
                fn (EditorialVerification $verification): bool => $verification->status === StatutVerificationEditorialEnum::Confirmee
            );

        $journalistes = Journaliste::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->get(['id', 'prenom', 'nom']);

        $conducteurs = Conducteur::query()
            ->where('type', 'redaction')
            ->whereNull('date_locked_at')
            ->whereNotIn('status', [StatutConducteurEnum::Cloture->value, StatutConducteurEnum::Archive->value])
            ->with('emission')
            ->orderByDesc('date_edition')
            ->get();

        $contributeurEditorial = $request->user()->hasAnyRole([
            'admin', 'directeur_general', 'chef_edition', 'chef_redaction', 'journaliste', 'correspondant',
        ]);
        $journalisteConnecteId = $request->user()->journalisteLie()?->id;

        return view('editorial.events.show', compact(
            'event',
            'journalistes',
            'conducteurs',
            'contributeurEditorial',
            'journalisteConnecteId',
            'peutValiderEvenement'
        ));
    }

    public function storeVerification(
        Request $request,
        EditorialEvent $event,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'claim' => ['required', 'string', 'min:5', 'max:5000'],
            'evidence' => ['nullable', 'string', 'max:20000'],
            'evidence_url' => ['nullable', 'url', 'max:1000'],
        ]);

        $workflow->ajouterVerification($event, $validated, $request->user());

        return $this->retourEvenement($event, 'Vérification ajoutée. Elle doit être examinée par un humain.');
    }

    public function finishVerification(
        Request $request,
        EditorialVerification $verification,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(StatutVerificationEditorialEnum::class)],
            'evidence' => ['nullable', 'string', 'max:20000'],
            'evidence_url' => ['nullable', 'url', 'max:1000'],
        ]);

        $workflow->terminerVerification(
            $verification,
            StatutVerificationEditorialEnum::from($validated['status']),
            $request->user(),
            $validated['evidence'] ?? null,
            $validated['evidence_url'] ?? null
        );

        return $this->retourEvenement($verification->evenement, 'Conclusion humaine enregistrée.');
    }

    public function validateEvent(
        Request $request,
        EditorialEvent $event,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $workflow->validerEvenement($event, $request->user());

        return $this->retourEvenement($event, 'Événement validé par un responsable éditorial.');
    }

    public function rejectEvent(
        Request $request,
        EditorialEvent $event,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $workflow->rejeterEvenement($event, $validated['rejection_reason'], $request->user());

        return $this->retourEvenement($event, 'Événement rejeté ; le motif et le responsable ont été enregistrés.');
    }

    public function storeStory(
        Request $request,
        EditorialEvent $event,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'angle' => ['nullable', 'string', 'max:10000'],
        ]);

        $workflow->creerSujetEditorial($event, $validated, $request->user());

        return $this->retourEvenement($event, 'Sujet éditorial créé.');
    }

    public function assignStory(
        Request $request,
        EditorialStory $story,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'journaliste_id' => ['required', 'integer', 'exists:journalistes,id'],
        ]);

        $journaliste = Journaliste::query()->findOrFail($validated['journaliste_id']);
        $workflow->assignerSujet($story, $journaliste, $request->user());

        return $this->retourEvenement($story->evenement, 'Sujet affecté.');
    }

    public function startProduction(
        Request $request,
        EditorialStory $story,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $workflow->demarrerProduction($story, $request->user());

        return $this->retourEvenement($story->evenement, 'Production démarrée.');
    }

    public function submitForValidation(
        Request $request,
        EditorialStory $story,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $workflow->soumettrePourValidation($story, $request->user());

        return $this->retourEvenement($story->evenement, 'Sujet soumis à validation éditoriale.');
    }

    public function approveStory(
        Request $request,
        EditorialStory $story,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'verification_exception_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflow->validerSujetEditorial(
            $story,
            $request->user(),
            $validated['verification_exception_reason'] ?? null
        );

        return $this->retourEvenement($story->evenement, 'Sujet validé par un responsable éditorial.');
    }

    public function rejectStory(
        Request $request,
        EditorialStory $story,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $workflow->rejeterSujetEditorial($story, $validated['rejection_reason'], $request->user());

        return $this->retourEvenement($story->evenement, 'Sujet rejeté ; le motif et le responsable ont été enregistrés.');
    }

    public function placeStory(
        Request $request,
        EditorialStory $story,
        EditorialWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'conducteur_id' => ['required', 'integer', 'exists:conducteurs,id'],
            'duration_seconds' => ['sometimes', 'integer', 'min:1', 'max:36000'],
        ]);

        $conducteur = Conducteur::query()->findOrFail($validated['conducteur_id']);
        $workflow->placerAuConducteur(
            $story,
            $conducteur,
            $request->user(),
            (int) ($validated['duration_seconds'] ?? 120)
        );

        return $this->retourEvenement($story->evenement, 'Sujet ajouté au conducteur de rédaction.');
    }

    private function autoriserLecture(User $user): void
    {
        abort_unless($user->hasAnyRole(self::LECTURE_ROLES), 403);
    }

    private function retourEvenement(EditorialEvent $event, string $message): RedirectResponse
    {
        return redirect()
            ->route('evenements-editoriaux.show', $event)
            ->with('status', $message);
    }
}
