<?php

namespace App\Services;

use App\Enums\StatutConducteurEnum;
use App\Enums\StatutEtapeWorkflowEnum;
use App\Models\ActivityLog;
use App\Models\Conducteur;
use App\Models\DigitalPublication;
use App\Models\EditorialEvent;
use App\Models\EditorialStory;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\Journaliste;
use App\Models\ParcoursIntervenant;
use App\Models\Sequence;
use App\Models\SourceVeille;
use App\Models\User;
use App\Models\VeilleSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class RoleDashboardService
{
    public function roles(User $user): array
    {
        if (! $user->is_active) {
            return [];
        }
        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            $preferred = $user->hasRole('admin') ? 'admin' : 'superadmin';
            $labels = config('workspaces.roles');

            return collect([$preferred => $labels[$preferred]] + $labels)->map(fn ($label, $role) => ['value' => $role, 'label' => $label])->values()->all();
        }
        $assigned = $user->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', 'dashboard.view'))
            ->pluck('name')
            ->all();

        return collect(config('workspaces.roles'))->filter(fn ($label, $role) => in_array($role, $assigned, true))
            ->map(fn ($label, $role) => ['value' => $role, 'label' => $label])->values()->all();
    }

    public function build(User $user, string $role, array $filters): array
    {
        $requiredReadPermissions = match ($role) {
            'journaliste', 'correspondant' => ['conducteurs.view', 'sequences.view', 'editorial.view'],
            'monteur', 'habilleur', 'realisateur', 'presentateur', 'chef_plateau', 'lecture' => ['conducteurs.view', 'sequences.view'],
            'chef_redaction', 'chef_edition' => ['conducteurs.view', 'sequences.view', 'editorial.view', 'digital.view'],
            'community_manager' => ['digital.view'],
            'directeur_general' => ['conducteurs.view', 'sequences.view', 'digital.view'],
            'admin', 'superadmin' => ['users.view', 'activity_logs.view', 'sources_veille.view'],
            'responsable_administratif' => ['journalistes.view'],
            'receptionniste' => ['intervenants.view'],
            default => [],
        };
        if (! $user->hasAllPermissions($requiredReadPermissions)) {
            return [
                'distribution' => [],
                'roleLabel' => config('workspaces.roles.'.$role),
                'scopeLabel' => 'Aucune donnée de lecture autorisée pour cet espace',
                'kpis' => [],
                'sections' => [$this->section('Accès aux données', [], 'Aucun module de lecture requis n’est activé pour cette fonction.')],
                'priorities' => [],
                'recentActivity' => [],
                'quickActions' => [],
                'workspaceNotices' => [],
                'workspaceFilters' => [...$filters, 'from' => $filters['from'] ?? today()->toDateString(), 'to' => $filters['to'] ?? ($filters['from'] ?? today()->toDateString())],
                'filterEmissions' => collect(),
                'filterDepartments' => collect(),
                'personalScope' => false,
            ];
        }

        $from = $filters['from'] ?? today()->toDateString();
        $to = $filters['to'] ?? $from;
        $administrator = $user->hasAnyRole(['admin', 'superadmin']);
        $journalisteId = $user->journalisteLie()?->id;
        $personal = ! $administrator && in_array($role, ['journaliste', 'correspondant', 'monteur'], true);
        $presenter = $role === 'presentateur';
        $conducteurs = Conducteur::query()->whereDate('date_edition', '>=', $from)->whereDate('date_edition', '<=', $to);
        if ($personal) {
            $conducteurs->whereHas('sequences', fn ($q) => $this->ownedSequences($q, $journalisteId));
        } elseif ($presenter) {
            if (! $administrator) {
                $conducteurs->where(fn ($c) => $c->where('presentateur_id', $journalisteId ?? 0)->orWhereHas('emission', fn ($e) => $e->where('presentateur_id', $journalisteId ?? 0)->orWhereHas('presentateurs', fn ($q) => $q->where('journalistes.id', $journalisteId ?? 0))));
            }
            $conducteurs->whereIn('type', ['emission', 'presentateur', 'presentation'])
                ->whereIn('status', [StatutConducteurEnum::Valide, StatutConducteurEnum::Transfere, StatutConducteurEnum::En_direct]);
        }
        if (in_array($role, ['realisateur', 'chef_plateau'], true)) {
            $conducteurs->whereIn('type', ['emission', 'presentateur', 'presentation']);
        }
        $emissionQuery = Emission::query();
        if ($role === 'community_manager' && ! $administrator) {
            $emissionQuery->whereIn(
                'id',
                DigitalPublication::query()
                    ->where('created_by', $user->id)
                    ->whereNotNull('emission_id')
                    ->select('emission_id')
            );
        }
        if ($personal || $presenter) {
            $emissionQuery->whereIn('id', (clone $conducteurs)->select('emission_id'));
        }
        $showProduction = ! in_array($role, ['admin', 'superadmin', 'responsable_administratif', 'documentaliste', 'community_manager'], true);
        $emissions = $showProduction || $role === 'community_manager' ? $emissionQuery->orderBy('nom')->get(['id', 'nom']) : collect();
        if (filled($filters['emission_id'] ?? null)) {
            abort_unless($emissions->contains('id', (int) $filters['emission_id']), 403);
            $conducteurs->where('emission_id', $filters['emission_id']);
        }
        $departments = in_array($role, ['admin', 'superadmin', 'directeur_general', 'responsable_administratif', 'chef_edition', 'chef_redaction'], true) ? User::whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department') : collect();
        $department = $filters['department'] ?? '';
        if ($department !== '') {
            abort_unless($departments->contains($department), 403);
            if (! in_array($role, ['admin', 'superadmin', 'responsable_administratif'], true)) {
                $conducteurs->whereHas('sequences.journalisteResponsable.user', fn ($u) => $u->where('department', $department));
            }
        }
        $q = trim($filters['q'] ?? '');
        $conducteurIds = (clone $conducteurs)->select('conducteurs.id');
        $sequences = Sequence::with(['conducteur.emission', 'journalisteResponsable'])->where('masque', false)->whereIn('conducteur_id', $conducteurIds);
        if ($personal) {
            $this->ownedSequences($sequences, $journalisteId);
        }
        if ($role === 'monteur') {
            $sequences->where('statut_terrain', StatutEtapeWorkflowEnum::Termine)->where('statut_montage', '!=', StatutEtapeWorkflowEnum::Termine);
        } elseif ($role === 'habilleur') {
            $sequences->where('statut_montage', StatutEtapeWorkflowEnum::Termine)->where('statut_habillage', '!=', StatutEtapeWorkflowEnum::Termine);
        }
        $backlogStories = EditorialStory::with('evenement')->whereDate('updated_at', '<=', $to);
        $stories = EditorialStory::with('evenement')->whereDate('updated_at', '>=', $from)->whereDate('updated_at', '<=', $to);
        if ($personal) {
            $stories->where('assigned_journaliste_id', $journalisteId ?? 0);
            $backlogStories->where('assigned_journaliste_id', $journalisteId ?? 0);
        }
        if (filled($filters['emission_id'] ?? null)) {
            $backlogStories->whereHas('sequences', fn ($query) => $query->whereHas('conducteur', fn ($c) => $c->where('emission_id', $filters['emission_id'])));
            $stories->whereHas('sequences', fn ($query) => $query->whereHas('conducteur', fn ($c) => $c->where('emission_id', $filters['emission_id'])));
        }
        foreach ([$sequences, $stories, $backlogStories] as $query) {
            $query->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.$q.'%'));
        }
        $conducteurs->when($q !== '', fn ($query) => $query->where(fn ($c) => $c->where('nom', 'like', '%'.$q.'%')->orWhereHas('emission', fn ($e) => $e->where('nom', 'like', '%'.$q.'%'))));
        if ($department !== '') {
            $stories->whereHas('journalisteAssigne.user', fn ($u) => $u->where('department', $department));
            $backlogStories->whereHas('journalisteAssigne.user', fn ($u) => $u->where('department', $department));
        }
        $status = $filters['status'] ?? '';
        if (in_array($status, ['draft', 'assigned', 'in_production', 'awaiting_validation', 'validated', 'rejected'], true)) {
            $stories->where('status', $status);
            $backlogStories->where('status', $status);
        }
        if (in_array($status, ['urgent', 'awaiting_validation', 'validated'], true)) {
            if ($status === 'urgent') {
                $sequences->where(fn ($s) => $s->where('alerte_info', true)->orWhere(fn ($s) => $s->where('heure_livraison_prevue', '<', now())->where('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine)));
            } else {
                $conducteurs->where('status', $status === 'validated' ? StatutConducteurEnum::Valide : StatutConducteurEnum::A_valider);
                $sequences->whereIn('conducteur_id', (clone $conducteurs)->select('conducteurs.id'));
            }
        }
        $kpis = [];
        $sections = [];
        $actions = [];
        $notices = [];
        $activity = [];
        $priorities = [];
        $distributionQuery = null;
        $distributionColumn = 'status';
        $scope = $personal ? 'Vos affectations uniquement' : ($presenter ? 'Vos émissions et leurs conducteurs validés uniquement' : 'Données accessibles pour cette fonction');
        if ($personal && ! $journalisteId) {
            $notices[] = 'Votre compte n’est pas lié à une fiche employé journaliste. Aucune affectation personnelle ne peut être affichée.';
        }
        if (in_array($role, ['journaliste', 'correspondant', 'monteur', 'habilleur'], true)) {
            $distributionQuery = clone $sequences;
            $distributionColumn = 'statut_livraison';
            $kpis = [$this->kpi('Sujets de conducteur', (clone $sequences)->count(), 'assignment'), $this->kpi('Livraisons en retard', (clone $sequences)->where('heure_livraison_prevue', '<', now())->where('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine)->count(), 'schedule')];
            $sections[] = $this->section($role === 'journaliste' ? 'Mes missions terrain et de production' : 'Mes missions de production', $this->sequenceRows($sequences), 'Aucune mission dans cette période et ces filtres.');
            if (in_array($role, ['journaliste', 'correspondant'], true)) {
                $kpis[] = $this->kpi('Sujets éditoriaux', (clone $stories)->count(), 'edit_document');
                $kpis[] = $this->kpi('Corrections à intégrer', (clone $backlogStories)->where('status', 'rejected')->count(), 'rate_review');
                $sections[] = $this->section('Mes sujets et retours de correction', $this->storyRows($stories), 'Aucun sujet éditorial affecté dans cette période.');
                $actions[] = $this->action($user, 'viewAny', Conducteur::class, 'conducteurs.redaction.index', 'Ouvrir mes conducteurs', 'edit_document');
                $actions[] = $this->action($user, 'viewAny', VeilleSubject::class, 'veille.sujets.index', 'Consulter la veille', 'travel_explore');
            }
            $priorities = $this->sequenceRows((clone $sequences)->where('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine)->where(fn ($s) => $s->where('alerte_info', true)->orWhere('heure_livraison_prevue', '<=', now()->addHours(2))));
            if (in_array($role, ['journaliste', 'correspondant'], true)) {
                $priorities = [...$this->storyRows((clone $backlogStories)->where('status', 'rejected')), ...$priorities];
            }
            $activity = $this->recentRows($sequences, 'sequence');
        } elseif (in_array($role, ['chef_redaction', 'chef_edition'], true)) {
            $distributionQuery = clone $stories;
            $events = EditorialEvent::withCount(['verifications' => fn ($v) => $v->where('status', 'pending')])->where('status', 'in_verification')->whereDate('updated_at', '>=', $from)->whereDate('updated_at', '<=', $to)->when($q !== '', fn ($e) => $e->where('title', 'like', '%'.$q.'%'));
            if (filled($filters['emission_id'] ?? null)) {
                $events->whereHas('sujets.sequences.conducteur', fn ($c) => $c->where('emission_id', $filters['emission_id']));
            }
            $waiting = (clone $stories)->where('status', 'awaiting_validation');
            $digital = DigitalPublication::query()->where('status', 'awaiting_validation')->whereDate('updated_at', '>=', $from)->whereDate('updated_at', '<=', $to)->when($q !== '', fn ($d) => $d->where('title', 'like', '%'.$q.'%'))->when(filled($filters['emission_id'] ?? null), fn ($d) => $d->where('emission_id', $filters['emission_id']));
            if ($department !== '') {
                $digital->whereHas('auteur', fn ($u) => $u->where('department', $department));
            }
            $eventRows = (clone $events)->latest('updated_at')->limit(8)->get()->map(fn ($e) => $this->row($e->title, $e->verifications_count.' vérification(s) en attente', 'En vérification', route('evenements-editoriaux.show', $e), $e->updated_at))->all();
            $pendingConducteurs = (clone $conducteurs)->where('status', StatutConducteurEnum::A_valider);
            if ($role === 'chef_redaction') {
                $kpis = [$this->kpi('Sujets à arbitrer', (clone $events)->count(), 'fact_check'), $this->kpi('Sujets à valider', (clone $waiting)->count(), 'rate_review'), $this->kpi('Conducteurs à valider', (clone $pendingConducteurs)->count(), 'view_list'), $this->kpi('Contenus numériques à valider', (clone $digital)->count(), 'public')];
                $priorities = [...$this->storyRows((clone $backlogStories)->where('status', 'awaiting_validation')), ...$this->publicationRows($digital)];
                $sections = [
                    $this->section('Sujets à arbitrer', $eventRows, 'Aucun sujet à arbitrer dans cette période.'),
                    $this->section('Sujets à valider', $this->storyRows($waiting), 'Aucun sujet en attente de validation.'),
                    $this->section('Conducteurs à valider', $this->conducteurRows($pendingConducteurs), 'Aucun conducteur en attente de validation.'),
                    $this->section('Contenus numériques à valider', $this->publicationRows($digital), 'Aucun contenu numérique en attente de validation.'),
                ];
                $actions = [
                    $this->action($user, 'viewAny', Conducteur::class, 'conducteurs.redaction.index', 'Ouvrir les sujets de rédaction', 'fact_check'),
                    $this->action($user, 'create', Conducteur::class, 'conducteurs.redaction.create', 'Préparer une édition de rédaction', 'add'),
                    $this->action($user, 'viewAny', DigitalPublication::class, 'digital.index', 'Contrôler les contenus numériques', 'public'),
                ];
                $notices[] = 'La création de conférences dédiées n’est pas encore disponible. Les éditions restent préparables depuis les conducteurs.';
            } else {
                $overdueSequences = (clone $sequences)->where('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine)->where('heure_livraison_prevue', '<', now());
                $kpis = [$this->kpi('Sujets à contrôler', (clone $waiting)->count(), 'rate_review'), $this->kpi('Conducteurs à valider', (clone $pendingConducteurs)->count(), 'view_list'), $this->kpi('Livraisons en retard', (clone $overdueSequences)->count(), 'schedule'), $this->kpi('Contenus numériques à valider', (clone $digital)->count(), 'public')];
                $priorities = [...$this->storyRows((clone $backlogStories)->where('status', 'awaiting_validation')), ...$this->sequenceRows($overdueSequences), ...$this->publicationRows($digital)];
                $sections = [
                    $this->section('Sujets et scripts à relire', $this->storyRows($waiting), 'Aucun sujet en attente de contrôle éditorial.'),
                    $this->section('Conducteurs à valider', $this->conducteurRows($pendingConducteurs), 'Aucun conducteur en attente de validation.'),
                    $this->section('Contenus numériques à valider', $this->publicationRows($digital), 'Aucun contenu numérique en attente de validation.'),
                ];
                $actions = [
                    $this->action($user, 'create', Conducteur::class, 'conducteurs.redaction.create', 'Préparer une édition de rédaction', 'add'),
                    $this->action($user, 'create', Conducteur::class, 'conducteurs.emission.create', 'Préparer un conducteur', 'description'),
                ];
            }
            $activity = [...$this->recentRows($stories, 'story'), ...$this->recentRows($conducteurs, 'conducteur')];
        } elseif ($role === 'realisateur') {
            $distributionQuery = clone $conducteurs;
            $conducteursToPrepare = (clone $conducteurs)->whereIn('status', [
                StatutConducteurEnum::Brouillon->value,
                StatutConducteurEnum::En_construction->value,
                StatutConducteurEnum::A_valider->value,
            ]);
            $sequencesToFollow = (clone $sequences)->where(fn ($s) => $s
                ->where('statut_terrain', '!=', StatutEtapeWorkflowEnum::Termine)
                ->orWhere('statut_montage', '!=', StatutEtapeWorkflowEnum::Termine)
                ->orWhere('statut_habillage', '!=', StatutEtapeWorkflowEnum::Termine)
                ->orWhere('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine)
                ->orWhere('statut_diffusion', '!=', StatutEtapeWorkflowEnum::Termine));
            $sequencesWithoutMedia = (clone $sequences)->where(fn ($s) => $s
                ->where(fn ($s) => $s->whereNull('video_url')->orWhere('video_url', ''))
                ->where(fn ($s) => $s->whereNull('live_url')->orWhere('live_url', '')));
            $kpis = [
                $this->kpi('Conducteurs à préparer', (clone $conducteursToPrepare)->count(), 'view_list'),
                $this->kpi('Séquences à suivre', (clone $sequencesToFollow)->count(), 'view_agenda'),
                $this->kpi('Médias à vérifier', (clone $sequencesWithoutMedia)->count(), 'video_library'),
                $this->kpi('Séquences urgentes', (clone $sequences)->where('alerte_info', true)->count(), 'warning'),
            ];
            $sections = [
                $this->section('Conducteurs et validation', $this->conducteurRows($conducteurs), 'Aucun conducteur disponible dans cette période.'),
                $this->section('Séquences, durées et médias', $this->realisationRows($sequences), 'Aucune séquence disponible dans cette période.'),
            ];
            $priorities = $this->realisationRows((clone $sequences)->where(fn ($s) => $s
                ->where('alerte_info', true)
                ->orWhere(fn ($s) => $s
                    ->where(fn ($s) => $s->whereNull('video_url')->orWhere('video_url', ''))
                    ->where(fn ($s) => $s->whereNull('live_url')->orWhere('live_url', '')))));
            $activity = $this->recentRows($conducteurs, 'conducteur');
            $actions[] = $this->action($user, 'viewAny', Conducteur::class, 'conducteurs.emission.index', 'Ouvrir les conducteurs d’émission', 'view_list');
            $notices[] = 'Les incidents techniques ne sont pas enregistrés séparément. Cette vue signale uniquement les urgences éditoriales et les médias manquants.';
        } elseif (in_array($role, ['presentateur', 'chef_plateau', 'lecture'], true)) {
            $distributionQuery = clone $conducteurs;
            $kpis = [$this->kpi('Conducteurs dans la période', (clone $conducteurs)->count(), 'view_list'), $this->kpi('Séquences prévues', (clone $sequences)->count(), 'view_agenda'), $this->kpi('En direct', (clone $conducteurs)->where('status', StatutConducteurEnum::En_direct)->count(), 'live_tv')];
            $sections = [$this->section($presenter ? 'Mes conducteurs validés' : 'Conducteurs et chronométrage', $this->conducteurRows($conducteurs), 'Aucun conducteur disponible dans votre périmètre pour cette période.')];
            $priorities = $this->sequenceRows((clone $sequences)->where('alerte_info', true));
            $activity = $this->recentRows($conducteurs, 'conducteur');
            $actions[] = $this->action($user, 'viewAny', Conducteur::class, 'conducteurs.emission.index', 'Consulter les conducteurs', 'view_list');
        } elseif ($role === 'community_manager') {
            $backlogPublications = DigitalPublication::query()->when(! $administrator, fn ($p) => $p->where('created_by', $user->id))->when($q !== '', fn ($p) => $p->where('title', 'like', '%'.$q.'%'))->when(filled($filters['emission_id'] ?? null), fn ($p) => $p->where('emission_id', $filters['emission_id']));
            $publications = (clone $backlogPublications)
                ->where(fn ($p) => $p->whereBetween('updated_at', [$from.' 00:00:00', $to.' 23:59:59'])->orWhereBetween('scheduled_at', [$from.' 00:00:00', $to.' 23:59:59'])->orWhereBetween('published_at', [$from.' 00:00:00', $to.' 23:59:59']))
                ->when($q !== '', fn ($p) => $p->where('title', 'like', '%'.$q.'%'))->when(filled($filters['emission_id'] ?? null), fn ($p) => $p->where('emission_id', $filters['emission_id']));
            if (in_array($status, array_keys(config('workspaces.digital_statuses')), true)) {
                $publications->where('status', $status);
                $backlogPublications->where('status', $status);
            }
            $distributionQuery = clone $publications;
            $kpis = [$this->kpi('À préparer / corriger', (clone $publications)->whereIn('status', ['draft', 'changes_requested'])->count(), 'edit_note'), $this->kpi('Validation attendue', (clone $publications)->where('status', 'awaiting_validation')->count(), 'rate_review'), $this->kpi('Planifiés', (clone $publications)->where('status', 'scheduled')->count(), 'event'), $this->kpi('Publications en retard', (clone $publications)->where('status', 'scheduled')->where('scheduled_at', '<', now())->count(), 'schedule')];
            $priorities = $this->publicationRows((clone $backlogPublications)->where(fn ($p) => $p->where('status', 'changes_requested')->orWhere(fn ($p) => $p->where('status', 'scheduled')->where('scheduled_at', '<=', now()->addHours(2)))));
            $platformStats = (clone $publications)
                ->reorder()
                ->select('platform')
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw("SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled")
                ->selectRaw("SUM(CASE WHEN status = 'scheduled' AND scheduled_at < ? THEN 1 ELSE 0 END) AS late", [now()])
                ->groupBy('platform')
                ->orderBy('platform')
                ->get();
            $platformRows = $platformStats->map(fn ($platform) => $this->row(
                config('workspaces.digital_platforms.'.$platform->platform, $platform->platform),
                sprintf('%d contenu(s) · %d planifié(s) · %d en retard', $platform->total, $platform->scheduled, $platform->late),
                (int) $platform->late > 0 ? 'Retard' : 'À jour',
                route('digital.index', ['platform' => $platform->platform])
            ))->all();
            $sections = [
                $this->section('Publications par plateforme', $platformRows, 'Aucune publication numérique dans cette période.'),
                $this->section('Mes contenus numériques', $this->publicationRows($publications), 'Aucun contenu numérique dans cette période.'),
            ];
            $activity = $this->recentRows($publications, 'publication');
            $actions = [$this->action($user, 'create', DigitalPublication::class, 'digital.create', 'Créer un contenu numérique', 'add'), $this->action($user, 'viewAny', DigitalPublication::class, 'digital.index', 'Suivre mes publications', 'public')];
            $kpis[] = $this->kpi('Publications réalisées', (clone $publications)->where('status', 'published')->count(), 'check_circle');
            $scope = 'Vos contenus numériques uniquement';
        } elseif ($role === 'directeur_general') {
            $distributionQuery = clone $conducteurs;
            $kpis = [$this->kpi('Productions programmées', (clone $conducteurs)->count(), 'view_list'), $this->kpi('Éditions clôturées', (clone $conducteurs)->where('status', StatutConducteurEnum::Cloture)->count(), 'check_circle'), $this->kpi('Livraisons en retard', (clone $sequences)->where('heure_livraison_prevue', '<', now())->where('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine)->count(), 'schedule'), $this->kpi('Publications réalisées', DigitalPublication::whereBetween('published_at', [$from.' 00:00:00', $to.' 23:59:59'])->when(filled($filters['emission_id'] ?? null), fn ($p) => $p->where('emission_id', $filters['emission_id']))->count(), 'public')];
            $sections = [$this->section('Productions de la période', $this->conducteurRows($conducteurs), 'Aucune production enregistrée dans cette période.')];
            $priorities = $this->sequenceRows((clone $sequences)->where('heure_livraison_prevue', '<', now())->where('statut_livraison', '!=', StatutEtapeWorkflowEnum::Termine));
            $activity = $this->recentRows($conducteurs, 'conducteur');
            $actions = [$this->action($user, 'viewAny', Conducteur::class, 'conducteurs.emission.index', 'Consulter les productions', 'bar_chart'), $this->action($user, 'viewAny', DigitalPublication::class, 'digital.index', 'Consulter les publications', 'public')];
            $notices[] = 'Les incidents et rapports par service ne disposent pas encore d’un registre dédié.';
        } elseif (in_array($role, ['admin', 'superadmin'], true)) {
            $conducteursDashboard = Conducteur::query()
                ->with(['emission'])
                ->whereDate('date_edition', '>=', $from)
                ->whereDate('date_edition', '<=', $to)
                ->when($q !== '', fn ($query) => $query->where(fn ($c) => $c->where('nom', 'like', '%'.$q.'%')->orWhereHas('emission', fn ($e) => $e->where('nom', 'like', '%'.$q.'%'))))
                ->when(filled($filters['emission_id'] ?? null), fn ($query) => $query->where('emission_id', $filters['emission_id']));

            $conducteursProduction = (clone $conducteursDashboard)->whereIn('type', ['emission', 'presentateur', 'presentation']);
            $conducteursRedaction = (clone $conducteursDashboard)->redaction();

            $emissionsDashboard = Emission::query()
                ->withCount(['conducteurs as conducteurs_redaction_count' => fn ($q) => $q->where('type', 'redaction')->whereDate('date_edition', '>=', $from)->whereDate('date_edition', '<=', $to)])
                ->withCount(['conducteurs as conducteurs_production_count' => fn ($q) => $q->whereIn('type', ['emission', 'presentateur', 'presentation'])->whereDate('date_edition', '>=', $from)->whereDate('date_edition', '<=', $to)])
                ->when($q !== '', fn ($query) => $query->where('nom', 'like', '%'.$q.'%'))
                ->when(filled($filters['emission_id'] ?? null), fn ($query) => $query->where('id', $filters['emission_id']))
                ->orderBy('nom');

            $veilleSujets = VeilleSubject::query()->with(['source']);
            $veilleRecent = (clone $veilleSujets)
                ->orderByDesc('scraped_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn ($s) => $this->row(
                    $s->title ?: '(Sujet sans titre)',
                    trim(($s->source?->name ?: 'Source inconnue').($s->summary ? ' · '.str($s->summary)->limit(90) : '')),
                    match ($s->status) {
                        'pending', 'draft' => 'À examiner',
                        'approved', 'validated' => 'Validé',
                        'rejected' => 'Rejeté',
                        'used', 'published' => 'Exploité',
                        default => $s->status ?: 'Non renseigné',
                    },
                    Gate::forUser($user)->allows('view', $s) ? route('veille.sujets.show', $s) : route('veille.sujets.index'),
                    $s->scraped_at ?? $s->updated_at ?? $s->created_at
                ))
                ->all();

            $veilleStats = [
                'total' => (clone $veilleSujets)->count(),
                'a_examiner' => (clone $veilleSujets)->whereIn('status', ['draft', 'pending'])->count(),
                'sources_actives' => SourceVeille::query()->where('is_active', true)->count(),
                'sources_erreur' => SourceVeille::query()->where('is_active', true)->where('status', 'error')->count(),
            ];

            $distributionQuery = clone $conducteursDashboard;
            $distributionColumn = 'type';

            $kpis = [
                $this->kpi('Émissions', (clone $emissionsDashboard)->count(), 'tv'),
                $this->kpi('Conducteurs production', (clone $conducteursProduction)->count(), 'view_list'),
                $this->kpi('Conducteurs rédaction', (clone $conducteursRedaction)->count(), 'edit_note'),
                $this->kpi('Veille à examiner', $veilleStats['a_examiner'], 'travel_explore'),
            ];

            $emissionRows = (clone $emissionsDashboard)->limit(8)->get()->map(fn ($e) => $this->row(
                $e->nom ?: 'Émission sans nom',
                sprintf('Rédactions : %d · Productions : %d', (int) $e->conducteurs_redaction_count, (int) $e->conducteurs_production_count),
                $e->is_active ? 'Active' : 'Inactive',
                Gate::forUser($user)->allows('view', $e) ? route('emissions.show', $e) : route('emissions.index'),
                $e->updated_at
            ))->all();

            $sections = [
                $this->section('Veille — sujets récents', $veilleRecent, 'Aucun sujet de veille pour le moment.'),
                $this->section('Production (conducteurs d’émission)', $this->conducteurRows($conducteursProduction), 'Aucun conducteur de production dans cette période.'),
                $this->section('Rédactions', $this->conducteurRows($conducteursRedaction), 'Aucun conducteur de rédaction dans cette période.'),
                $this->section('Émissions', $emissionRows, 'Aucune émission enregistrée.'),
            ];

            $priorities = (clone $veilleSujets)
                ->whereIn('status', ['draft', 'pending'])
                ->orderByDesc('priority')
                ->orderByDesc('scraped_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn ($s) => $this->row(
                    $s->title ?: '(Sujet sans titre)',
                    trim(($s->source?->name ?: 'Source inconnue').' · '.$this->veillePriorityLabel($s->priority)),
                    'À examiner',
                    Gate::forUser($user)->allows('view', $s) ? route('veille.sujets.show', $s) : route('veille.sujets.index'),
                    $s->scraped_at ?? $s->updated_at ?? $s->created_at
                ))
                ->all();

            $activity = ActivityLog::query()
                ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
                ->when($department !== '', fn ($a) => $a->whereHas('actor', fn ($u) => $u->where('department', $department)))
                ->whereNotNull('actor_id')
                ->whereNotNull('route_name')
                ->where(fn ($a) => $a->where('http_status', '>=', 200)->where('http_status', '<', 400))
                ->where(fn ($a) => $a
                    ->where('route_name', 'like', 'veille.%')
                    ->orWhere('route_name', 'like', 'conducteurs.%')
                    ->orWhere('route_name', 'like', 'emissions.%')
                    ->orWhere('route_name', 'like', 'journalistes.%')
                    ->orWhere('route_name', 'like', 'intervenants.%')
                    ->orWhere('route_name', 'like', 'parcours-intervenants.%')
                    ->orWhere('route_name', 'like', 'invitation.%')
                    ->orWhere('route_name', 'like', 'scanner-qr.%')
                    ->orWhere('route_name', 'like', 'users.%')
                    ->orWhere('route_name', 'like', 'digital.%')
                    ->orWhere('route_name', 'like', 'workspace.%')
                    ->orWhere('route_name', 'like', 'evenements-editoriaux.%')
                    ->orWhere('route_name', 'like', 'sujets-editoriaux.%'))
                ->where(function ($a): void {
                    foreach (['.index', '.show', '.create', '.edit', '.live-state', '.state', '.lookup'] as $suffix) {
                        $a->where('route_name', 'not like', '%'.$suffix);
                    }
                })
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn ($a) => $this->row(
                    $a->actor_name ?: 'Utilisateur',
                    $this->activityActionLabel($a->route_name, $a->http_method),
                    $this->activitySubjectLabel($a),
                    route('admin.activity-logs'),
                    $a->created_at
                ))
                ->unique(fn ($row) => ($row['title'] ?? '').'|'.($row['meta'] ?? '').'|'.($row['status'] ?? '').'|'.($row['url'] ?? ''))
                ->take(8)
                ->values()
                ->all();

            $actions = array_values(array_filter([
                $this->action($user, 'viewAny', VeilleSubject::class, 'veille.sujets.index', 'Ouvrir la veille', 'travel_explore'),
                $this->action($user, 'viewAny', Conducteur::class, 'conducteurs.emission.index', 'Voir la production', 'view_list'),
                $this->action($user, 'viewAny', Conducteur::class, 'conducteurs.redaction.index', 'Voir les rédactions', 'edit_note'),
                $this->action($user, 'viewAny', Emission::class, 'emissions.index', 'Voir les émissions', 'tv'),
                $this->action($user, 'viewAny', User::class, 'users.index', 'Gestion utilisateurs', 'manage_accounts'),
            ]));

            $notices[] = sprintf('Veille : %d sujet(s) au total · %d source(s) active(s) · %d source(s) en erreur.', $veilleStats['total'], $veilleStats['sources_actives'], $veilleStats['sources_erreur']);
            if ($role === 'superadmin') {
                $notices[] = 'Cette installation correspond à une seule organisation. Aucun espace de gestion multi-organisations n’est configuré.';
            }
            $scope = 'Vue production, rédactions, émissions et veille';
        } elseif ($role === 'responsable_administratif') {
            $staff = Journaliste::query()->when($department !== '', fn ($j) => $j->whereHas('user', fn ($u) => $u->where('department', $department)))->when($q !== '', fn ($j) => $j->where(fn ($s) => $s->where('nom', 'like', '%'.$q.'%')->orWhere('prenom', 'like', '%'.$q.'%')));
            $distributionQuery = clone $staff;
            $distributionColumn = 'is_active';
            $kpis = [$this->kpi('Employés enregistrés', (clone $staff)->count(), 'groups'), $this->kpi('Employés actifs', (clone $staff)->where('is_active', true)->count(), 'badge')];
            $sections = [$this->section('Équipe enregistrée', (clone $staff)->orderBy('nom')->limit(8)->get()->map(fn ($j) => $this->row($j->nomComplet, $j->matricule ?: 'Matricule non renseigné', $j->is_active ? 'Actif' : 'Inactif', route('journalistes.show', $j), $j->updated_at))->all(), 'Aucun employé enregistré.')];
            $actions[] = $this->action($user, 'viewAny', Journaliste::class, 'journalistes.index', 'Consulter l’équipe', 'groups');
            $notices[] = 'Les demandes d’absence, plannings d’équipe et dossiers administratifs ne disposent pas encore d’un module dédié.';
        } elseif ($role === 'documentaliste') {
            $notices[] = 'Aucun catalogue documentaire exploitable n’est configuré. Les demandes de recherche, métadonnées et droits d’utilisation restent à connecter.';
            $sections = [$this->section('Médias et demandes documentaires', [], 'Aucun média documentaire disponible dans cet espace.')];
        } elseif ($role === 'receptionniste') {
            $invites = ParcoursIntervenant::with(['intervenant', 'conducteur.emission'])->whereHas('conducteur', fn ($c) => $c->whereDate('date_edition', '>=', $from)->whereDate('date_edition', '<=', $to));
            if ($q !== '') {
                $invites->whereHas('intervenant', fn ($i) => $i->where('nom', 'like', '%'.$q.'%')->orWhere('prenom', 'like', '%'.$q.'%'));
            }
            $kpis = [$this->kpi('Invités attendus', (clone $invites)->count(), 'groups'), $this->kpi('Invitations confirmées', (clone $invites)->whereNotNull('confirme_at')->count(), 'check_circle')];
            $sections = [$this->section('Accueil des invités', (clone $invites)->orderBy('heure_passage_prevue')->limit(8)->get()->filter(fn ($i) => $i->intervenant !== null)->map(fn ($i) => $this->row($i->intervenant->nomComplet, $i->conducteur?->emission?->nom ?? 'Émission non renseignée', $i->confirme_at ? 'Confirmé' : 'Confirmation attendue', route('parcours-intervenants.index'), $i->updated_at))->all(), 'Aucun invité programmé dans cette période.')];
            $actions[] = $this->action($user, 'viewAny', Intervenant::class, 'parcours-intervenants.index', 'Ouvrir le suivi des invités', 'groups');
        }

        $distribution = [];
        if ($distributionQuery) {
            $labels = config('workspaces.digital_statuses') + ['assigned' => 'Affecté', 'in_production' => 'En production', 'validated' => 'Validé', 'rejected' => 'Correction demandée'];
            $colors = ['#2463c4', '#339c76', '#d78e32', '#9166c1', '#bd5667', '#528a9c'];
            $distribution = (clone $distributionQuery)->reorder()->toBase()->select($distributionColumn)->selectRaw('COUNT(*) AS total')->groupBy($distributionColumn)->get()->values()->map(function ($row, $index) use ($distributionColumn, $labels, $colors): array {
                $value = $row->{$distributionColumn};

                return ['label' => $distributionColumn === 'is_active' ? ($value ? 'Actifs' : 'Désactivés') : ($labels[$value] ?? $value ?? 'Non renseigné'), 'total' => (int) $row->total, 'color' => $colors[$index % count($colors)]];
            })->all();
        }

        if ($administrator) {
            $scope = 'Vue administrateur · Données de toute l’équipe';
        }

        return ['distribution' => $distribution, 'roleLabel' => config('workspaces.roles.'.$role), 'scopeLabel' => $scope, 'kpis' => $kpis, 'sections' => $sections, 'priorities' => array_slice($priorities, 0, 8), 'recentActivity' => collect($activity)->sortByDesc('sort')->take(8)->values()->all(), 'quickActions' => array_values(array_filter($actions)), 'workspaceNotices' => $notices, 'workspaceFilters' => [...$filters, 'from' => $from, 'to' => $to], 'filterEmissions' => $emissions, 'filterDepartments' => $departments, 'personalScope' => ! $administrator && ($personal || $presenter || $role === 'community_manager')];
    }

    private function ownedSequences(Builder $query, ?int $journalisteId): Builder
    {
        return $query->where(fn ($s) => $s->where('journaliste_responsable_id', $journalisteId ?? 0)->orWhere('journaliste_id', $journalisteId ?? 0));
    }

    private function kpi(string $label, int $value, string $icon): array
    {
        return compact('label', 'value', 'icon');
    }

    private function row(string $title, string $detail, string $status, string $href, ?Carbon $date = null): array
    {
        return compact('title', 'detail', 'status', 'href') + ['when' => $date?->format('d/m H:i'), 'sort' => $date?->timestamp ?? 0];
    }

    private function section(string $title, array $items, string $empty): array
    {
        return compact('title', 'items', 'empty');
    }

    private function sequenceRows(Builder $query): array
    {
        return (clone $query)->orderBy('heure_livraison_prevue')->orderBy('id')->limit(8)->get()->map(fn ($s) => $this->row($s->title ?: 'Sujet sans titre', ($s->conducteur?->emission?->nom ?: 'Émission non définie').($s->heure_livraison_prevue ? ' · Livraison '.$s->heure_livraison_prevue->format('d/m H:i') : ' · Échéance non renseignée'), $s->alerte_info ? 'Urgent' : ($s->statut_livraison?->value ?? 'Non renseigné'), in_array($s->conducteur?->type, ['emission', 'presentateur', 'presentation'], true) ? route('conducteurs.presentateur.show', $s->conducteur_id) : route('conducteurs.redaction.show', $s->conducteur_id), $s->updated_at))->all();
    }

    private function realisationRows(Builder $query): array
    {
        return (clone $query)->orderBy('position')->orderBy('id')->limit(8)->get()->map(function ($s) {
            $duration = max(0, (int) ($s->duration_seconds ?? 0));
            $mediaCount = collect([$s->video_url, $s->live_url])->filter(fn ($url) => filled($url))->count();
            $mediaLabel = $mediaCount > 0 ? $mediaCount.' lien(s) média' : 'Aucun lien média renseigné';
            $detail = ($s->conducteur?->emission?->nom ?: 'Émission non définie')
                .' · Séquence '.(int) $s->position
                .' · Durée prévue '.intdiv($duration, 60).':'.str_pad((string) ($duration % 60), 2, '0', STR_PAD_LEFT)
                .' · '.$mediaLabel;
            $href = in_array($s->conducteur?->type, ['emission', 'presentateur', 'presentation'], true)
                ? route('conducteurs.presentateur.show', $s->conducteur_id)
                : route('conducteurs.redaction.show', $s->conducteur_id);

            return $this->row(
                $s->title ?: 'Séquence '.$s->position,
                $detail,
                $s->alerte_info ? 'Urgent' : $this->workflowStatus($s),
                $href,
                $s->updated_at
            );
        })->all();
    }

    private function workflowStatus(Sequence $sequence): string
    {
        foreach ([
            'statut_terrain' => 'Terrain',
            'statut_montage' => 'Montage',
            'statut_habillage' => 'Habillage',
            'statut_livraison' => 'Livraison',
            'statut_diffusion' => 'Diffusion',
        ] as $field => $label) {
            $status = $sequence->{$field};
            if ($status !== StatutEtapeWorkflowEnum::Termine) {
                return $label.' · '.($status?->value ?? 'En attente');
            }
        }

        return 'Prête à diffuser';
    }

    private function storyRows(Builder $query): array
    {
        return (clone $query)->latest('updated_at')->latest('id')->limit(8)->get()->map(fn ($s) => $this->row($s->title, $s->rejection_reason ?: ($s->evenement?->title ?? 'Événement éditorial'), match ($s->status->value) {
            'awaiting_validation' => 'À valider', 'in_production' => 'En production', 'assigned' => 'Affecté', 'validated' => 'Validé', 'rejected' => 'Correction demandée', default => 'Brouillon'
        }, route('evenements-editoriaux.show', $s->editorial_event_id), $s->updated_at))->all();
    }

    private function conducteurRows(Builder $query): array
    {
        return (clone $query)->with('emission')->withCount('sequences')->orderBy('date_edition')->orderBy('id')->limit(8)->get()->map(fn ($c) => $this->row($c->nom ?: ($c->emission?->nom ?? 'Conducteur'), ($c->emission?->nom ?? 'Émission non définie').' · '.$c->date_edition->format('d/m/Y').' · '.$c->sequences_count.' séquence(s)', $c->status?->value ?? 'Brouillon', in_array($c->type, ['emission', 'presentateur', 'presentation'], true) ? route('conducteurs.presentateur.show', $c) : route('conducteurs.redaction.show', $c), $c->updated_at))->all();
    }

    private function publicationRows(Builder $query): array
    {
        return (clone $query)->orderBy('scheduled_at')->orderBy('id')->limit(8)->get()->map(fn ($p) => $this->row($p->title, config('workspaces.digital_platforms.'.$p->platform).($p->scheduled_at ? ' · '.$p->scheduled_at->format('d/m H:i') : '').($p->feedback ? ' · '.$p->feedback : ''), config('workspaces.digital_statuses.'.$p->status), route('digital.show', $p), $p->updated_at))->all();
    }

    private function recentRows(Builder $query, string $kind): array
    {
        $query = (clone $query)->reorder()->latest('updated_at')->latest('id');

        return match ($kind) {
            'sequence' => $this->sequenceRows($query), 'story' => $this->storyRows($query), 'publication' => $this->publicationRows($query), default => $this->conducteurRows($query)
        };
    }

    private function action(User $user, string $ability, string $model, string $route, string $label, string $icon): ?array
    {
        return Gate::forUser($user)->allows($ability, $model) ? ['href' => route($route), 'label' => $label, 'icon' => $icon] : null;
    }

    private function veillePriorityLabel(mixed $priority): string
    {
        $value = is_numeric($priority) ? (int) $priority : 0;

        return match (true) {
            $value >= 8 => 'Priorité haute',
            $value >= 5 => 'Priorité moyenne',
            $value > 0 => 'Priorité normale',
            default => 'Priorité non définie',
        };
    }

    private function activityActionLabel(?string $routeName, ?string $method): string
    {
        $routeName = (string) $routeName;
        $method = strtoupper((string) $method);

        $action = match (true) {
            str_ends_with($routeName, '.store'), str_ends_with($routeName, '.confirm') => 'Création / validation',
            str_ends_with($routeName, '.update'), str_ends_with($routeName, '.sujet.update') => 'Mise à jour',
            str_ends_with($routeName, '.destroy'), str_ends_with($routeName, '.sujet.destroy') => 'Suppression',
            str_ends_with($routeName, '.toggle_active') => 'Activation / désactivation',
            str_ends_with($routeName, '.reset_password') => 'Réinitialisation de mot de passe',
            str_ends_with($routeName, '.reorder'), str_ends_with($routeName, '.sujet.reorder') => 'Réorganisation',
            str_ends_with($routeName, '.assign') => 'Affectation',
            str_ends_with($routeName, '.cancel') => 'Annulation',
            default => match ($method) {
                'POST' => 'Action',
                'PUT', 'PATCH' => 'Mise à jour',
                'DELETE' => 'Suppression',
                default => 'Action utilisateur',
            },
        };

        $domain = match (true) {
            str_starts_with($routeName, 'veille.') => 'Veille',
            str_starts_with($routeName, 'conducteurs.') => 'Conducteur',
            str_starts_with($routeName, 'emissions.') => 'Émission',
            str_starts_with($routeName, 'journalistes.') => 'Journaliste',
            str_starts_with($routeName, 'intervenants.'), str_starts_with($routeName, 'parcours-intervenants.'), str_starts_with($routeName, 'invitation.') => 'Invité / parcours',
            str_starts_with($routeName, 'scanner-qr.') => 'Pointage QR',
            str_starts_with($routeName, 'users.') => 'Utilisateur',
            str_starts_with($routeName, 'digital.') => 'Publication numérique',
            str_starts_with($routeName, 'workspace.') => 'Espace partagé',
            str_starts_with($routeName, 'evenements-editoriaux.'), str_starts_with($routeName, 'sujets-editoriaux.') => 'Éditorial',
            default => 'Métier',
        };

        return $action.' · '.$domain;
    }

    private function activitySubjectLabel(ActivityLog $log): string
    {
        $type = (string) ($log->subject_type ?? '');
        $short = $type !== '' ? class_basename($type) : '';
        $labels = [
            'User' => 'Utilisateur',
            'Conducteur' => 'Conducteur',
            'Sequence' => 'Séquence',
            'Emission' => 'Émission',
            'Journaliste' => 'Journaliste',
            'Intervenant' => 'Intervenant',
            'ParcoursIntervenant' => 'Parcours invité',
            'VeilleSubject' => 'Sujet de veille',
            'DigitalPublication' => 'Publication numérique',
            'WorkspaceDossier' => 'Dossier partagé',
            'EditorialEvent' => 'Événement éditorial',
            'EditorialStory' => 'Sujet éditorial',
        ];
        $label = $labels[$short] ?? ($short !== '' ? $short : 'Action métier');

        return $log->subject_id ? $label.' #'.$log->subject_id : $label;
    }
}
