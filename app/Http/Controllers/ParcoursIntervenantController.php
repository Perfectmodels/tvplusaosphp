<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Models\Emission;
use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Services\InvitationService;
use App\Services\ParcoursIntervenantService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParcoursIntervenantController extends Controller
{
    /** 7 KPIs Header capture TV+A OS + filtres + tableau Timeline */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ParcoursIntervenant::class);
        $date = $request->input('date') ? rescue(static fn () => Carbon::parse($request->input('date')), today(), false) : today();
        $search = trim((string) $request->input('q', ''));
        $emissionId = $request->input('emission_id') ? (int) $request->input('emission_id') : null;
        $filtreEtape = $request->input('etape') ? rescue(static fn () => EtapeParcoursIntervenantEnum::from($request->input('etape')), null) : null;
        $tri = $request->input('tri', 'passage_prevue'); // passage_prevue | retard | nom

        // =============================================================
        // BASE QUERY avec filtre DATE
        // ⚠️ Un intervenant PROGRAMMÉ n'a pas encore de conducteur (il n'est
        //    rattaché qu'à son acceptation) : on filtre donc AUSSI sur
        //    heure_passage_prevue, sinon il disparaît de la liste.
        // =============================================================
        $baseQ = ParcoursIntervenant::query()
            ->with([
                'intervenant',
                'sequence.emission',
                'emission',
                'conducteur.emission',
                'pivotSequenceIntervenant',
                'triggeredBy',
            ])
            ->where(static function ($q) use ($date) {
                $q->whereDate('heure_passage_prevue', $date->toDateString())
                    ->orWhereHas('conducteur', static function ($cq) use ($date) {
                        $cq->whereDate('date_edition', $date->toDateString());
                    });
            })
            ->when($emissionId, static fn ($q) => $q->where(static fn ($sq) => $sq->where('emission_id', $emissionId)->orWhereHas('sequence', static fn ($s2) => $s2->where('emission_id', $emissionId))))
            ->when($search !== '', static function ($q) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $q->whereHas('intervenant', static function ($iq) use ($like) {
                    $iq->whereRaw('LOWER(COALESCE(prenom,\'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(nom,\'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(fonction,\'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(organisme,\'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(code_intervenant,\'\')) LIKE ?', [$like]);
                });
            })
            ->when($filtreEtape instanceof EtapeParcoursIntervenantEnum, static fn ($q) => $q->parEtape($filtreEtape));

        // =============================================================
        // CALCUL 7 KPIs (même base today, sans recherche/étapes pour global count)
        // =============================================================
        $kpiQ = ParcoursIntervenant::query()
            ->where(static function ($q) use ($date) {
                $q->whereDate('heure_passage_prevue', $date->toDateString())
                    ->orWhereHas('conducteur', static function ($cq) use ($date) {
                        $cq->whereDate('date_edition', $date->toDateString());
                    });
            })
            ->when($emissionId, static fn ($q) => $q->where(static fn ($sq) => $sq->where('emission_id', $emissionId)->orWhereHas('sequence', static fn ($s2) => $s2->where('emission_id', $emissionId))));

        $kpis = (object) [
            'total_jour' => (int) $kpiQ->count(),
            'confirmes' => (int) (clone $kpiQ)->whereNotNull('confirme_at')->count(),
            'en_chemin' => (int) (clone $kpiQ)->parEtape(EtapeParcoursIntervenantEnum::EnChemin)->count(),
            'arrives' => (int) (clone $kpiQ)->whereNotNull('arrive_accueil_at')->count(),
            'maquillage' => (int) (clone $kpiQ)->parEtape(EtapeParcoursIntervenantEnum::Maquillage)->count(),
            'plateau' => (int) (clone $kpiQ)->parEtape(EtapeParcoursIntervenantEnum::SurPlateau)->count(),
            'retards' => (int) (clone $kpiQ)->enRetard()->count(),
        ];

        // =============================================================
        // TRI + PAGINATION
        // =============================================================
        switch ($tri) {
            case 'retard':
                $rowsQ = $baseQ->orderByRaw('-retard_minutes DESC');
                break;
            case 'nom':
                $rowsQ = $baseQ->orderByRaw('(SELECT LOWER(CONCAT(COALESCE(prenom,\'\'),\' \',COALESCE(nom,\'\'))) FROM intervenants WHERE id=parcours_intervenants.intervenant_id) ASC');
                break;
            case 'passage_prevue':
            default:
                $rowsQ = $baseQ->orderByRaw('(
                    SELECT COALESCE(heure_passage_prevue, time_slot)
                    FROM sequence_intervenants
                    WHERE id=parcours_intervenants.sequence_intervenant_id
                    OR (sequence_id=parcours_intervenants.sequence_id AND intervenant_id=parcours_intervenants.intervenant_id)
                    LIMIT 1
                ) ASC');
                break;
        }

        /** @var LengthAwarePaginator $parcoursP */
        $parcoursP = $rowsQ->paginate($request->integer('par_page', 10))->withQueryString();

        // =============================================================
        // RETROACTIVITÉ on-the-fly : Si certains pivots ont de vieilles dates
        // mais la ligne parcours est à l'étape 1 → on réinjecte pour l'affichage
        // =============================================================
        $parcoursP->getCollection()->transform(function (ParcoursIntervenant $p) {
            $pivot = $p->pivotSequenceIntervenant;
            if ($pivot && $p->ordreActuel === 1) {
                try {
                    $hasAny = ($pivot->heure_arrive ?? $pivot->heure_maq_debut ?? $pivot->heure_plateau ?? null) !== null;
                    if ($hasAny) {
                        $pivot->retroActiverParcoursDepuisPivot(auth()->user());
                        $p->refresh();
                    }
                } catch (\Throwable) {
                }
            }

            return $p;
        });

        // =============================================================
        // Listes pour filtres
        // =============================================================
        $emissionsFiltre = Emission::query()
            ->select('id', 'nom')
            ->where('is_active', true)
            ->orderBy('nom')
            ->pluck('nom', 'id');

        $etapesFiltre = collect(EtapeParcoursIntervenantEnum::cases())
            ->mapWithKeys(static fn (EtapeParcoursIntervenantEnum $e) => [$e->value => $e->label()]);

        // =============================================================
        // Données du formulaire « Programmer un intervenant »
        // =============================================================
        $intervenantsProgrammables = Intervenant::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->get(['id', 'civilite', 'prenom', 'nom', 'fonction', 'organisme'])
            ->map(static function ($i) {
                $civ = '';
                try {
                    $civ = is_object($i->civilite) && method_exists($i->civilite, 'label')
                        ? $i->civilite->label()
                        : (string) ($i->civilite?->value ?? $i->civilite ?? '');
                } catch (\Throwable) {
                    $civ = '';
                }

                $fonction = '';
                try {
                    $f = $i->fonction;
                    $fonction = is_object($f) && method_exists($f, 'label') ? $f->label() : (string) ($f?->value ?? $f ?? '');
                } catch (\Throwable) {
                    $fonction = '';
                }

                return [
                    'id' => (int) $i->id,
                    'nom_complet' => trim($civ.' '.$i->prenom.' '.$i->nom) ?: ('Intervenant #'.$i->id),
                    'fonction' => $fonction !== '' ? $fonction : '—',
                    'organisme' => (string) ($i->organisme ?? '—'),
                ];
            })
            ->values();

        $emissionsProgrammables = Emission::query()
            ->where('is_active', true)
            ->orderBy('nom')
            ->get(['id', 'nom', 'slug', 'heure_debut'])
            ->map(static function ($e) {
                $heure = '—';
                try {
                    if ($e->heure_debut) {
                        $h = is_object($e->heure_debut) && method_exists($e->heure_debut, 'format')
                            ? $e->heure_debut->format('H:i')
                            : Carbon::parse((string) $e->heure_debut)->format('H:i');
                        $heure = $h ?: '—';
                    }
                } catch (\Throwable) {
                    $heure = '—';
                }

                return [
                    'id' => (int) $e->id,
                    'nom' => (string) $e->nom,
                    'heure' => $heure,
                ];
            })
            ->values();

        return view('parcours_intervenants.index', [
            'dateFiltre' => $date,
            'search' => $search,
            'emissionFiltre' => $emissionId,
            'etapeFiltre' => $filtreEtape,
            'tri' => $tri,
            'kpis' => $kpis,
            'parcours' => $parcoursP,
            'emissionsFiltre' => $emissionsFiltre,
            'etapesFiltre' => $etapesFiltre,
            'etapesTimeline' => EtapeParcoursIntervenantEnum::cases(),
            'intervenantsProgrammables' => $intervenantsProgrammables,
            'emissionsProgrammables' => $emissionsProgrammables,
        ]);
    }

    // =====================================================================
    //  WORKFLOW PARCOURS — VALIDATION MANUELLE PAR BOUTONS
    // =====================================================================

    /**
     * Les 8 étapes du parcours exposées à l'interface, dans l'ordre,
     * avec le libellé du bouton de validation.
     *
     * Chaque étape est confirmée en cliquant sur son bouton :
     * Invitation → Accepté → En chemin → Arrivé → En attente → Maquillage → Plateau → Terminé
     */
    public const ETAPES_UI = [
        ['value' => 'invitation_envoyee', 'label' => 'Invitation',    'bouton' => 'Invité',    'icone' => 'fa-envelope',              'couleur' => 'slate'],
        ['value' => 'confirme',           'label' => 'Confirmation',  'bouton' => 'Accepté',   'icone' => 'fa-check',                 'couleur' => 'emerald'],
        ['value' => 'en_chemin',          'label' => 'En chemin',     'bouton' => 'En chemin', 'icone' => 'fa-car',                   'couleur' => 'sky'],
        ['value' => 'arrive_accueil',     'label' => 'Arrivé',        'bouton' => 'Arrivé',    'icone' => 'fa-location-dot',          'couleur' => 'indigo'],
        ['value' => 'en_attente',         'label' => 'En attente',    'bouton' => 'En attente', 'icone' => 'fa-hourglass-half',        'couleur' => 'amber'],
        ['value' => 'en_maquillage',      'label' => 'En maquillage', 'bouton' => 'Maquillage', 'icone' => 'fa-paintbrush',            'couleur' => 'pink'],
        ['value' => 'sur_plateau',        'label' => 'Plateau',       'bouton' => 'Plateau',   'icone' => 'fa-tv',                    'couleur' => 'primary'],
        ['value' => 'diffuse_termine',    'label' => 'Terminé',       'bouton' => 'Terminé',   'icone' => 'fa-circle-check',          'couleur' => 'emerald'],
    ];

    /** Valeurs d'étape autorisées pour la validation manuelle. */
    private function etapesAutorisees(): array
    {
        return array_column(self::ETAPES_UI, 'value');
    }

    /**
     * Valider manuellement une étape du parcours (bouton Accepté / En chemin /
     * Arrivé / Maquillage / Plateau).
     */
    public function avancer(
        Request $request,
        ParcoursIntervenant $parcours,
        ParcoursIntervenantService $service,
    ): JsonResponse {
        $valid = $request->validate([
            'etape' => ['required', 'string', Rule::in($this->etapesAutorisees())],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'etape.required' => 'Étape manquante.',
            'etape.in' => 'Cette étape ne peut pas être validée manuellement.',
        ]);

        $etape = EtapeParcoursIntervenantEnum::from($valid['etape']);
        Gate::authorize('advance', [$parcours, $etape]);

        // ⛔ RÈGLE MÉTIER « SCAN OBLIGATOIRE » : au-delà de la confirmation de
        //    l'invitation, l'arrivée de l'invité est validée UNIQUEMENT par le
        //    scan de son QR à l'accueil (poste Accueil, utilisateur autorisé),
        //    et les étapes studio exigent ce scan au préalable.
        if (! $parcours->peutValiderManuellement($etape)) {
            return response()->json([
                'ok' => false,
                'message' => ParcoursIntervenant::messageScanRequis($etape),
            ], 422);
        }

        // ✅ RÈGLE MÉTIER : c'est à l'ACCEPTATION de l'invitation que l'intervenant
        //    est placé dans le conducteur (création du pivot sequence_intervenants).
        $placeDansConducteur = false;
        if ($etape === EtapeParcoursIntervenantEnum::Confirme && ! $parcours->sequence_intervenant_id) {
            try {
                $placeDansConducteur = $service->placerDansConducteur($parcours, $request->user());
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invitation acceptée, mais placement dans le conducteur impossible : '.$e->getMessage(),
                ], 500);
            }
        }

        try {
            $resultat = $parcours->avancerEtape(
                $etape,
                $request->user(),
                'bouton_web',
                $valid['notes'] ?? null
            );

            // Le pivot logistique est mis en miroir pour rester cohérent
            $service->synchroniserPivot($parcours, $etape, $request->user());

        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors de la validation : '.$e->getMessage(),
            ], 500);
        }

        $parcours->refresh();

        return response()->json([
            'ok' => true,
            'message' => sprintf(
                'Étape « %s » validée%s%s',
                $etape->labelCourt(),
                $placeDansConducteur ? ' — invité placé dans le conducteur' : '',
                ! empty($resultat['deja_fait']) ? ' (déjà fait)' : ''
            ),
            'deja_fait' => (bool) ($resultat['deja_fait'] ?? false),
            'place_dans_conducteur' => $placeDansConducteur,
            'rattache_conducteur' => (bool) $parcours->conducteur_id,
            'affecte_sequence' => (bool) $parcours->sequence_intervenant_id,
            'en_attente_affectation' => ! $parcours->sequence_intervenant_id,
            'parcours' => $this->payload($parcours),
        ]);
    }

    // =====================================================================
    //  PROGRAMMATION D'UN INTERVENANT
    // =====================================================================

    /**
     * Programmer un intervenant pour une ÉMISSION : il reçoit son invitation
     * mais n'est PAS encore dans le conducteur — il y sera placé à l'acceptation.
     */
    public function programmer(Request $request, InvitationService $invitationService): JsonResponse
    {
        Gate::authorize('create', ParcoursIntervenant::class);
        $valid = $request->validate([
            'intervenant_id' => ['required', 'integer', 'exists:intervenants,id'],
            'emission_id' => ['required', 'integer', 'exists:emissions,id'],
            'heure_passage' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'ouvrir_whatsapp' => ['sometimes', 'boolean'],
        ], [
            'intervenant_id.required' => 'Veuillez sélectionner un intervenant.',
            'intervenant_id.exists' => 'Cet intervenant n\'existe pas.',
            'emission_id.required' => 'Veuillez sélectionner une émission.',
            'emission_id.exists' => 'Cette émission n\'existe pas.',
            'heure_passage.required' => 'Veuillez indiquer l\'heure de passage.',
            'heure_passage.date' => 'L\'heure de passage est invalide.',
        ]);

        $intervenantId = (int) $valid['intervenant_id'];
        $emissionId = (int) $valid['emission_id'];
        $heurePassage = $valid['heure_passage'];

        // Une seule programmation par (intervenant, émission, heure)
        $exist = ParcoursIntervenant::where('intervenant_id', $intervenantId)
            ->where('emission_id', $emissionId)
            ->where('heure_passage_prevue', Carbon::parse($heurePassage))
            ->first();

        if ($exist) {
            return response()->json([
                'ok' => false,
                'message' => 'Cet intervenant est déjà programmé sur cette émission à cette heure.',
            ], 409);
        }

        $intervenant = Intervenant::findOrFail($intervenantId);
        $ouvrirWhatsApp = $request->boolean('ouvrir_whatsapp');
        if ($ouvrirWhatsApp && ! $invitationService->numeroWhatsApp($intervenant->telephone_1 ?: $intervenant->phone)) {
            throw ValidationException::withMessages([
                'intervenant_id' => 'Renseignez un numéro WhatsApp valide sur la fiche de cet intervenant avant de le booker.',
            ]);
        }

        [$parcours, $whatsAppUrl] = DB::transaction(function () use ($intervenantId, $emissionId, $heurePassage, $valid, $request, $ouvrirWhatsApp, $invitationService, $intervenant): array {
            $parcours = ParcoursIntervenant::create([
                'intervenant_id' => $intervenantId,
                'emission_id' => $emissionId,
                'sequence_id' => null,      // la séquence précise est rattachée plus tard
                'conducteur_id' => null,      // placé dans le conducteur à l'acceptation
                'sequence_intervenant_id' => null,      // ⚠️ pas encore dans le conducteur
                'heure_passage_prevue' => $heurePassage,
                // L'ouverture WhatsApp horodate l'invitation ; la confirmation reste distincte.
                'last_trigger_user_id' => $request->user()?->id,
                'last_trigger_medium' => 'bouton_web',
                'notes_logistique' => sprintf('[%s] bouton_web — intervenant programmé%s',
                    now()->format('d/m H:i'),
                    isset($valid['notes']) ? ' : '.$valid['notes'] : ''
                ),
            ]);

            $whatsAppUrl = $ouvrirWhatsApp ? $invitationService->lienWhatsApp($intervenant, $parcours) : null;
            if ($whatsAppUrl) {
                $invitationService->marquerEnvoi($parcours);
            }

            return [$parcours, $whatsAppUrl];
        });

        // ⚠️ PAS de pivot ici : l'intervenant n'est placé dans le conducteur
        //    qu'au moment de son ACCEPTATION (voir placerDansConducteur).

        return response()->json([
            'ok' => true,
            'message' => $whatsAppUrl
                ? 'Booking enregistré. Validez l’envoi du message dans WhatsApp.'
                : 'Intervenant programmé. Confirmez l\'invitation pour lancer son parcours.',
            'whatsapp_url' => $whatsAppUrl,
            'message_text' => $invitationService->message($intervenant, $parcours),
            'telephone' => $intervenant->telephone_1 ?: $intervenant->phone,
            'parcours' => $this->payload($parcours->fresh()),
        ]);
    }

    /**
     * Génère une suggestion de message WhatsApp via l'assistant IA.
     */
    public function suggestMessage(
        Request $request,
        ParcoursIntervenant $parcours,
        \App\Services\Ai\OpenCodeAssistant $assistant,
    ): JsonResponse {
        Gate::authorize('sendInvitation', $parcours);
        $intervenant = $parcours->intervenant;
        $emission = $parcours->emission;
        $angles = trim((string) $request->input('angles', ''));

        $prompt = "Tu es l'assistant de production de TV+ Afrique. "
            ."Rédige un message WhatsApp d'invitation pour un intervenant.\n\n"
            ."Intervenant : ".($intervenant?->nom_complet ?? "l'invité")."\n"
            ."Émission : ".($emission?->nom ?? 'notre émission')."\n"
            ."Date/heure de passage : ".($parcours->heure_passage_prevue?->format('d/m/Y à H:i') ?? 'à confirmer')."\n"
            ."Angles/thèmes souhaités : ".($angles !== '' ? $angles : 'non précisés')."\n\n"
            ."Le message doit être chaleureux, professionnel, en français, et inclure : "
            ."salutation personnalisée, présentation de l'émission, les angles/thèmes à aborder, "
            ."les détails pratiques (date, heure, lieu), et un appel à l'action pour confirmer. "
            ."Utilise le format WhatsApp avec *gras* pour les titres. "
            ."Propose 2-3 angles de discussion pertinents si non précisés. "
            ."Réponds uniquement avec le message, sans commentaire.";

        try {
            $suggestion = $assistant->answer($prompt);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'suggestion' => $suggestion]);
    }

    /**
     * Retourne le message WhatsApp pré-formaté pour un parcours.
     */
    public function messagePreview(
        ParcoursIntervenant $parcours,
        InvitationService $invitationService,
    ): JsonResponse {
        Gate::authorize('view', $parcours);
        $intervenant = $parcours->intervenant;
        abort_if(! $intervenant, 404);

        $message = $invitationService->message($intervenant, $parcours);
        $telephone = $intervenant->telephone_1 ?: $intervenant->phone;
        $numero = $invitationService->numeroWhatsApp($telephone);

        return response()->json([
            'ok' => true,
            'message' => $message,
            'telephone' => $telephone,
            'whatsapp_url' => $numero ? 'https://wa.me/'.$numero.'?text='.rawurlencode($message) : null,
        ]);
    }

    /**
     * Le formulaire HTML cible `_blank` : l'onglet reste sur l'application,
     * WhatsApp s'ouvre dans un nouvel onglet avec le message déjà rédigé.
     */
    public function envoyerWhatsApp(
        Request $request,
        ParcoursIntervenant $parcours,
        InvitationService $invitationService,
    ): RedirectResponse {
        Gate::authorize('sendInvitation', $parcours);
        $intervenant = $parcours->intervenant;
        abort_if(! $intervenant, 404);

        $lien = $invitationService->lienWhatsApp($intervenant, $parcours);
        if (! $lien) {
            return back()->with(
                'status',
                "Aucun numéro WhatsApp exploitable pour {$intervenant->nom_complet}. Renseignez son téléphone puis réessayez."
            );
        }

        // Ouvre la fenêtre de confirmation de 7 jours (un renvoi la relance).
        $invitationService->marquerEnvoi($parcours);

        return redirect()->away($lien);
    }

    public function regenererQr(ParcoursIntervenant $parcours): RedirectResponse
    {
        Gate::authorize('sendInvitation', $parcours);
        $this->authorize('qr', $parcours->intervenant);
        $parcours->regenererQrToken();

        return back()->with('status', 'QR d’accès et code manuel régénérés. Les précédents sont immédiatement révoqués.');
    }

    /**
     * Marquer l'intervenant comme « en retard » (bouton dédié).
     * Le retard est calculé par rapport à l'heure de passage prévue.
     */
    public function marquerRetard(Request $request, ParcoursIntervenant $parcours): JsonResponse
    {
        Gate::authorize('markDelay', $parcours);
        $valid = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Calcul du retard si non fourni
        $minutes = isset($valid['minutes']) ? (int) $valid['minutes'] : null;

        if ($minutes === null) {
            try {
                $pivot = $parcours->pivotSequenceIntervenant;
                $prevue = $pivot?->heure_passage_prevue;
                if (! $prevue && $pivot && $pivot->time_slot) {
                    $prevue = Carbon::parse($pivot->time_slot);
                }
                $minutes = $prevue ? (int) max(1, now()->diffInMinutes($prevue, false) * -1) : 15;
            } catch (\Throwable) {
                $minutes = 15;
            }
        }

        $parcours->retard_minutes = $minutes;
        $parcours->notes_logistique = trim(
            ($parcours->notes_logistique ? $parcours->notes_logistique."\n" : '')
            .sprintf('[%s] bouton_web — MARQUÉ EN RETARD (+%d min)%s',
                now()->format('d/m H:i'),
                $minutes,
                isset($valid['notes']) ? ' : '.$valid['notes'] : ''
            )
        );

        try {
            $parcours->save();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors du marquage du retard : '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => sprintf('Intervenant marqué en retard (+%d min).', $minutes),
            'parcours' => $this->payload($parcours->refresh()),
        ]);
    }

    /** Marquer l'intervenant comme absent (no-show). */
    public function marquerNoShow(Request $request, ParcoursIntervenant $parcours): JsonResponse
    {
        Gate::authorize('markNoShow', $parcours);
        $parcours->no_show = true;
        $parcours->notes_logistique = trim(
            ($parcours->notes_logistique ? $parcours->notes_logistique."\n" : '')
            .sprintf('[%s] bouton_web — NO-SHOW (absent)', now()->format('d/m H:i'))
        );

        try {
            $parcours->save();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Erreur lors du marquage no-show : '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Intervenant marqué absent (no-show).',
            'parcours' => $this->payload($parcours->refresh()),
        ]);
    }

    /** État courant du parcours (JSON) — sert à alimenter le modal de workflow. */
    public function etat(ParcoursIntervenant $parcours): JsonResponse
    {
        Gate::authorize('view', $parcours);
        return response()->json([
            'ok' => true,
            'parcours' => $this->payload($parcours),
        ]);
    }

    // =====================================================================
    //  HELPERS
    // =====================================================================

    /** État complet du parcours envoyé au client. */
    private function payload(ParcoursIntervenant $p): array
    {
        // Scan d'accueil effectué (= arrivée validée par le Scanner QR).
        $scanFait = $p->estEtapePassee(EtapeParcoursIntervenantEnum::ArriveSurSite);

        $etapes = [];
        foreach (self::ETAPES_UI as $def) {
            $enum = EtapeParcoursIntervenantEnum::from($def['value']);
            $col = $p->colonneEtape($enum);
            $date = $p->{$col};

            $etapes[] = [
                'value' => $def['value'],
                'label' => $def['label'],
                'bouton' => $def['bouton'],
                'icone' => $def['icone'],
                'couleur' => $def['couleur'],
                'fait' => $date !== null,
                'date' => $date?->toIso8601String(),
                'heure' => $date?->format('H:i'),
                'horodatage' => $date ? $date->format('d/m/Y').' · '.$date->format('H:i') : null,
                // ⛔ Scan obligatoire : étape studio tant que l'invité n'a pas
                //    été scanné à l'accueil (l'arrivée = le scan lui-même).
                'bloque_scan' => ! $scanFait
                    && $enum->ordre() >= EtapeParcoursIntervenantEnum::ArriveSurSite->ordre(),
                'can_valider' => auth()->user()?->can('advance', [$p, $enum]) ?? false,
            ];
        }

        // Première étape non faite = étape courante
        $courante = null;
        foreach ($etapes as $e) {
            if (! $e['fait']) {
                $courante = $e['value'];
                break;
            }
        }

        return [
            'id' => (int) $p->id,
            'can_retard' => auth()->user()?->can('markDelay', $p) ?? false,
            'can_no_show' => auth()->user()?->can('markNoShow', $p) ?? false,
            'can_scanner' => auth()->user()?->can('scan', Intervenant::class) ?? false,
            'rattache_conducteur' => (bool) $p->conducteur_id,
            'affecte_sequence' => (bool) $p->sequence_intervenant_id,
            'en_attente_affectation' => ! $p->sequence_intervenant_id,
            'statut_affectation' => $p->sequence_intervenant_id
                ? 'affecte_sequence'
                : ($p->conducteur_id ? 'conducteur_sans_sequence' : 'en_attente_conducteur'),
            'etapes' => $etapes,
            'etape_courante' => $courante,
            'etape_courante_label' => collect($etapes)->firstWhere('value', $courante)['label'] ?? 'Terminé',
            'scan_accueil_fait' => $scanFait,
            'retard_minutes' => (int) ($p->retard_minutes ?? 0),
            'en_retard' => (bool) $p->estEnRetard(),
            'no_show' => (bool) $p->no_show,
            'hors_conducteur' => ! $p->sequence_intervenant_id && ! $p->conducteur_id,
            'emission_nom' => (string) ($p->emission?->nom ?? $p->sequence?->emission?->nom ?? $p->conducteur?->emission?->nom ?? '—'),
            'heure_passage' => $p->heure_passage_prevue?->toIso8601String(),
            'heure_passage_hm' => $p->heure_passage_prevue?->format('H:i'),
            'notes_logistique' => (string) ($p->notes_logistique ?? ''),
            'intervenant' => [
                'id' => (int) $p->intervenant_id,
                'nom_complet' => (string) ($p->intervenant?->nom_complet ?? '—'),
            ],
        ];
    }
}
