<?php

namespace App\Http\Controllers;

use App\Enums\PonctualitePresenceEnum;
use App\Enums\StatutConducteurEnum;
use App\Enums\StatutDispoIntervenantEnum;
use App\Enums\StatutEmissionEnum;
use App\Enums\StatutEtapeWorkflowEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\ConducteurPresence;
use App\Models\Conference;
use App\Models\Emission;
use App\Models\InscriptionPersonnel;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\User;
use App\Models\VeilleSubject;
use App\Services\RoleDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tableau de bord du chef d'édition — toutes les données sont calculées ici
 * (view-model) : la vue ne fait aucune requête et n'affiche aucune donnée
 * codée en dur.
 */
class DashboardController extends Controller
{
    private const ETAPES_WORKFLOW = [
        'statut_terrain' => 'Terrain',
        'statut_montage' => 'Montage',
        'statut_habillage' => 'Habillage',
        'statut_livraison' => 'Livraison',
        'statut_diffusion' => 'Diffusion',
    ];

    private const TYPES_HORS_SUJET = [
        TypeSequenceEnum::Ouverture,
        TypeSequenceEnum::Generique,
        TypeSequenceEnum::Titres,
        TypeSequenceEnum::Meteo,
        TypeSequenceEnum::Agenda,
        TypeSequenceEnum::Annonce,
        TypeSequenceEnum::Publicite,
        TypeSequenceEnum::Conclusion,
    ];

    public function index(Request $request, RoleDashboardService $workspaces): View
    {
        $user = $request->user();

        $dashboardRoles = $workspaces->roles($user);
        if ($dashboardRoles === []) {
            abort(403, 'Aucune fonction ne dispose d’un tableau de bord.');
        }

        $requestedRole = $request->query('role');
        abort_if($requestedRole !== null && ! is_string($requestedRole), 422, 'Fonction invalide.');
        if (is_string($requestedRole) && $requestedRole !== '') {
            if (! $this->hasDashboardRole($dashboardRoles, $requestedRole)) {
                abort(403, 'Cette fonction ne vous est pas attribuée.');
            }

            $activeDashboardRole = $requestedRole;
            $request->session()->put('dashboard.active_role', $activeDashboardRole);
        } else {
            $activeDashboardRole = (string) $request->session()->get('dashboard.active_role', '');
            if (! $this->hasDashboardRole($dashboardRoles, $activeDashboardRole)) {
                $activeDashboardRole = $dashboardRoles[0]['value'];
                $request->session()->put('dashboard.active_role', $activeDashboardRole);
            }
        }

        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'emission_id' => ['nullable', 'integer', 'exists:emissions,id'],
            'q' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['urgent', 'draft', 'assigned', 'in_production', 'awaiting_validation', 'validated', 'rejected', 'changes_requested', 'approved', 'scheduled', 'published'])],
        ]);
        $filters['from'] = $filters['from'] ?? today()->toDateString();
        $filters['to'] = $filters['to'] ?? $filters['from'];
        abort_if(Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['to'])) > 366, 422, 'Sélectionnez une période de 366 jours maximum.');
        $data = $workspaces->build($user, $activeDashboardRole, $filters) + [
            'dashboardRoles' => $dashboardRoles,
            'activeDashboardRole' => $activeDashboardRole,
            'pointageConference' => in_array($activeDashboardRole, ['admin', 'superadmin', 'chef_edition', 'chef_redaction', 'directeur_general', 'journaliste', 'lecture'], true) && $filters['from'] === today()->toDateString() && $filters['to'] === today()->toDateString() && ! filled($filters['q'] ?? null) && ! filled($filters['emission_id'] ?? null) && ! filled($filters['department'] ?? null) ? $this->bilanPointage() : null,
        ];
        $standardToday = $filters['from'] === today()->toDateString() && $filters['to'] === today()->toDateString()
            && ! filled($filters['emission_id'] ?? null) && ! filled($filters['q'] ?? null) && ! filled($filters['status'] ?? null) && ! filled($filters['department'] ?? null);
        if ($activeDashboardRole === 'chef_edition' && $standardToday) {
            return $this->dashboardChefEdition($dashboardRoles, $activeDashboardRole)->with($data);
        }

        $vueDediee = $this->vueTableauDeBord($activeDashboardRole);
        if ($vueDediee !== null) {
            $extras = $this->extrasTableauxDeBord($user, $activeDashboardRole);

            return view($vueDediee, $data + [
                'extrasTableauxDeBord' => $extras['blocs'],
                'tableauDeBordChiffres' => $extras['chiffres'],
            ]);
        }

        return view('dashboard_role', $data);
    }

    public function pointage(Request $request): JsonResponse
    {
        abort_unless($this->estAdministrateur($request->user()) || $this->dashboardRolesFor($request->user()) !== [], 403);
        Gate::authorize('viewAny', Conducteur::class);
        $pointageConference = $this->bilanPointage();

        return response()->json([
            'pointage' => $pointageConference,
            'html' => view('_partials.dashboard-conference-pointage', compact('pointageConference'))->render(),
        ])->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed>|null */
    private function bilanPointage(): ?array
    {
        if (Gate::denies('viewAny', Conducteur::class)) {
            return null;
        }
        $jour = today()->toDateString();
        $conferences = Conducteur::redaction()
            ->where(fn ($query) => $query->whereDate('date_edition', $jour)->orWhereDate('conference_started_at', $jour))
            ->with('presences.journaliste')
            ->orderByDesc('conference_started_at')->orderBy('nom')->orderBy('id')->get()
            ->map(function (Conducteur $conducteur): array {
                $aLHeure = $conducteur->presences->filter(fn (ConducteurPresence $presence) => $presence->ponctualite === PonctualitePresenceEnum::ALHeure)->count();
                $enRetard = $conducteur->presences->filter(fn (ConducteurPresence $presence) => $presence->ponctualite === PonctualitePresenceEnum::EnRetard)->count();

                return [
                    'id' => $conducteur->id,
                    'nom' => $conducteur->nom ?: 'Conférence de rédaction #'.$conducteur->id,
                    'edition' => $conducteur->date_edition?->format('d/m/Y'),
                    'debut' => $conducteur->conference_started_at?->format('d/m/Y à H:i'),
                    'href' => route('conducteurs.redaction.show', $conducteur).'#presences',
                    'total' => $conducteur->presences->count(),
                    'a_l_heure' => $aLHeure,
                    'en_retard' => $enRetard,
                    'a_pointer' => $conducteur->presences->count() - $aLHeure - $enRetard,
                    'personnes' => $conducteur->presences->map(fn (ConducteurPresence $presence) => $presence->pointagePayload())->values()->all(),
                ];
            });

        return [
            'jour' => $jour,
            'conferences' => $conferences->all(),
            'totaux' => [
                'conferences' => $conferences->count(),
                'presences' => $conferences->sum('total'),
                'a_l_heure' => $conferences->sum('a_l_heure'),
                'en_retard' => $conferences->sum('en_retard'),
                'a_pointer' => $conferences->sum('a_pointer'),
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function dashboardRolesFor(User $user): array
    {
        return (new RoleDashboardService)->roles($user);
    }

    private function estAdministrateur(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $roles
     */
    private function hasDashboardRole(array $roles, string $role): bool
    {
        return collect($roles)->contains(fn (array $item): bool => $item['value'] === $role);
    }

    public function presentateur(Request $request, RoleDashboardService $workspaces): View
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('presentateur'), 403);

        $roles = $this->dashboardRolesFor($user);

        return view('dashboard_role', $workspaces->build($user, 'presentateur', []) + ['dashboardRoles' => $roles, 'activeDashboardRole' => 'presentateur', 'pointageConference' => null]);
    }

    private function routeConducteur(Conducteur $conducteur): string
    {
        return in_array((string) $conducteur->type, ['emission', 'presentateur', 'presentation'], true)
            ? route('conducteurs.presentateur.show', $conducteur)
            : route('conducteurs.redaction.show', $conducteur);
    }

    /**
     * Vue dédiée du tableau de bord pour un rôle donné (null = vue générique).
     */
    private function vueTableauDeBord(string $role): ?string
    {
        return match ($role) {
            'admin', 'superadmin' => 'dashboards.admin',
            'directeur_general' => 'dashboards.direction',
            'chef_redaction' => 'dashboards.chef_redaction',
            'journaliste', 'correspondant' => 'dashboards.journaliste',
            'presentateur' => 'dashboards.presentateur',
            'monteur', 'habilleur', 'realisateur', 'chef_plateau', 'lecture' => 'dashboards.production',
            'community_manager' => 'dashboards.community',
            'documentaliste' => 'dashboards.documentaliste',
            'responsable_administratif' => 'dashboards.rh',
            'receptionniste' => 'dashboards.reception',
            default => null,
        };
    }

    /**
     * Blocs complémentaires des tableaux de bord dédiés : listes
     * (« blocs ») et compteurs du hero (« chiffres »), avec gardes
     * de permissions pour ne rien exposer au-delà du périmètre du rôle.
     *
     * @return array{blocs: array<int, array{title: string, items: array<int, array<string, mixed>>, empty: string}>, chiffres: array<string, int>}
     */
    private function extrasTableauxDeBord(User $user, string $role): array
    {
        return match ($role) {
            'admin', 'superadmin' => $this->extrasAdministration($user),
            'presentateur' => $this->extrasPresentateur($user),
            default => ['blocs' => [], 'chiffres' => []],
        };
    }

    private function extrasAdministration(User $user): array
    {
        $items = [];
        $chiffres = [];

        if ($user->can('users.view')) {
            $inscriptionsAttente = InscriptionPersonnel::query()->where('statut', InscriptionPersonnel::STATUT_PENDING)->count();
            $comptesActifs = User::query()->where('is_active', true)->count();
            $comptesTotal = User::query()->count();
            $chiffres += [
                'inscriptions_attente' => $inscriptionsAttente,
                'comptes_actifs' => $comptesActifs,
                'comptes_total' => $comptesTotal,
            ];
            $items[] = $this->ligneTableauDeBord(
                'Inscriptions du personnel',
                $inscriptionsAttente.' en attente · '.max(0, $comptesTotal - $comptesActifs).' compte(s) inactif(s)',
                $inscriptionsAttente > 0 ? 'Action requise' : 'À jour',
                route('personnel.inscriptions.index', ['statut' => 'pending'])
            );
            $items[] = $this->ligneTableauDeBord(
                'Comptes utilisateurs',
                $comptesActifs.' actif(s) sur '.$comptesTotal,
                'Gestion',
                route('users.index')
            );
        }

        if (Gate::forUser($user)->allows('viewAny', Conducteur::class)) {
            $conducteursJour = Conducteur::query()->whereDate('date_edition', today()->toDateString())->count();
            $chiffres['conducteurs_du_jour'] = $conducteursJour;
            $items[] = $this->ligneTableauDeBord(
                'Conducteurs du jour',
                $conducteursJour.' édition(s) au '.now()->translatedFormat('d F'),
                'Suivi',
                route('conducteurs.emission.index')
            );
        }

        return ['blocs' => [['title' => 'Supervision administration', 'items' => $items, 'empty' => 'Aucun élément d’administration accessible pour ce compte.']], 'chiffres' => $chiffres];
    }

    private function extrasPresentateur(User $user): array
    {
        $conducteurs = $this->conducteursPresentateurDuJour($user);
        $alertes = $conducteurs->isEmpty() ? collect() : Sequence::query()
            ->with(['conducteur.emission'])
            ->whereIn('conducteur_id', $conducteurs->modelKeys())
            ->where('alerte_info', true)
            ->where('masque', false)
            ->orderBy('position')
            ->orderBy('id')
            ->limit(8)
            ->get();

        $blocs = [
            [
                'title' => 'Ma feuille de route du jour',
                'items' => $conducteurs
                    ->map(fn (Conducteur $conducteur) => $this->ligneTableauDeBord(
                        $conducteur->nom ?: ($conducteur->emission?->nom ?? 'Conducteur'),
                        ($conducteur->emission?->nom ?? 'Émission non définie').' · '.$conducteur->sequences_count.' séquence(s)',
                        $conducteur->status?->value ?? 'Brouillon',
                        $this->routeConducteur($conducteur),
                        $conducteur->updated_at
                    ))
                    ->all(),
                'empty' => 'Aucun conducteur validé pour votre émission aujourd’hui.',
            ],
            [
                'title' => 'Alertes info actives',
                'items' => $alertes
                    ->map(fn (Sequence $sequence) => $this->ligneTableauDeBord(
                        '#'.($sequence->position ?? '—').' · '.($sequence->title ?: 'Séquence sans titre'),
                        $sequence->conducteur?->emission?->nom ?? 'Émission non définie',
                        'Alerte info',
                        $this->routeConducteur($sequence->conducteur),
                        $sequence->updated_at
                    ))
                    ->all(),
                'empty' => 'Aucune alerte info active aujourd’hui.',
            ],
        ];

        return ['blocs' => $blocs, 'chiffres' => [
            'conducteurs_du_jour' => $conducteurs->count(),
            'alertes_info' => $alertes->count(),
        ]];
    }

    /**
     * Conducteurs d'émission du jour dans le périmètre du présentateur
     * connecté (même résolution d'identité que RoleDashboardService).
     *
     * @return Collection<int, Conducteur>
     */
    private function conducteursPresentateurDuJour(User $user): Collection
    {
        $query = Conducteur::query()
            ->with(['emission', 'presentateur'])
            ->withCount('sequences')
            ->whereDate('date_edition', today()->toDateString())
            ->whereIn('type', ['emission', 'presentateur', 'presentation'])
            ->whereIn('status', [StatutConducteurEnum::Valide, StatutConducteurEnum::Transfere, StatutConducteurEnum::En_direct]);
        if (! $user->hasAnyRole(['admin', 'superadmin'])) {
            $presentateurId = $user->journalisteLie()?->id;
            $query->where(fn ($c) => $c->where('presentateur_id', $presentateurId ?? 0)
                ->orWhereHas('emission', fn ($e) => $e->where('presentateur_id', $presentateurId ?? 0)
                    ->orWhereHas('presentateurs', fn ($q) => $q->where('journalistes.id', $presentateurId ?? 0))));
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Ligne normalisée pour les listes du tableau de bord
     * (contrat attendu par le partial workspace-list).
     *
     * @return array<string, mixed>
     */
    private function ligneTableauDeBord(string $title, string $detail, string $status, string $href, ?Carbon $date = null): array
    {
        return ['title' => $title, 'detail' => $detail, 'status' => $status, 'href' => $href, 'when' => $date?->format('d/m H:i')];
    }

    private function dashboardChefEdition(array $dashboardRoles, string $activeDashboardRole): View
    {
        $aujourdhui = today();

        $conducteursDuJour = Conducteur::with(['emission.presentateurPrincipal', 'presentateur', 'sequences'])
            ->whereDate('date_edition', $aujourdhui->toDateString())
            ->get();

        $sequencesDuJour = Sequence::with(['emission', 'journaliste', 'journalisteResponsable', 'conducteur.emission'])
            ->whereHas('conducteur', fn ($q) => $q->whereDate('date_edition', $aujourdhui->toDateString()))
            ->where('masque', false)
            ->orderBy('position')
            ->get();

        $sujets = $sequencesDuJour->reject(
            fn (Sequence $s): bool => in_array($s->type, self::TYPES_HORS_SUJET, true)
        )->values();

        $sujetsPrets = $sujets->filter(fn (Sequence $s): bool => $this->estPrete($s));
        $sujetsEnMontage = $sujets->filter(
            fn (Sequence $s): bool => $s->statut_montage === StatutEtapeWorkflowEnum::En_cours
        )->count();

        $duplex = $sequencesDuJour->filter(fn (Sequence $s): bool => $s->type === TypeSequenceEnum::Duplex)->values();

        $parcoursDuJour = ParcoursIntervenant::today()
            ->with(['intervenant', 'conducteur'])
            ->get()
            ->filter(fn (ParcoursIntervenant $p): bool => $p->intervenant !== null)
            ->values();

        $invitesConfirmes = $parcoursDuJour->filter(fn (ParcoursIntervenant $p): bool => $p->confirme_at !== null)->count();

        $conferences = Conference::whereDate('scheduled_at', $aujourdhui->toDateString())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('scheduled_at')
            ->get();

        $echeances = $this->construireEcheances($conferences, $sequencesDuJour);

        $veilleRecente = VeilleSubject::with('source')
            ->orderByDesc('scraped_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $alertesInfo = $sequencesDuJour->filter(fn (Sequence $s): bool => (bool) $s->alerte_info)->values();

        $intervenantsEnRetard = $parcoursDuJour->filter(fn (ParcoursIntervenant $p): bool => $p->estEnRetard())->count();
        $veilleEnAttente = VeilleSubject::whereIn('status', ['draft', 'pending'])->count();
        $conducteursAValider = $conducteursDuJour->where('status', StatutConducteurEnum::A_valider)->count();
        $detailsAlertes = collect([
            $intervenantsEnRetard > 0 ? sprintf('%d invité(s) en retard', $intervenantsEnRetard) : null,
            $conducteursAValider > 0 ? sprintf('%d conducteur(s) à valider', $conducteursAValider) : null,
            $veilleEnAttente > 0 ? sprintf('%d sujet(s) de veille en attente', $veilleEnAttente) : null,
        ])->filter()->implode(' · ');

        return view('dashboard', [
            'pointageConference' => $this->bilanPointage(),
            'planningAujourdhui' => $conducteursDuJour->map(fn (Conducteur $c): array => $this->lignePlanning($c))->sortBy('tri')->values(),
            'suiviIntervenants' => $parcoursDuJour->sortByDesc('ordre_actuel')->values(),
            'alertesOperationnelles' => [
                'total' => $intervenantsEnRetard + $veilleEnAttente + $conducteursAValider,
                'details' => $detailsAlertes !== '' ? $detailsAlertes : 'Aucune action requise',
            ],
            'progression' => $conducteursDuJour->isNotEmpty()
                ? (int) round($conducteursDuJour->avg(fn (Conducteur $c): int => $c->progression_percent)) : 0,
            'totalEmissions' => Emission::count(),
            'emissionsDiffusees' => Emission::parStatut(StatutEmissionEnum::Diffusee)->count(),
            'intervenantsDispos' => Intervenant::where('disponibilite', StatutDispoIntervenantEnum::Disponible->value)->count(),
            'totalIntervenants' => Intervenant::count(),

            'dashboardRoles' => $dashboardRoles,
            'activeDashboardRole' => $activeDashboardRole,
            'nbSujetsPrevus' => $sujets->count(),
            'nbSujetsEnCours' => $sujets->count() - $sujetsPrets->count(),
            'nbSujetsPrets' => $sujetsPrets->count(),
            'nbSujetsEnMontage' => $sujetsEnMontage,
            'nbInvitesConfirmes' => $invitesConfirmes,
            'nbInvitesTotal' => $parcoursDuJour->count(),
            'nbDuplexPrevus' => $duplex->count(),
            'nbDuplexAPreparer' => $duplex->reject(
                fn (Sequence $s): bool => $s->statut_terrain === StatutEtapeWorkflowEnum::Termine
            )->count(),
            'nbEcheances' => $echeances->count(),
            'echeances' => $echeances->filter(fn (array $e): bool => $e['date']->isFuture())->take(5)->values(),
            'etatProduction' => $this->construireEtatProduction($sujets),
            'sujetsEnProduction' => $sujets
                ->reject(fn (Sequence $s): bool => $this->estPrete($s))
                ->take(5)
                ->map(fn (Sequence $s): array => [
                    'titre' => $s->title ?: 'Sujet sans titre',
                    'type' => $s->type?->value ?? 'SUJET',
                    'duplex' => $s->type === TypeSequenceEnum::Duplex,
                    'heure' => $s->heure_livraison_prevue?->format('H:i') ?? $s->start_time?->format('H:i') ?? '—',
                    'auteur' => $s->journalisteResponsable?->nomComplet ?? $s->journaliste?->nomComplet ?? 'Non affecté',
                    'etape' => $this->etapeCourante($s),
                    'avancement' => $this->pourcentageAvancement($s),
                    'emission' => $s->emission?->nom ?: '—',
                ])
                ->values(),
            'alertes' => $this->construireAlertes($alertesInfo, $veilleRecente),
            'journalistesTerrain' => $this->construireJrisTerrain($sujets),
            'emissionsDuJour' => $this->construireApercuEmissions($conducteursDuJour),
            'nbAlertesTotal' => $alertesInfo->count() + $veilleRecente->where('priority', '>=', 8)->count(),
        ]);
    }

    private function lignePlanning(Conducteur $c): array
    {
        $emission = $c->emission;
        $statut = $c->status instanceof StatutConducteurEnum
            ? $c->status
            : StatutConducteurEnum::Brouillon;

        return [
            'conducteur' => $c,
            'tri' => $this->heureCourte($emission?->heure_debut),
            'heure' => $this->heureCourte($emission?->heure_debut),
            'emission' => $emission?->nom ?: ($c->nom ?: 'Émission du jour'),
            'plateau' => $emission?->studio ?: '—',
            'presentateur' => $c->presentateur?->nomComplet
                ?? $emission?->presentateurPrincipal?->nomComplet
                ?? 'Équipe',
            'statut' => $statut,
            'badge' => $statut->badge(),
        ];
    }

    private function estPrete(Sequence $sequence): bool
    {
        return collect(array_keys(self::ETAPES_WORKFLOW))
            ->every(fn (string $champ): bool => $sequence->{$champ} === StatutEtapeWorkflowEnum::Termine);
    }

    private function etapeCourante(Sequence $sequence): string
    {
        foreach (self::ETAPES_WORKFLOW as $champ => $libelle) {
            if ($sequence->{$champ} !== StatutEtapeWorkflowEnum::Termine) {
                return $libelle;
            }
        }

        return 'Terminé';
    }

    private function pourcentageAvancement(Sequence $sequence): int
    {
        $termines = collect(array_keys(self::ETAPES_WORKFLOW))
            ->filter(fn (string $champ): bool => $sequence->{$champ} === StatutEtapeWorkflowEnum::Termine)
            ->count();

        return (int) round($termines / count(self::ETAPES_WORKFLOW) * 100);
    }

    /**
     * Répartition disjointe de chaque sujet : prêt, en production,
     * en attente (terrain terminé, étape suivante non lancée), rédaction (reste).
     *
     * @return array<int, array{libelle: string, total: int, couleur: string}>
     */
    private function construireEtatProduction(Collection $sujets): array
    {
        $compteurs = ['redaction' => 0, 'production' => 0, 'attente' => 0, 'pret' => 0];

        foreach ($sujets as $sujet) {
            if ($this->estPrete($sujet)) {
                $compteurs['pret']++;
            } elseif (collect(array_keys(self::ETAPES_WORKFLOW))->contains(
                fn (string $champ): bool => $sujet->{$champ} === StatutEtapeWorkflowEnum::En_cours
            )) {
                $compteurs['production']++;
            } elseif ($sujet->statut_terrain === StatutEtapeWorkflowEnum::Termine) {
                $compteurs['attente']++;
            } else {
                $compteurs['redaction']++;
            }
        }

        return [
            ['libelle' => 'Rédaction', 'total' => $compteurs['redaction'], 'couleur' => 'bg-primary-container', 'hex' => '#1d4ed8'],
            ['libelle' => 'En production', 'total' => $compteurs['production'], 'couleur' => 'bg-secondary-container', 'hex' => '#f97316'],
            ['libelle' => 'Prêts', 'total' => $compteurs['pret'], 'couleur' => 'bg-tertiary', 'hex' => '#16a34a'],
            ['libelle' => 'En attente', 'total' => $compteurs['attente'], 'couleur' => 'bg-error', 'hex' => '#dc2626'],
        ];
    }

    /**
     * @return Collection<int, array{date: Carbon, heure: string, titre: string, detail: string, urgent: bool}>
     */
    private function construireEcheances(Collection $conferences, Collection $sequences): Collection
    {
        $echeances = $conferences->map(fn (Conference $c): array => [
            'date' => $c->scheduled_at,
            'heure' => $c->scheduled_at->format('H:i'),
            'titre' => $c->title ?: 'Conférence de rédaction',
            'detail' => $c->location ?: 'Conférence',
            'urgent' => false,
        ]);

        $livraisons = $sequences
            ->filter(fn (Sequence $s): bool => $s->heure_livraison_prevue !== null
                && $s->statut_livraison !== StatutEtapeWorkflowEnum::Termine)
            ->map(fn (Sequence $s): array => [
                'date' => $s->heure_livraison_prevue,
                'heure' => $s->heure_livraison_prevue->format('H:i'),
                'titre' => 'Livraison — '.($s->title ?: 'Sujet sans titre'),
                'detail' => $s->emission?->nom ?: 'Émission du jour',
                'urgent' => false,
            ]);

        return $echeances->concat($livraisons)->sortBy('date')->values();
    }

    /**
     * @return array{alertesInfo: Collection<int, array<string, mixed>>, veille: Collection<int, array<string, mixed>>}
     */
    private function construireAlertes(Collection $alertesInfo, Collection $veille): array
    {
        return [
            'alertesInfo' => $alertesInfo->map(fn (Sequence $s): array => [
                'niveau' => 'Alerte info',
                'couleur' => 'text-error',
                'heure' => $s->start_time?->format('H:i') ?? '—',
                'titre' => $s->title ?: 'Alerte sans titre',
                'detail' => $s->summary ?: ($s->emission?->nom ?: ''),
                'badge' => 'Urgent',
                'badgeClasse' => 'bg-error/10 text-error',
            ])->values(),
            'veille' => $veille->map(fn (VeilleSubject $v): array => [
                'niveau' => $v->source?->name ?: 'Veille',
                'couleur' => $v->priority >= 8 ? 'text-error' : ($v->priority >= 5 ? 'text-secondary-container' : 'text-primary-container'),
                'point' => $v->priority >= 8 ? 'bg-error' : ($v->priority >= 5 ? 'bg-secondary-container' : 'bg-primary-container'),
                'id' => $v->id,
                'heure' => $v->scraped_at?->format('H:i') ?? '—',
                'titre' => $v->title ?: '(Sujet sans titre)',
                'detail' => $v->summary ?? '',
                'badge' => $v->priority >= 8 ? 'Urgent' : ($v->priority >= 5 ? 'À surveiller' : 'Info'),
                'badgeClasse' => $v->priority >= 8
                    ? 'bg-error/10 text-error'
                    : ($v->priority >= 5 ? 'bg-secondary-container/10 text-secondary-container' : 'bg-primary-container/10 text-primary-container'),
            ])->values(),
        ];
    }

    /**
     * Affectations enregistrées dans les conducteurs du jour, sans déduire de déplacement ni de direct.
     *
     * @return Collection<int, array{nom: string, titre: string, statut: string, couleur: string, emission: string, href: string}>
     */
    private function construireJrisTerrain(Collection $sequences): Collection
    {
        return $sequences
            ->filter(fn (Sequence $s): bool => ($s->journalisteResponsable ?? $s->journaliste) !== null)
            ->map(function (Sequence $s): array {
                $couleur = match ($s->statut_terrain) {
                    StatutEtapeWorkflowEnum::En_cours => 'text-primary-container',
                    StatutEtapeWorkflowEnum::Termine => 'text-tertiary',
                    default => 'text-on-surface-variant',
                };

                return [
                    'nom' => ($s->journalisteResponsable ?? $s->journaliste)->nomComplet,
                    'titre' => $s->title ?: 'Sujet sans titre',
                    'statut' => $s->statut_terrain?->label() ?? 'Non renseigné',
                    'couleur' => $couleur,
                    'emission' => $s->emission?->nom ?: ($s->conducteur->emission?->nom ?: 'Émission non renseignée'),
                    'href' => $this->routeConducteur($s->conducteur),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{emission: Emission, horaire: string, progression: int, statut: string, couleur: string}>
     */
    private function construireApercuEmissions(Collection $conducteurs): Collection
    {
        return $conducteurs
            ->filter(fn (Conducteur $c): bool => $c->emission !== null)
            ->groupBy('emission_id')
            ->map(function (Collection $groupe): array {
                $conducteur = $groupe->first();
                $emission = $conducteur->emission;
                $progression = (int) round($groupe->avg(fn (Conducteur $c): int => $c->progression_percent));

                [$statut, $couleur] = match ($conducteur->status) {
                    StatutConducteurEnum::En_direct => ['En direct', 'text-error'],
                    StatutConducteurEnum::Valide, StatutConducteurEnum::Transfere => ['Prêt', 'text-tertiary'],
                    StatutConducteurEnum::Cloture, StatutConducteurEnum::Archive => ['Clôturé', 'text-on-surface-variant'],
                    default => ['En préparation', 'text-secondary-container'],
                };

                return [
                    'emission' => $emission,
                    'horaire' => $this->heureCourte($emission->heure_debut).' – '.$this->heureCourte($emission->heure_fin),
                    'progression' => $progression,
                    'statut' => $statut,
                    'couleur' => $couleur,
                ];
            })
            ->values();
    }

    private function heureCourte(mixed $valeur): string
    {
        if ($valeur instanceof Carbon) {
            return $valeur->format('H:i');
        }

        if (is_string($valeur) && $valeur !== '') {
            return substr($valeur, 0, 5);
        }

        return '—';
    }
}
