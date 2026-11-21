<?php

namespace App\Http\Controllers;

use App\Enums\RolePresentateurEmissionEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\TypeEmissionEnum;
use App\Http\Requests\StoreEmissionRequest;
use App\Http\Requests\UpdateEmissionRequest;
use App\Models\Emission;
use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Emission::class);

        $q = $request->string('q')->trim();
        $filtreType = $request->string('type', '')->trim();
        $filtreStatut = $request->string('statut', '')->trim();
        $filtreJour = $request->string('jour', '')->trim();

        $formatLabels = [
            TypeEmissionEnum::JT->value => 'Journaux télévisés',
            TypeEmissionEnum::Mag->value => 'Magazines',
            TypeEmissionEnum::Talk->value => 'Talk-shows',
            TypeEmissionEnum::Debat->value => 'Débats',
            TypeEmissionEnum::Sport->value => 'Émissions sportives',
            TypeEmissionEnum::Culture->value => 'Émissions culturelles',
            TypeEmissionEnum::Radio->value => 'Programmes radio',
        ];
        $typeOrder = 'CASE type';
        foreach (array_keys($formatLabels) as $rank => $type) {
            $typeOrder .= ' WHEN ? THEN '.$rank;
        }
        $typeOrder .= ' ELSE 99 END';
        $emissions = Emission::query()
            ->with('presentateurPrincipal')
            ->withCount(['conducteurs' => fn ($query) => $query->typeEmission()])
            ->when($q->isNotEmpty(), fn ($query) => $query->recherche((string) $q))
            ->when($filtreType->isNotEmpty(), fn ($query) => $query->where('type', (string) $filtreType))
            ->when($filtreStatut->isNotEmpty(), fn ($query) => $query->where('statut', (string) $filtreStatut))
            ->when($filtreJour->isNotEmpty(), fn ($query) => $query->whereJsonContains('jours_diffusion', (string) $filtreJour))
            ->orderByRaw($typeOrder, array_keys($formatLabels))
            ->orderByRaw('CASE WHEN heure_debut IS NULL THEN 1 ELSE 0 END')
            ->orderBy('heure_debut')->orderBy('nom')->orderBy('id')
            ->paginate(12)->withQueryString();
        $emissionGroups = $emissions->getCollection()->groupBy(fn (Emission $emission) => $emission->type?->value ?? '');

        $joursOptions = ['lun' => 'Lundi', 'mar' => 'Mardi', 'mer' => 'Mercredi', 'jeu' => 'Jeudi', 'ven' => 'Vendredi', 'sam' => 'Samedi', 'dim' => 'Dimanche'];
        $jourActuel = array_keys($joursOptions)[now()->dayOfWeekIso - 1];
        $stats = [
            'total' => Emission::count(),
            'actives' => Emission::where('is_active', true)->count(),
            'aujourdhui' => Emission::where('is_active', true)
                ->whereNotIn('statut', [StatutEmissionEnum::Archivee->value, StatutEmissionEnum::Annulee->value])
                ->whereJsonContains('jours_diffusion', $jourActuel)->count(),
            'sans_horaire' => Emission::whereNull('heure_debut')->count(),
        ];
        $optionsTypes = $formatLabels;
        $optionsStatuts = StatutEmissionEnum::selectOptions();

        return view('emissions.index', compact(
            'emissions', 'emissionGroups', 'formatLabels', 'stats', 'q', 'filtreType', 'filtreStatut', 'filtreJour',
            'optionsTypes', 'optionsStatuts', 'joursOptions', 'jourActuel'
        ));
    }

    public function create(): View
    {
        $this->authorize('create', Emission::class);

        $emission = new Emission([
            'type' => TypeEmissionEnum::JT->value,
            'statut' => StatutEmissionEnum::Brouillon->value,
            'jours_diffusion' => [],
            'couleur_habillage' => '#0054cdFF',
            'police_titres' => 'Inter',
            'format_image' => '16:9',
            'qualite' => 'HD 1080p',
            'fuseau_horaire' => 'Africa/Libreville',
            'langues' => ['Français'],
            'is_brouillon' => true,
            'is_active' => true,
        ]);

        $chefsEdition = User::query()->where(fn ($query) => $query->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'chef_edition')))
            ->when($emission->chef_edition_id, fn ($query) => $query->orWhere('id', $emission->chef_edition_id))
            ->orderBy('name')->get(['id', 'name', 'is_active']);

        $presentateursDispos = Journaliste::presentateurs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'civilite', 'photo', 'email', 'specialites']);
        $optionsTypes = TypeEmissionEnum::selectOptions();
        $optionsStatuts = StatutEmissionEnum::selectOptions();
        $optionsRolesPresent = RolePresentateurEmissionEnum::selectOptions();
        $joursOptions = ['lun' => 'Lundi', 'mar' => 'Mardi', 'mer' => 'Mercredi', 'jeu' => 'Jeudi', 'ven' => 'Vendredi', 'sam' => 'Samedi', 'dim' => 'Dimanche'];

        return view('emissions.create', compact(
            'emission', 'presentateursDispos', 'optionsTypes', 'optionsStatuts', 'optionsRolesPresent', 'joursOptions', 'chefsEdition'
        ));
    }

    public function store(StoreEmissionRequest $request): RedirectResponse
    {
        $this->authorize('create', Emission::class);

        $data = $request->validated();
        if (empty($data['statut'] ?? null)) {
            $data['statut'] = StatutEmissionEnum::Brouillon->value;
        }

        $presentateurs = $data['presentateurs'] ?? [];
        unset($data['presentateurs']);

        /** @var Emission $emission */
        $emission = DB::transaction(function () use ($data, $presentateurs) {
            $emission = Emission::create($data);
            $ordre = 1;
            foreach ($presentateurs as $row) {
                $role = ! empty($row['role']) ? (RolePresentateurEmissionEnum::tryFrom($row['role']) ?? RolePresentateurEmissionEnum::Principal) : RolePresentateurEmissionEnum::Principal;
                $emission->ajouterPresentateur(
                    Journaliste::findOrFail((int) $row['journaliste_id']),
                    $role,
                    $row['statut_affectation'] ?? 'En_attente',
                    (int) ($row['ordre'] ?? $ordre)
                );
                $ordre++;
            }

            return $emission;
        });

        return redirect()
            ->route('emissions.show', $emission)
            ->with('status', "Émission « {$emission->nom} » créée avec succès.");
    }

    public function show(Emission $emission): View
    {
        $this->authorize('view', $emission);
        $emission->load(['presentateurs' => fn ($q) => $q->orderByPivot('ordre'), 'chefEdition', 'conducteurs', 'sequences']);

        return view('emissions.show', compact('emission'));
    }

    public function edit(Emission $emission): View
    {
        $this->authorize('update', $emission);

        $emission->load(['presentateurs' => fn ($q) => $q->orderByPivot('ordre')]);

        $chefsEdition = User::query()->where(fn ($query) => $query->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'chef_edition')))
            ->when($emission->chef_edition_id, fn ($query) => $query->orWhere('id', $emission->chef_edition_id))
            ->orderBy('name')->get(['id', 'name', 'is_active']);

        $presentateursDispos = Journaliste::presentateurs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'civilite', 'photo', 'email', 'specialites']);
        $optionsTypes = TypeEmissionEnum::selectOptions();
        $optionsStatuts = StatutEmissionEnum::selectOptions();
        $optionsRolesPresent = RolePresentateurEmissionEnum::selectOptions();
        $joursOptions = ['lun' => 'Lundi', 'mar' => 'Mardi', 'mer' => 'Mercredi', 'jeu' => 'Jeudi', 'ven' => 'Vendredi', 'sam' => 'Samedi', 'dim' => 'Dimanche'];

        return view('emissions.edit', compact(
            'emission', 'presentateursDispos', 'optionsTypes', 'optionsStatuts', 'optionsRolesPresent', 'joursOptions', 'chefsEdition'
        ));
    }

    public function update(UpdateEmissionRequest $request, Emission $emission): RedirectResponse
    {
        $this->authorize('update', $emission);

        $data = $request->validated();
        $syncPresentateurs = array_key_exists('presentateurs', $data);
        $presentateurs = $data['presentateurs'] ?? [];
        unset($data['presentateurs']);

        DB::transaction(function () use ($emission, $data, $presentateurs, $syncPresentateurs) {
            $emission->update($data);
            if (! $syncPresentateurs) {
                return;
            }

            $actualPresents = $emission->presentateurs->keyBy('id');
            $kept = [];
            $ordre = 1;
            foreach ($presentateurs as $row) {
                $jid = (int) $row['journaliste_id'];
                $role = ! empty($row['role']) ? (RolePresentateurEmissionEnum::tryFrom($row['role']) ?? RolePresentateurEmissionEnum::Principal) : RolePresentateurEmissionEnum::Principal;
                if (! isset($actualPresents[$jid])) {
                    $emission->ajouterPresentateur(
                        Journaliste::findOrFail($jid),
                        $role,
                        $row['statut_affectation'] ?? 'En_attente',
                        (int) ($row['ordre'] ?? $ordre)
                    );
                } else {
                    $emission->presentateurs()->updateExistingPivot($jid, [
                        'role' => $role->value,
                        'statut_affectation' => $row['statut_affectation'] ?? 'En_attente',
                        'ordre' => (int) ($row['ordre'] ?? $ordre),
                    ]);
                }
                $kept[] = $jid;
                $ordre++;
            }

            $retirer = $actualPresents->except($kept);
            foreach ($retirer as $supp) {
                $emission->retirerPresentateur($supp);
            }
        });

        return redirect()
            ->route('emissions.show', $emission)
            ->with('status', "Émission « {$emission->nom} » mise à jour.");
    }

    public function destroy(Emission $emission): RedirectResponse
    {
        $this->authorize('delete', $emission);
        $nom = $emission->nom;
        $emission->delete();

        return redirect()
            ->route('emissions.index')
            ->with('status', "Émission « {$nom} » supprimée.");
    }
}
