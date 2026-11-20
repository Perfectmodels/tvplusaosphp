<?php

namespace App\Http\Controllers;

use App\Enums\PosteScanEnum;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\IntervenantCheckinAttempt;
use App\Models\ParcoursIntervenant;
use App\Services\InvitationCheckinService;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class ScannerQrController extends Controller
{
    public function index(): View
    {
        Gate::authorize('scan', Intervenant::class);
        $intervenantSelectionne = null;
        if (request()->filled('intervenant')) {
            Gate::authorize('viewAny', Intervenant::class);
            $input = request()->validate(['intervenant' => ['required', 'integer']]);
            $intervenantSelectionne = Intervenant::query()->findOrFail($input['intervenant']);
        }
        $emissions = Emission::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->get(['id', 'nom']);
        $tentativesRecentes = IntervenantCheckinAttempt::query()
            ->with(['intervenant', 'emission', 'scannerBy'])
            ->where('result', '!=', 'pending')
            ->orderByDesc('scanned_at')
            ->limit(15)
            ->get();
        $scansAujourdhui = IntervenantCheckinAttempt::query()
            ->whereDate('scanned_at', today())
            ->count();
        $entreesConfirmees = IntervenantCheckinAttempt::query()
            ->whereDate('scanned_at', today())
            ->where('result', 'accepted')
            ->count();

        return view('scanner-qr.checkin', compact(
            'emissions',
            'tentativesRecentes',
            'scansAujourdhui',
            'entreesConfirmees',
            'intervenantSelectionne',
        ));
    }

    public function lookup(Request $request, InvitationCheckinService $service): JsonResponse
    {
        Gate::authorize('scan', Intervenant::class);

        $valid = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
            'emission_id' => ['required', 'integer', 'exists:emissions,id'],
            'scan_method' => ['required', 'string', Rule::in(['camera', 'manual', 'photo'])],
            'intervenant_id' => ['nullable', 'integer'],
        ]);

        $result = $service->lookup(
            $valid['code'],
            (int) $valid['emission_id'],
            $valid['scan_method'],
            $request,
            $request->user(),
            isset($valid['intervenant_id']) ? (int) $valid['intervenant_id'] : null,
        );

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function tracking(
        ParcoursIntervenant $parcours,
        InvitationCheckinService $service,
    ): JsonResponse {
        Gate::authorize('scan', Intervenant::class);

        return response()->json([
            'ok' => true,
            'parcours' => $service->tracking($parcours),
        ]);
    }

    public function confirm(Request $request, InvitationCheckinService $service): JsonResponse
    {
        Gate::authorize('scan', Intervenant::class);

        $valid = $request->validate([
            'attempt_id' => ['required', 'integer', 'exists:intervenant_checkin_attempts,id'],
            'token' => ['required', 'string', 'max:2048'],
            'emission_id' => ['required', 'integer', 'exists:emissions,id'],
        ]);

        $result = $service->confirm(
            (int) $valid['attempt_id'],
            $valid['token'],
            (int) $valid['emission_id'],
            $request->user(),
        );

        $status = $result['ok'] || $result['result'] === 'already_recorded' ? 200 : 422;

        return response()->json($result, $status);
    }

    public function cancel(Request $request, InvitationCheckinService $service): JsonResponse
    {
        Gate::authorize('scan', Intervenant::class);

        $valid = $request->validate([
            'attempt_id' => ['required', 'integer', 'exists:intervenant_checkin_attempts,id'],
        ]);

        return response()->json($service->cancel(
            (int) $valid['attempt_id'],
            $request->user(),
        ));
    }

    /**
     * POST scan QR → déclenche workflow 3 étapes (SPEC §6.3)
     * ou la CONFIRMATION d'invitation (poste « Invitation »).
     */
    public function store(Request $request): JsonResponse
    {
        // Le scan est réservé aux utilisateurs qui en ont les droits
        // (IntervenantPolicy::scan) : c'est lui qui valide les étapes.
        Gate::authorize('scan', Intervenant::class);
        $valid = $request->validate([
            'code' => ['nullable', 'string'],
            'uuid' => ['nullable', 'string'],
            'poste' => ['required', 'string', new Enum(PosteScanEnum::class)],
            'manual_id' => ['nullable', 'integer', 'exists:intervenants,id'],
        ]);

        $i = ! empty($valid['manual_id'])
            ? Intervenant::find($valid['manual_id'])
            : Intervenant::query()
                ->where(function ($q) use ($valid) {
                    if (! empty($valid['code'])) {
                        $q->orWhere('code_intervenant', $valid['code']);
                    }
                    if (! empty($valid['uuid'])) {
                        $q->orWhere('uuid_intervenant', $valid['uuid']);
                    }
                })
                ->first();

        if (! $i) {
            return response()->json(['ok' => false, 'message' => 'Intervenant introuvable.'], 404);
        }

        $intervenant = [
            'id' => $i->id,
            'nom_complet' => $i->nom_complet,
            'code' => $i->code_intervenant,
            'photo' => $i->photoUrl,
            'organisme' => $i->organisme,
        ];

        // ===== SCAN D'INVITATION : confirme + place (même règle que « Accepté ») =====
        if ($valid['poste'] === PosteScanEnum::Invitation->value) {
            try {
                $resultat = app(InvitationService::class)->confirmerParScan($i, $request->user(), $request);

                // Trace du scan (comme pour les postes logistiques).
                $i->scanLogs()->create([
                    'parcours_intervenant_id' => $resultat['parcours']?->id ?? null,
                    'poste' => PosteScanEnum::Invitation->value,
                    'scanned_at' => now(),
                    'metadata' => [
                        'deja_fait' => (bool) ($resultat['deja_fait'] ?? false),
                        'ip' => $request->ip(),
                        'ua' => $request->userAgent(),
                    ],
                    'qr_code_id' => $i->qrCode?->id,
                    'scanned_by_user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'ok' => (bool) $resultat['ok'],
                    'etape' => $resultat['ok'] ? 'Invitation confirmée' : 'Invitation',
                    'message' => $resultat['message'],
                    'deja_fait' => (bool) ($resultat['deja_fait'] ?? false),
                    'place_dans_conducteur' => (bool) ($resultat['place_dans_conducteur'] ?? false),
                    'intervenant' => $intervenant,
                ], $resultat['ok'] ? 200 : 422);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
            }
        }

        // ===== POSTES LOGISTIQUES (Accueil / Maquillage / Plateau) =====
        try {
            $resultat = $i->triggerScanWorkflow(
                poste: PosteScanEnum::from($valid['poste']),
                userIdScanner: $request->user()?->id,
            );

            return response()->json(array_merge(['ok' => true, 'intervenant' => $intervenant], $resultat));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
