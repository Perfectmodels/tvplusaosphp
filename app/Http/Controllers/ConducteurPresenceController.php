<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PonctualitePresenceEnum;
use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Journaliste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * PRÉSENCES à la réunion de rédaction (Conducteur de Rédaction uniquement).
 * Personnes INTERNES uniquement (journalistes).
 */
final class ConducteurPresenceController extends Controller
{
    public function index(Conducteur $conducteur): JsonResponse
    {
        abort_if($conducteur->type !== 'redaction', 404);
        Gate::authorize('view', $conducteur);

        return response()->json([
            'ok' => true,
            'conference_started_at' => $conducteur->conference_started_at?->toIso8601String(),
            'presences' => $conducteur->presences()->with('journaliste')->get()
                ->map(fn (ConducteurPresence $presence) => $presence->pointagePayload())->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function updateConference(Request $request, Conducteur $conducteur): JsonResponse
    {
        abort_if($conducteur->type !== 'redaction', 404);
        Gate::authorize('update', $conducteur);
        $validated = $request->validate([
            'conference_started_at' => ['required', 'date', 'before_or_equal:now'],
        ], [
            'conference_started_at.before_or_equal' => 'Le début effectif de la conférence ne peut pas être dans le futur.',
        ]);
        $conducteur->update([
            'conference_started_at' => Carbon::parse($validated['conference_started_at'], config('app.timezone'))->setTimezone(config('app.timezone')),
            'conference_started_by' => $request->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'conference_started_at' => $conducteur->conference_started_at->toIso8601String(),
            'message' => 'Début de conférence enregistré. Vous pouvez pointer la ponctualité de chaque présent.',
        ]);
    }

    public function markPunctuality(Request $request, Conducteur $conducteur, ConducteurPresence $presence): JsonResponse
    {
        abort_if($conducteur->type !== 'redaction' || (int) $presence->conducteur_id !== (int) $conducteur->id, 404);
        Gate::authorize('update', $conducteur);
        $validated = $request->validate([
            'ponctualite' => ['present', 'nullable', Rule::enum(PonctualitePresenceEnum::class)],
        ]);
        if ($validated['ponctualite'] !== null && (! $conducteur->conference_started_at || $conducteur->conference_started_at->isFuture())) {
            throw ValidationException::withMessages(['ponctualite' => 'Enregistrez le début effectif de la conférence avant de pointer la ponctualité.']);
        }
        $presence->update([
            'ponctualite' => $validated['ponctualite'],
            'ponctualite_marked_at' => $validated['ponctualite'] !== null ? now() : null,
            'ponctualite_marked_by' => $validated['ponctualite'] !== null ? $request->user()->id : null,
        ]);

        return response()->json([
            'ok' => true,
            'presence' => $presence->fresh('journaliste')->pointagePayload(),
            'message' => 'Pointage manuel enregistré.',
        ]);
    }

    // ============== RECONCILE : réviser la liste complète des présences ==============
    /**
     * Remplace la liste des présences par celle fournie (règle « réviser la liste »).
     *   • personne dans la payload mais pas en base  → créée
     *   • personne dans la payload ET en base        → heure d'arrivée mise à jour
     *   • personne en base mais absente de la payload → supprimée
     *
     * Payload : { presences: [ { journaliste_id, arrivee_at }, ... ] }
     */
    public function reconcile(Request $request, Conducteur $conducteur): JsonResponse
    {
        abort_if((string) $conducteur->type !== 'redaction', 404, 'Les présences ne s\'enregistrent que sur un conducteur de Rédaction.');

        Gate::authorize('update', $conducteur);

        $valid = $request->validate([
            'presences' => ['sometimes', 'array', 'max:60'],
            'presences.*.journaliste_id' => ['required_with:presences', 'integer', 'exists:journalistes,id'],
            'presences.*.arrivee_at' => ['required_with:presences', 'date'],
        ], [
            'presences.*.journaliste_id.exists' => 'Une des personnes sélectionnées n\'existe pas.',
            'presences.*.arrivee_at.date' => 'Une des heures d\'arrivée est invalide.',
        ]);

        $souhaites = [];
        foreach (($valid['presences'] ?? []) as $ligne) {
            $jId = (int) $ligne['journaliste_id'];
            if ($jId > 0 && ! isset($souhaites[$jId])) {
                $souhaites[$jId] = $ligne['arrivee_at'];
            }
        }

        $existantes = ConducteurPresence::where('conducteur_id', $conducteur->id)
            ->get()
            ->keyBy('journaliste_id');

        $ajoutees = 0;
        $misesAJour = 0;
        $supprimees = 0;

        // 1. Créations + mises à jour
        foreach ($souhaites as $jId => $arrivee) {
            $exist = $existantes->get($jId);
            if ($exist) {
                if ((string) $exist->arrivee_at !== (string) $arrivee) {
                    $exist->update(['arrivee_at' => $arrivee]);
                    $misesAJour++;
                }
            } else {
                ConducteurPresence::create([
                    'conducteur_id' => $conducteur->id,
                    'journaliste_id' => $jId,
                    'arrivee_at' => $arrivee,
                    'created_by' => $request->user()?->id,
                ]);
                $ajoutees++;
            }
        }

        // 2. Suppressions (absentes de la liste souhaitée)
        foreach ($existantes as $jId => $presence) {
            if (! isset($souhaites[$jId])) {
                $presence->delete();
                $supprimees++;
            }
        }

        $presences = $conducteur->presences()->with('journaliste')->get()->map(fn (ConducteurPresence $presence) => $presence->pointagePayload())->values()->all();

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Liste révisée : %d ajout(s), %d modification(s), %d suppression(s).',
                $ajoutees,
                $misesAJour,
                $supprimees
            ),
            'presences' => $presences,
            'total' => count($presences),
            'resume' => ['ajoutees' => $ajoutees, 'mises_a_jour' => $misesAJour, 'supprimees' => $supprimees],
        ]);
    }

    // ============== STORE : ajouter une présence (y compris retardataire) ==============
    public function store(Request $request, Conducteur $conducteur): JsonResponse
    {
        abort_if((string) $conducteur->type !== 'redaction', 404, 'Les présences ne s\'enregistrent que sur un conducteur de Rédaction.');

        // ⚠️ Policy DÉDIÉE aux présences (et non ConducteurPolicy::update) :
        // un journaliste doit pouvoir marquer SA PROPRE présence.
        Gate::authorize('create', ConducteurPresence::class);

        $valid = $request->validate([
            'journaliste_id' => ['required', 'integer', 'exists:journalistes,id'],
            'arrivee_at' => ['required', 'date'],
            'presences' => ['sometimes', 'array'],
            'presences.*.journaliste_id' => ['required_with:presences', 'integer', 'exists:journalistes,id'],
            'presences.*.arrivee_at' => ['required_with:presences', 'date'],
        ], [
            'journaliste_id.required' => 'Veuillez sélectionner une personne.',
            'journaliste_id.exists' => 'Cette personne n\'existe pas.',
            'arrivee_at.required' => 'Veuillez indiquer la date et l\'heure d\'arrivée.',
            'arrivee_at.date' => 'La date/heure d\'arrivée est invalide.',
        ]);

        // Un utilisateur non-manager ne peut marquer que SA PROPRE présence
        $this->autoriserProprietaire($request, (int) $valid['journaliste_id']);

        $journaliste = Journaliste::find((int) $valid['journaliste_id']);

        $exist = ConducteurPresence::where('conducteur_id', $conducteur->id)
            ->where('journaliste_id', (int) $valid['journaliste_id'])
            ->first();

        if ($exist) {
            return response()->json([
                'ok' => false,
                'message' => trim($journaliste?->prenom.' '.$journaliste?->nom).' figure déjà dans la liste des présents.',
            ], 409);
        }

        $presence = ConducteurPresence::create([
            'conducteur_id' => $conducteur->id,
            'journaliste_id' => (int) $valid['journaliste_id'],
            'arrivee_at' => $valid['arrivee_at'],
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'ok' => true,
            'message' => trim($journaliste?->prenom.' '.$journaliste?->nom).' ajouté(e) à la liste des présents.',
            'presence' => $this->payload($presence->load('journaliste')),
            'total' => (int) $conducteur->presences()->count(),
        ]);
    }

    // ============== STORE BULK : liste de présents à la création ==============
    public function storeBulk(Request $request, Conducteur $conducteur): JsonResponse
    {
        abort_if((string) $conducteur->type !== 'redaction', 404, 'Les présences ne s\'enregistrent que sur un conducteur de Rédaction.');

        Gate::authorize('update', $conducteur);

        $valid = $request->validate([
            'presences' => ['required', 'array', 'min:1', 'max:60'],
            'presences.*.journaliste_id' => ['required', 'integer', 'exists:journalistes,id'],
            'presences.*.arrivee_at' => ['required', 'date'],
        ]);

        $creees = [];
        foreach ($valid['presences'] as $ligne) {
            $jId = (int) $ligne['journaliste_id'];

            // Une seule présence par personne par conducteur
            if (ConducteurPresence::where('conducteur_id', $conducteur->id)->where('journaliste_id', $jId)->exists()) {
                continue;
            }

            $presence = ConducteurPresence::create([
                'conducteur_id' => $conducteur->id,
                'journaliste_id' => $jId,
                'arrivee_at' => $ligne['arrivee_at'],
                'created_by' => $request->user()?->id,
            ]);

            $creees[] = $this->payload($presence->load('journaliste'));
        }

        return response()->json([
            'ok' => true,
            'message' => count($creees).' présence(s) enregistrée(s).',
            'presences' => $creees,
            'total' => (int) $conducteur->presences()->count(),
        ]);
    }

    // ============== UPDATE : modifier l'heure d'arrivée ==============
    public function update(Request $request, Conducteur $conducteur, ConducteurPresence $presence): JsonResponse
    {
        abort_if((int) $presence->conducteur_id !== (int) $conducteur->id, 404, 'Cette présence ne relève pas de ce conducteur.');

        Gate::authorize('update', $presence);

        $valid = $request->validate([
            'arrivee_at' => ['required', 'date'],
        ], [
            'arrivee_at.required' => 'Veuillez indiquer la date et l\'heure d\'arrivée.',
            'arrivee_at.date' => 'La date/heure d\'arrivée est invalide.',
        ]);

        $presence->update(['arrivee_at' => $valid['arrivee_at']]);

        return response()->json([
            'ok' => true,
            'message' => 'Heure d\'arrivée mise à jour.',
            'presence' => $this->payload($presence->fresh('journaliste')),
        ]);
    }

    // ============== DESTROY : retirer une présence ==============
    public function destroy(Request $request, Conducteur $conducteur, ConducteurPresence $presence): JsonResponse
    {
        abort_if((int) $presence->conducteur_id !== (int) $conducteur->id, 404, 'Cette présence ne relève pas de ce conducteur.');

        Gate::authorize('delete', $presence);

        $nom = $presence->nom_complet;
        $presence->delete();

        return response()->json([
            'ok' => true,
            'message' => $nom.' retiré(e) de la liste des présents.',
            'total' => (int) $conducteur->presences()->count(),
        ]);
    }

    // =====================================================================
    //  HELPERS
    // =====================================================================

    /**
     * Un utilisateur non-manager ne peut enregistrer que SA PROPRE présence.
     */
    private function autoriserProprietaire(Request $request, int $journalisteId): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Authentification requise.');
        }

        // Managers : gestion de tout le monde (géré par le policy `before()`)
        if ($user->hasAnyRole(['admin', 'directeur_general', 'chef_edition', 'chef_redaction'])) {
            return;
        }

        $journaliste = method_exists($user, 'journalisteLie') ? $user->journalisteLie() : null;
        if (! $journaliste || (int) $journaliste->id !== (int) $journalisteId) {
            abort(403, 'Vous ne pouvez enregistrer que votre propre présence.');
        }
    }

    /** Données envoyées au client. */
    private function payload(ConducteurPresence $p): array
    {
        return $p->pointagePayload();
    }
}
