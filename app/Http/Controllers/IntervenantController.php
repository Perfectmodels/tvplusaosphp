<?php

namespace App\Http\Controllers;

use App\Enums\StatutDispoIntervenantEnum;
use App\Http\Requests\StoreIntervenantRequest;
use App\Http\Requests\UpdateIntervenantRequest;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Services\FormOptionsService;
use App\Services\InvitationService;
use App\Services\PortraitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class IntervenantController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Intervenant::class);
        $recherche = request()->string('q');
        $type = request()->string('type');
        $dispo = request()->string('dispo');
        $periode = request()->string('periode', 'futur');
        $filtreStatut = request()->string('statut', '');
        $emissionId = request()->integer('emission_id', 0);
        $fonction = request()->string('fonction');
        $ville = request()->string('ville');

        $filtersScope = [
            'periode' => $periode->isNotEmpty() ? $periode->toString() : 'futur',
            'emission_id' => $emissionId > 0 ? $emissionId : null,
        ];

        $query = Intervenant::query()
            ->when($recherche->isNotEmpty(), fn ($q) => $q->recherche($recherche))
            ->when($type->isNotEmpty(), fn ($q) => $q->where('type', $type))
            ->when($dispo->isNotEmpty(), fn ($q) => $q->where('disponibilite', $dispo))
            ->when($fonction->isNotEmpty(), fn ($q) => $q->where('fonction', 'LIKE', '%'.$fonction.'%'))
            ->when($ville->isNotEmpty(), fn ($q) => $q->where('ville_residence', $ville))
            ->when($emissionId > 0, fn ($q) => $q->whereHas('sequences', static function ($sq) use ($emissionId) {
                $sq->where('emission_id', $emissionId);
            }))
            ->when($filtreStatut->toString() === 'reserve', fn ($q) => $q->reserveDansConducteurs($filtersScope))
            ->when($filtreStatut->toString() === 'libre', fn ($q) => $q->nonReserveDansPeriode($filtersScope));

        $intervenants = $query
            ->withCount(['sequences AS reservations_futures_count' => static function ($q) {
                $today = now()->startOfDay();
                $q->join('conducteurs', 'conducteurs.id', '=', 'sequences.conducteur_id')
                    ->whereDate('conducteurs.date_edition', '>=', $today->toDateString())
                    ->whereIn('conducteurs.type', ['chef_edition', 'presentateur', 'redaction']);
            }])
            ->orderBy('nom')
            ->paginate(18)
            ->withQueryString();

        // KPIs vrais sur réservations via pivot sequence_intervenants
        $totalI = Intervenant::count();
        $disponibles = Intervenant::where('disponibilite', StatutDispoIntervenantEnum::Disponible->value)->count();
        $aRelancer = Intervenant::where('disponibilite', StatutDispoIntervenantEnum::Occupe->value)->count();
        $reservesToday = (int) Intervenant::reserveDansConducteurs(['periode' => 'today'])->count();
        $reservesFutur = (int) Intervenant::reserveDansConducteurs(['periode' => 'futur'])->count();
        $nouveaux = Intervenant::where('created_at', '>=', now()->subDays(30))->count();

        $emissions = Emission::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->pluck('nom', 'id');

        return view('intervenants.index', compact(
            'intervenants', 'recherche', 'type', 'dispo', 'periode', 'filtreStatut', 'emissionId',
            'totalI', 'disponibles', 'aRelancer', 'reservesToday', 'reservesFutur', 'nouveaux', 'emissions'
        ));
    }

    public function create(FormOptionsService $formOptions): View
    {
        $this->authorize('create', Intervenant::class);

        return view('intervenants.create', ['intervenant' => new Intervenant, 'suggestions' => $formOptions->intervenants()]);
    }

    public function store(StoreIntervenantRequest $request, PortraitService $portraits): RedirectResponse
    {
        $this->authorize('create', Intervenant::class);
        $data = $request->validated();
        unset($data['photo_file'], $data['remove_photo']);
        $data['first_name'] = $data['prenom'] ?? null;
        $data['last_name'] = $data['nom'] ?? null;
        $data['phone'] = $data['telephone_1'] ?? null;
        $data['organization'] = $data['organisme'] ?? null;
        $data['address'] = $data['adresse_physique'] ?? null;
        $data['city'] = $data['ville_residence'] ?? null;
        $data['country'] = $data['pays_residence'] ?? null;
        $data['code'] = $data['code_intervenant'] ?? null;

        $i = DB::transaction(function () use ($data, $request, $portraits) {
            $intervenant = Intervenant::create($data);
            $portraits->saveFromRequest($request, $intervenant);

            return $intervenant;
        });

        return redirect()
            ->route('intervenants.show', ['intervenant' => $i, 'qr' => 1])
            ->with('status', 'Intervenant créé avec succès. QR Code généré automatiquement.');
    }

    public function show(Intervenant $intervenant): View
    {
        $this->authorize('view', $intervenant);

        return view('intervenants.show', compact('intervenant'));
    }

    public function edit(Intervenant $intervenant, FormOptionsService $formOptions): View
    {
        $this->authorize('update', $intervenant);

        return view('intervenants.edit', ['intervenant' => $intervenant, 'suggestions' => $formOptions->intervenants()]);
    }

    public function update(UpdateIntervenantRequest $request, Intervenant $intervenant, PortraitService $portraits): RedirectResponse
    {
        $this->authorize('update', $intervenant);
        $data = $request->validated();
        unset($data['photo_file'], $data['remove_photo']);
        $data['first_name'] = $data['prenom'] ?? $intervenant->first_name;
        $data['last_name'] = $data['nom'] ?? $intervenant->last_name;
        foreach (['telephone_1' => 'phone', 'organisme' => 'organization', 'adresse_physique' => 'address', 'ville_residence' => 'city', 'pays_residence' => 'country'] as $field => $legacy) {
            if (array_key_exists($field, $data)) {
                $data[$legacy] = $data[$field];
            }
        }

        DB::transaction(function () use ($intervenant, $data, $request, $portraits) {
            $anciennePhoto = $intervenant->photo;
            $intervenant->update($data);
            $portraits->saveFromRequest($request, $intervenant, $anciennePhoto);
        });

        return redirect()
            ->route('intervenants.show', $intervenant)
            ->with('status', 'Intervenant mis à jour.');
    }

    public function destroy(Intervenant $intervenant): RedirectResponse
    {
        $this->authorize('delete', $intervenant);
        $intervenant->delete();

        return redirect()
            ->route('intervenants.index')
            ->with('status', 'Intervenant supprimé.');
    }

    // ===== QR CODE SPEC §3.5 =====
    public function qr(Intervenant $intervenant): View
    {
        $this->authorize('qr', $intervenant);

        $parcoursActifs = $intervenant->parcoursIntervenants()
            ->where('est_actif', true)
            ->where('no_show', false)
            ->whereNotNull('heure_passage_prevue')
            ->where('heure_passage_prevue', '>=', now()->subHours(24))
            ->with(['emission', 'sequence.emission', 'conducteur.emission'])
            ->orderBy('heure_passage_prevue')
            ->get();

        $prochainParcours = $parcoursActifs
            ->first(fn (ParcoursIntervenant $parcours): bool => $parcours->heure_passage_prevue?->isFuture())
            ?? $parcoursActifs->first();

        return view('intervenants.qr', [
            'intervenant' => $intervenant,
            'parcoursActifs' => $parcoursActifs,
            'prochainParcours' => $prochainParcours,
        ]);
    }

    public function qrPng(
        Request $request,
        Intervenant $intervenant,
        InvitationService $invitationService,
    ): Response {
        $this->authorize('qr', $intervenant);

        $parcours = $this->parcoursDemande($request, $intervenant);
        $contenu = $parcours
            ? $invitationService->qrPngPourParcours($parcours)
            : $invitationService->qrPng($intervenant);
        $suffixe = $parcours ? '-acces-'.$parcours->id : '';

        return response($contenu, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-'.$intervenant->code_intervenant.$suffixe.'.png"',
        ]);
    }

    public function qrSvg(
        Request $request,
        Intervenant $intervenant,
        InvitationService $invitationService,
    ): Response {
        $this->authorize('qr', $intervenant);

        $parcours = $this->parcoursDemande($request, $intervenant);
        $contenu = $parcours
            ? $this->qrSvgPourParcours($parcours)
            : $invitationService->qrSvg($intervenant);
        $suffixe = $parcours ? '-acces-'.$parcours->id : '';

        return response($contenu, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-'.$intervenant->code_intervenant.$suffixe.'.svg"',
        ]);
    }

    /** QR SVG compact du jeton d'accès d'une invitation précise. */
    private function qrSvgPourParcours(ParcoursIntervenant $parcours): string
    {
        return (string) QrCode::format('svg')
            ->size(512)
            ->errorCorrection('H')
            ->margin(2)
            ->generate((string) $parcours->qr_token);
    }

    /** Parcours ciblé par `?parcours=…`, en vérifiant qu'il appartient bien à l'invité. */
    private function parcoursDemande(Request $request, Intervenant $intervenant): ?ParcoursIntervenant
    {
        $id = $request->integer('parcours');

        if ($id <= 0) {
            return null;
        }

        return $intervenant->parcoursIntervenants()->whereKey($id)->firstOrFail();
    }

    public function qrRegenerate(Intervenant $intervenant): RedirectResponse
    {
        $this->authorize('update', $intervenant);

        // Jeton public + badge scannable : l'ancien QR devient immédiatement invalide.
        $intervenant->regenererTokenInvitation();
        $intervenant->generateQrCode();

        return back()->with('status', "QR Code régénéré pour {$intervenant->nom_complet}. L'ancien badge et l'ancien lien d'invitation ne sont plus valides.");
    }

    /**
     * Envoi de l'invitation WhatsApp depuis la fiche intervenant.
     * Le QR reste permanent ; si une invitation est en attente, elle est
     * liée au message et sa fenêtre de confirmation de 7 jours est ouverte.
     */
    public function envoyerWhatsApp(
        Request $request,
        Intervenant $intervenant,
        InvitationService $invitationService,
    ): RedirectResponse {
        $this->authorize('update', $intervenant);

        $parcours = $this->parcoursDemande($request, $intervenant)
            ?? $intervenant->derniereInvitationEnAttente();
        $lien = $invitationService->lienWhatsApp($intervenant, $parcours);

        if (! $lien) {
            return back()->with(
                'status',
                "Aucun numéro WhatsApp exploitable pour {$intervenant->nom_complet}. Renseignez son téléphone puis réessayez."
            );
        }

        if ($parcours) {
            $invitationService->marquerEnvoi($parcours);
        }

        return redirect()->away($lien);
    }

    // ===== CHECK-IN PUBLIC (fallback page simple) =====
    public function checkIn(string $code, string $t): View
    {
        $i = Intervenant::where('code_intervenant', $code)->where('uuid_intervenant', $t)->firstOrFail();

        return view('intervenants.checkin', compact('i'));
    }
}
