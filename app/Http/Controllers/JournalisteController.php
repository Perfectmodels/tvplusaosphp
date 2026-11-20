<?php

namespace App\Http\Controllers;

use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\StatutJournalisteEnum;
use App\Enums\TypeSequenceEnum;
use App\Http\Requests\StoreJournalisteRequest;
use App\Http\Requests\UpdateJournalisteRequest;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use App\Services\FormOptionsService;
use App\Services\JournalisteAccountService;
use App\Services\PortraitService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class JournalisteController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Journaliste::class);
        $recherche = request()->string('q');
        $journalistes = Journaliste::query()
            ->when($recherche->isNotEmpty(), fn ($q) => $q->recherche($recherche))
            ->withCount(['sujetsResponsables as sujets_count'])
            ->withCount([
                'sujetsResponsables as sujets_diffuses' => fn (Builder $q) => $q->where(
                    'statut_diffusion',
                    StatutEtapeWorkflowEnum::Termine->value
                ),
            ])
            ->orderByDesc('is_presentateur')
            ->orderBy('nom')
            ->paginate(30)
            ->withQueryString();

        $effectif = Journaliste::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN statut = ? THEN 1 ELSE 0 END), 0) as en_mission', [StatutJournalisteEnum::Mission->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN statut = ? THEN 1 ELSE 0 END), 0) as en_conge', [StatutJournalisteEnum::Conge->value])
            ->first();

        $etapesEnCours = implode(' OR ', array_map(fn (string $colonne): string => $colonne.' = ?', Sequence::WORKFLOW_ORDER));
        $sujetsDuJour = Sequence::query()
            ->whereHas('conducteur', fn (Builder $q) => $q->whereDate('date_edition', today()->toDateString()))
            ->whereHas('journalisteResponsable')
            ->where('masque', false)
            ->whereIn('type', [TypeSequenceEnum::Sujet, ...TypeSequenceEnum::redactionCases()])
            ->where(fn (Builder $q) => $q->whereNull('statut_diffusion')->orWhere('statut_diffusion', '!=', StatutEtapeWorkflowEnum::Termine->value))
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$etapesEnCours.' THEN 1 ELSE 0 END), 0) as en_production', array_fill(0, count(Sequence::WORKFLOW_ORDER), StatutEtapeWorkflowEnum::En_cours->value))
            ->first();

        return view('journalistes.index', [
            'journalistes' => $journalistes,
            'recherche' => $recherche,
            'totalJ' => (int) $effectif->total,
            'enMission' => (int) $effectif->en_mission,
            'enConge' => (int) $effectif->en_conge,
            'sujetsEnCours' => (int) $sujetsDuJour->total,
            'sujetsEnProduction' => (int) $sujetsDuJour->en_production,
        ]);
    }

    public function create(Request $request, FormOptionsService $formOptions): View
    {
        $this->authorize('create', Journaliste::class);

        return view('journalistes.create', [
            'journaliste' => new Journaliste,
            'optionsSpecialites' => $formOptions->specialites(),
            'comptesDisponibles' => $request->user()->can('create', User::class)
                ? User::query()->whereNull('journaliste_id')->whereDoesntHave('journalistes')->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    public function store(StoreJournalisteRequest $request, JournalisteAccountService $accounts, PortraitService $portraits): RedirectResponse
    {
        $this->authorize('create', Journaliste::class);
        $data = $request->validated();
        $userId = ! empty($data['user_id']) ? (int) $data['user_id'] : null;
        unset($data['user_id'], $data['photo_file'], $data['remove_photo']);
        $nomPrenom = ['nom' => $data['nom'] ?? null, 'prenom' => $data['prenom'] ?? null];
        $data = array_merge($data, [
            'first_name' => $nomPrenom['prenom'],
            'last_name' => $nomPrenom['nom'],
            'phone' => $data['telephone'] ?? null,
        ]);

        $result = DB::transaction(function () use ($data, $accounts, $portraits, $request, $userId) {
            $j = Journaliste::create($data);
            if (! empty($data['is_presentateur'])) {
                $j->promouvoirPresentateur(true);
            }

            $account = $accounts->creerOuLier($j, $request->user(), $userId);
            $portraits->saveFromRequest($request, $j);

            return ['journaliste' => $j, 'account' => $account];
        }, 3);

        return redirect()
            ->route('journalistes.show', $result['journaliste'])
            ->with('status', 'Journaliste, matricule et compte utilisateur enregistrés.')
            ->with('journaliste_credentials', $result['account']['password'] !== null ? [
                'journaliste_id' => $result['journaliste']->id,
                'email' => $result['account']['user']->email,
                'password' => $result['account']['password'],
            ] : null);
    }

    public function show(Journaliste $journaliste): Response
    {
        $this->authorize('view', $journaliste);
        $journaliste->loadMissing('user.roles');
        if ($journaliste->user === null) {
            $journaliste->setRelation('user', User::with('roles')->where('journaliste_id', $journaliste->id)->first());
        }

        // --- Compteurs RÉELS de sujets (aucun chiffre fabriqué) -------------
        // Règle de ventilation (garantit avenir + enCours + diffuses = total) :
        //   diffuses : statut_diffusion = Terminé
        //   avenir   : pas diffusé ET statut_terrain = En attente
        //   enCours  : tout le reste (terrain démarré, pas encore diffusé)
        $sujets = fn () => $journaliste->sujetsResponsables();
        $diffuses = StatutEtapeWorkflowEnum::Termine->value;
        $attente = StatutEtapeWorkflowEnum::En_attente->value;

        $total = $sujets()->count();

        $nbDiffuses = $sujets()->where('statut_diffusion', $diffuses)->count();

        $nbAvenir = $sujets()
            ->where('statut_terrain', $attente)
            ->where(function (Builder $q) use ($diffuses) {
                $q->whereNull('statut_diffusion')
                    ->orWhere('statut_diffusion', '!=', $diffuses);
            })
            ->count();

        $stats = [
            'total' => $total,
            'diffuses' => $nbDiffuses,
            'avenir' => $nbAvenir,
            'enCours' => max(0, $total - $nbDiffuses - $nbAvenir),
            'diffusePct' => $total > 0 ? (int) round($nbDiffuses * 100 / $total) : 0,
        ];

        // Vraie liste des sujets rattachés (onglet « Sujets »)
        $sujetsResponsables = $sujets()
            ->with(['conducteur.emission', 'emission'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->view('journalistes.show', compact('journaliste', 'stats', 'sujetsResponsables'))
            ->header('Cache-Control', 'no-store');
    }

    public function edit(Journaliste $journaliste, FormOptionsService $formOptions): View
    {
        $this->authorize('update', $journaliste);
        $journaliste->loadMissing('user');
        if ($journaliste->user === null) {
            $journaliste->setRelation('user', User::where('journaliste_id', $journaliste->id)->first());
        }

        return view('journalistes.edit', ['journaliste' => $journaliste, 'optionsSpecialites' => $formOptions->specialites()]);
    }

    public function update(UpdateJournalisteRequest $request, Journaliste $journaliste, JournalisteAccountService $accounts, PortraitService $portraits): RedirectResponse
    {
        $this->authorize('update', $journaliste);
        $data = $request->validated();
        $creerCompte = (bool) ($data['creer_compte'] ?? false);
        unset($data['creer_compte'], $data['user_id'], $data['photo_file'], $data['remove_photo']);
        $anciennePhoto = $journaliste->photo;
        $data['first_name'] = $data['prenom'] ?? $journaliste->first_name;
        $data['last_name'] = $data['nom'] ?? $journaliste->last_name;
        if (array_key_exists('telephone', $data)) {
            $data['phone'] = $data['telephone'];
        }
        $etaitPresentateur = (bool) $journaliste->is_presentateur;

        $account = DB::transaction(function () use ($journaliste, $data, $etaitPresentateur, $creerCompte, $accounts, $portraits, $request, $anciennePhoto) {
            $journaliste = Journaliste::query()->lockForUpdate()->findOrFail($journaliste->id);
            $journaliste->update($data);
            if (! empty($data['is_presentateur']) && ! $etaitPresentateur) {
                $journaliste->promouvoirPresentateur(true);
            } elseif (empty($data['is_presentateur']) && $etaitPresentateur) {
                $journaliste->promouvoirPresentateur(false);
            }
            $newAccount = null;
            $user = $journaliste->user ?? User::where('journaliste_id', $journaliste->id)->first();
            if ($user) {
                if ($user->roles()->where('name', '!=', 'journaliste')->exists()) {
                    $this->authorize('update', $user);
                }
                $user->update([
                    'name' => trim($journaliste->prenom.' '.$journaliste->nom),
                    'email' => $journaliste->email,
                    'phone' => $journaliste->telephone,
                    'is_active' => $journaliste->is_active,
                    'journaliste_id' => $journaliste->id,
                ]);
                $journaliste->update(['user_id' => $user->id]);
            } elseif ($creerCompte) {
                $newAccount = $accounts->creerOuLier($journaliste, $request->user());
            }
            $portraits->saveFromRequest($request, $journaliste, $anciennePhoto);

            return $newAccount;
        }, 3);

        return redirect()
            ->route('journalistes.show', $journaliste)
            ->with('status', 'Journaliste mis à jour.')
            ->with('journaliste_credentials', $account !== null ? [
                'journaliste_id' => $journaliste->id,
                'email' => $account['user']->email,
                'password' => $account['password'],
            ] : null);
    }

    public function destroy(Journaliste $journaliste): RedirectResponse
    {
        $this->authorize('delete', $journaliste);
        $journaliste->delete();

        return redirect()
            ->route('journalistes.index')
            ->with('status', 'Journaliste supprimé.');
    }

    /**
     * Suppression en masse — uniquement la sélection de la page courante.
     * Chaque journaliste est autorisé individuellement (policy) avant
     * toute suppression : un seul refus = 403, rien n'est supprimé.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:journalistes,id'],
        ]);

        $journalistes = Journaliste::whereIn('id', $validated['ids'])->get();

        foreach ($journalistes as $journaliste) {
            $this->authorize('delete', $journaliste);
        }

        $total = DB::transaction(function () use ($journalistes) {
            $journalistes->each->delete();

            return $journalistes->count();
        });

        return redirect()
            ->route('journalistes.index')
            ->with('status', $total.' journaliste'.($total > 1 ? 's' : '').' supprimé'.($total > 1 ? 's' : '').'.');
    }

    public function promote(Journaliste $journaliste): RedirectResponse
    {
        $this->authorize('promote', $journaliste);
        $journaliste->promouvoirPresentateur(true);

        return back()->with('status', "{$journaliste->nom_complet} a été promu·e présentateur·rice.");
    }

    public function demote(Journaliste $journaliste): RedirectResponse
    {
        $this->authorize('demote', $journaliste);
        $journaliste->promouvoirPresentateur(false);

        return back()->with('status', "{$journaliste->nom_complet} n'est plus présentateur·rice.");
    }

    public function presentateurs(): View
    {
        $this->authorize('viewAny', Journaliste::class);
        $presentateurs = Journaliste::presentateurs()->orderBy('nom')->paginate(30);

        return view('journalistes.presentateurs', compact('presentateurs'));
    }
}
