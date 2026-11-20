<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EtapeParcoursIntervenantEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $sequence_intervenant_id
 * @property int $intervenant_id
 * @property int $sequence_id
 * @property int|null $conducteur_id
 * @property Carbon|null $heure_passage_prevue
 * @property Carbon|null $invitation_envoyee_at
 * @property Carbon|null $confirme_at
 * @property Carbon|null $qr_envoye_at
 * @property Carbon|null $en_chemin_at
 * @property Carbon|null $arrive_accueil_at
 * @property Carbon|null $en_maquillage_at
 * @property Carbon|null $pret_plateau_at
 * @property Carbon|null $sur_plateau_at
 * @property Carbon|null $diffuse_termine_at
 * @property string|null $qr_token
 * @property string|null $code_manuel
 * @property-read string $code_manuel_formate
 * @property string|null $confirme_medium
 * @property string|null $qr_medium
 * @property int|null $eta_arrive_minutes
 * @property int $retard_minutes
 * @property bool $no_show
 * @property bool $est_actif
 * @property string|null $notes_logistique
 * @property array|null $metadata
 * @property int|null $last_trigger_user_id
 * @property string|null $last_trigger_medium
 * @property-read Intervenant $intervenant
 * @property-read Sequence $sequence
 * @property-read Conducteur|null $conducteur
 * @property-read SequenceIntervenantPivot|null $pivotSequenceIntervenant
 * @property-read User|null $triggeredBy
 */
class ParcoursIntervenant extends Model
{
    use HasFactory;

    protected $table = 'parcours_intervenants';

    /**
     * Médias déclenchés par le système (scan QR à un poste, retro-remplissage
     * depuis les scans historiques du pivot) : ils court-circuitent la règle
     * « scan obligatoire » puisque ce sont eux qui portent le scan.
     *
     * @var array<int,string>
     */
    public const MEDIUMS_SYSTEME = ['scan_qr', 'retro_pivot'];

    protected $fillable = [
        'sequence_intervenant_id',
        'intervenant_id',
        'sequence_id',
        'conducteur_id',
        'emission_id',
        'qr_token',
        'code_manuel',
        'invitation_envoyee_at',
        'confirme_at',
        'qr_envoye_at',
        'en_chemin_at',
        'arrive_accueil_at',
        'en_attente_at',
        'en_maquillage_at',
        'pret_plateau_at',
        'sur_plateau_at',
        'diffuse_termine_at',
        'confirme_medium',
        'qr_medium',
        'confirmation_expires_at',
        'eta_arrive_minutes',
        'heure_passage_prevue',
        'retard_minutes',
        'no_show',
        'est_actif',
        'notes_logistique',
        'metadata',
        'last_trigger_user_id',
        'last_trigger_medium',
    ];

    protected $casts = [
        'invitation_envoyee_at' => 'datetime',
        'confirme_at' => 'datetime',
        'qr_envoye_at' => 'datetime',
        'en_chemin_at' => 'datetime',
        'arrive_accueil_at' => 'datetime',
        'en_attente_at' => 'datetime',
        'en_maquillage_at' => 'datetime',
        'pret_plateau_at' => 'datetime',
        'sur_plateau_at' => 'datetime',
        'diffuse_termine_at' => 'datetime',
        'confirmation_expires_at' => 'datetime',
        'eta_arrive_minutes' => 'integer',
        'emission_id' => 'integer',
        'heure_passage_prevue' => 'datetime',
        'retard_minutes' => 'integer',
        'no_show' => 'boolean',
        'est_actif' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'retard_minutes' => 0,
        'no_show' => false,
        'est_actif' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            if (blank($p->qr_token)) {
                do {
                    $p->qr_token = Str::random(48);
                } while (static::query()->where('qr_token', $p->qr_token)->exists());
            }

            if (blank($p->code_manuel)) {
                $p->code_manuel = static::genererCodeManuel();
            }

            // ⚠️ L'étape « Invitation » est confirmée MANUELLEMENT par le bouton
            //    dédié : on ne l'horodate plus automatiquement à la création.
            if ($p->sequence_id && ! $p->conducteur_id) {
                try {
                    $seq = Sequence::query()->select('conducteur_id')->find($p->sequence_id);
                    if ($seq) {
                        $p->conducteur_id = $seq->conducteur_id;
                    }
                } catch (\Throwable) {
                }
            }
        });
    }

    // =====================================================================
    // RELATIONS
    // =====================================================================

    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(Intervenant::class, 'intervenant_id');
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class, 'sequence_id');
    }

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class, 'conducteur_id');
    }

    /** Émission visée par la programmation (niveau ÉMISSION, sans séquence). */
    public function emission(): BelongsTo
    {
        return $this->belongsTo(Emission::class, 'emission_id');
    }

    public function pivotSequenceIntervenant(): BelongsTo
    {
        return $this->belongsTo(SequenceIntervenantPivot::class, 'sequence_intervenant_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_trigger_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ParcoursIntervenantEvent::class, 'parcours_intervenant_id')
            ->orderBy('recorded_at')
            ->orderBy('id');
    }

    public function urlQr(): string
    {
        try {
            return route('invitations.checkin-qr', ['token' => $this->qr_token], true);
        } catch (\Throwable) {
            return url('/i/checkin/'.$this->qr_token.'.png');
        }
    }

    /**
     * Code manuel à 6 chiffres, unique parmi tous les parcours.
     */
    public static function genererCodeManuel(): string
    {
        for ($tentative = 0; $tentative < 50; $tentative++) {
            $code = (string) random_int(100000, 999999);

            if (! static::query()->where('code_manuel', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Impossible de générer un code manuel unique.');
    }

    /** Code manuel lisible : « 482 913 ». */
    public function getCodeManuelFormateAttribute(): string
    {
        $code = (string) ($this->code_manuel ?? '');

        return preg_match('/^\d{6}$/', $code) === 1
            ? substr($code, 0, 3).' '.substr($code, 3)
            : $code;
    }

    public function regenererQrToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::query()->where('qr_token', $token)->exists());

        $this->forceFill([
            'qr_token' => $token,
            'code_manuel' => static::genererCodeManuel(),
        ])->save();

        return $token;
    }

    // =====================================================================
    // HELPERS WORKFLOW
    // =====================================================================

    /** Retourne la colonne timestamp pour une étape donnée */
    public function colonneEtape(EtapeParcoursIntervenantEnum $etape): string
    {
        return match ($etape) {
            EtapeParcoursIntervenantEnum::Invitation => 'invitation_envoyee_at',
            EtapeParcoursIntervenantEnum::PresenceConfirmee => 'confirme_at',
            EtapeParcoursIntervenantEnum::QrEnvoye => 'qr_envoye_at',
            EtapeParcoursIntervenantEnum::EnChemin => 'en_chemin_at',
            EtapeParcoursIntervenantEnum::ArriveSurSite => 'arrive_accueil_at',
            EtapeParcoursIntervenantEnum::EnAttente => 'en_attente_at',
            EtapeParcoursIntervenantEnum::EnPreparation => 'en_maquillage_at',
            EtapeParcoursIntervenantEnum::PretPlateau => 'pret_plateau_at',
            EtapeParcoursIntervenantEnum::EnPlateau => 'sur_plateau_at',
            EtapeParcoursIntervenantEnum::PassageTermine => 'diffuse_termine_at',
        };
    }

    public function estEtapePassee(EtapeParcoursIntervenantEnum $etape): bool
    {
        $col = $this->colonneEtape($etape);

        return $this->{$col} !== null;
    }

    /** Retourne l'étape ACTUELLE (dernière horodatage non null) */
    public function getEtapeActuelleAttribute(): EtapeParcoursIntervenantEnum
    {
        $maxOrdre = 0;
        $current = EtapeParcoursIntervenantEnum::Invitation;
        foreach (EtapeParcoursIntervenantEnum::cases() as $e) {
            if ($this->estEtapePassee($e) && $e->ordre() > $maxOrdre) {
                $maxOrdre = $e->ordre();
                $current = $e;
            }
        }

        return $current;
    }

    public function getEtapeSuivanteAttribute(): ?EtapeParcoursIntervenantEnum
    {
        foreach (EtapeParcoursIntervenantEnum::cases() as $etape) {
            if (! $this->estEtapePassee($etape)) {
                return $etape;
            }
        }

        return null;
    }

    public function getOrdreActuelAttribute(): int
    {
        return $this->etapeActuelle->ordre();
    }

    public function estEnRetard(): bool
    {
        if ($this->no_show) {
            return true;
        }
        if ($this->retard_minutes > 0) {
            return true;
        }

        return false;
    }

    // =====================================================================
    // CONFIRMATION PUBLIQUE (scan QR / lien WhatsApp)
    // =====================================================================

    /** L'invitation a-t-elle déjà été confirmée ? */
    public function estConfirmee(): bool
    {
        return $this->confirme_at !== null;
    }

    /** La fenêtre de 7 jours ouverte à l'envoi WhatsApp est-elle dépassée ? */
    public function estExpiree(): bool
    {
        return $this->confirmation_expires_at !== null
            && $this->confirmation_expires_at->isPast();
    }

    /** L'intervenant peut-il encore confirmer cette invitation depuis son téléphone ? */
    public function estConfirmable(): bool
    {
        return $this->est_actif
            && ! $this->no_show
            && ! $this->estConfirmee()
            && ! $this->estExpiree();
    }

    /**
     * Règle « SCAN OBLIGATOIRE » : au-delà de la confirmation d'invitation,
     * l'arrivée de l'invité est validée UNIQUEMENT par le scan de son QR à
     * l'accueil (poste « Accueil » du Scanner QR, par un utilisateur autorisé).
     * Les étapes studio suivantes restent manuelles mais exigent ce scan
     * au préalable.
     */
    public function peutValiderManuellement(EtapeParcoursIntervenantEnum $etape): bool
    {
        // Invitation / Confirmation / En chemin : validation manuelle normale.
        if ($etape->ordre() <= EtapeParcoursIntervenantEnum::EnChemin->ordre()) {
            return true;
        }

        // « Arrivé » : le scan du QR est la seule voie de validation.
        if ($etape === EtapeParcoursIntervenantEnum::ArriveSurSite) {
            return false;
        }

        // Étapes studio : accessibles manuellement seulement APRÈS le scan d'accueil.
        return $this->estEtapePassee(EtapeParcoursIntervenantEnum::ArriveSurSite);
    }

    /** Message bloquant pour une étape exigeant le scan du QR. */
    public static function messageScanRequis(EtapeParcoursIntervenantEnum $etape): string
    {
        return $etape === EtapeParcoursIntervenantEnum::ArriveSurSite
            ? 'Scan QR obligatoire : validez l’arrivée en scannant le QR de l’invité au poste « Accueil » du Scanner QR (utilisateur autorisé).'
            : 'Scan QR obligatoire : l’invité doit d’abord être scanné à l’accueil avant de valider les étapes studio.';
    }

    /**
     * Faire progresser une étape. Ignore les étapes antérieures déjà passées.
     *
     * @return array{etape:EtapeParcoursIntervenantEnum, date:Carbon, deja_fait:bool}
     */
    public function avancerEtape(
        EtapeParcoursIntervenantEnum $etape,
        ?User $triggeredBy = null,
        ?string $medium = null,
        ?string $notes = null,
        ?Carbon $dateForcee = null,
    ): array {
        return DB::transaction(function () use ($etape, $triggeredBy, $medium, $notes, $dateForcee): array {
            $parcours = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $col = $parcours->colonneEtape($etape);
            $event = ParcoursIntervenantEvent::query()
                ->where('parcours_intervenant_id', $parcours->id)
                ->where('etape', $etape->value)
                ->first();
            $timestampExistant = $parcours->{$col};
            $dejaFait = $event !== null || $timestampExistant !== null;

            // ⛔ SCAN OBLIGATOIRE : au-delà de la confirmation d'invitation,
            //    l'arrivée est validée uniquement par le scan du QR (poste
            //    Accueil) et les étapes studio exigent ce scan au préalable.
            //    Les médias système (scan_qr, retro_pivot) portent le scan
            //    lui-même et ne sont donc pas bloqués.
            if (! $dejaFait
                && ! in_array($medium, self::MEDIUMS_SYSTEME, true)
                && ! $parcours->peutValiderManuellement($etape)
            ) {
                throw new \RuntimeException(self::messageScanRequis($etape));
            }

            $date = $event?->recorded_at ?? $timestampExistant ?? $dateForcee ?? now();

            if (! $event) {
                ParcoursIntervenantEvent::create([
                    'parcours_intervenant_id' => $parcours->id,
                    'etape' => $etape->value,
                    'recorded_at' => $date,
                    'triggered_by_user_id' => $dejaFait ? null : $triggeredBy?->id,
                    'source' => $dejaFait ? 'legacy' : Str::limit($medium ?? 'manuel', 32, ''),
                    'notes' => $dejaFait ? null : $notes,
                ]);
            }

            if ($timestampExistant === null) {
                $parcours->{$col} = $date;
            }

            if (! $dejaFait) {
                if ($triggeredBy) {
                    $parcours->last_trigger_user_id = $triggeredBy->id;
                }
                if ($medium) {
                    $parcours->last_trigger_medium = $medium;
                }
                if ($notes) {
                    $prev = $parcours->notes_logistique ? ($parcours->notes_logistique."\n") : '';
                    $parcours->notes_logistique = $prev.sprintf(
                        '[%s] %s — étape %s : %s',
                        $date->format('d/m H:i'),
                        $medium ?? 'manuel',
                        $etape->labelCourt(),
                        $notes
                    );
                }
                if ($etape === EtapeParcoursIntervenantEnum::PresenceConfirmee && $medium) {
                    $parcours->confirme_medium = $medium;
                }

                $parcours->calculerRetard();
            }

            $parcours->save();
            $this->setRawAttributes($parcours->getAttributes(), true);

            return [
                'etape' => $etape,
                'date' => $date,
                'deja_fait' => $dejaFait,
            ];
        });
    }

    public function calculerRetard(): void
    {
        try {
            if (! $this->pivotSequenceIntervenant && $this->sequence_intervenant_id) {
                $this->loadMissing('pivotSequenceIntervenant');
            }
            $prevue = optional($this->pivotSequenceIntervenant)->heure_passage_prevue
                ?? optional($this->pivotSequenceIntervenant)->time_slot;
            if (! $prevue) {
                return;
            }

            $heurePrevue = Carbon::parse($prevue);
            $ref = $this->arrive_accueil_at ?? now();
            $diff = $ref->diffInMinutes($heurePrevue, false);
            if ($diff < 0) {
                $this->retard_minutes = abs((int) $diff);
            } else {
                $this->retard_minutes = min(0, (int) $diff * -1) < 0 ? 0 : 0; // en avance = retard 0
            }
        } catch (\Throwable) {
        }
    }

    // =====================================================================
    // SCOPES FILTRES
    // =====================================================================

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereHas('conducteur', static function ($cq) {
            $cq->whereDate('date_edition', today()->toDateString());
        });
    }

    public function scopeParEtape(Builder $q, EtapeParcoursIntervenantEnum $e): Builder
    {
        $col = $this->colonneEtape($e);
        $suiv = $e->etatParDefautApres();
        $colSuiv = $suiv ? $this->colonneEtape($suiv) : null;

        return $q->whereNotNull($col)
            ->when($colSuiv, static fn ($sq) => $sq->whereNull($colSuiv));
    }

    public function scopeParEmission(Builder $q, int $emissionId): Builder
    {
        return $q->whereHas('sequence', static fn ($sq) => $sq->where('emission_id', $emissionId));
    }

    public function scopeParConducteur(Builder $q, int $conducteurId): Builder
    {
        return $q->where('conducteur_id', $conducteurId);
    }

    public function scopeEnRetard(Builder $q): Builder
    {
        return $q->where(static function ($qq) {
            $qq->where('no_show', true)
                ->orWhere('retard_minutes', '>', 0);
        });
    }

    public function scopeConfirmer(Builder $q): Builder
    {
        return $q->whereNotNull('confirme_at');
    }

    // =====================================================================
    // TIMELINE UI
    // =====================================================================

    /**
     * @return array<int,array{
     *     etape:EtapeParcoursIntervenantEnum,
     *     statut:string,
     *     date:null|string,
     *     date_complete:null|string,
     *     date_iso:null|string,
     *     heure:null|string,
     *     horodatage:null|string
     * }>
     *         statut : 'fait' | 'actif' | 'futur'
     */
    public function getTimelineUiAttribute(): array
    {
        // Première étape non encore horodatée = étape en cours de confirmation.
        $premiereNonFaite = null;
        foreach (EtapeParcoursIntervenantEnum::cases() as $e) {
            if (! $this->estEtapePassee($e)) {
                $premiereNonFaite = $e;
                break;
            }
        }

        $out = [];
        foreach (EtapeParcoursIntervenantEnum::cases() as $e) {
            $col = $this->colonneEtape($e);
            $dateVal = $this->{$col};

            // ⚠️ L'état doit refléter l'horodatage RÉEL : une étape est « faite »
            //    dès qu'elle est validée, pas parce qu'elle précède l'étape courante.
            if ($dateVal !== null) {
                $statut = 'fait';
            } elseif ($premiereNonFaite === $e) {
                $statut = 'actif';
            } else {
                $statut = 'futur';
            }

            $dateStr = $dateCompleteStr = $dateIso = $heureStr = $horodatage = null;
            if ($dateVal instanceof Carbon) {
                $dateStr = $dateVal->format('d/m');
                $dateCompleteStr = $dateVal->format('d/m/Y');
                $dateIso = $dateVal->toIso8601String();
                $heureStr = $dateVal->format('H:i');
                $horodatage = $dateCompleteStr.' · '.$heureStr;
            } elseif (is_string($dateVal) && $dateVal !== '') {
                try {
                    $d = Carbon::parse($dateVal);
                    $dateStr = $d->format('d/m');
                    $dateCompleteStr = $d->format('d/m/Y');
                    $dateIso = $d->toIso8601String();
                    $heureStr = $d->format('H:i');
                    $horodatage = $dateCompleteStr.' · '.$heureStr;
                } catch (\Throwable) {
                }
            }

            $out[] = [
                'etape' => $e,
                'statut' => $statut,
                'date' => $dateStr,
                'date_complete' => $dateCompleteStr,
                'date_iso' => $dateIso,
                'heure' => $heureStr,
                'horodatage' => $horodatage,
            ];
        }

        return $out;
    }

    /** Badge de statut complet pour la vue liste (badge droit dans la capture) */
    protected function badgeStatutActuel(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $etape = $this->etapeActuelle;
                $retard = $this->estEnRetard();

                $baseCls = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold tracking-wide border ';
                if ($retard) {
                    $cls = 'bg-error/10 text-error border-error/20';
                    $label = 'En retard';
                } else {
                    $cls = match (true) {
                        in_array($etape, [EtapeParcoursIntervenantEnum::EnPlateau, EtapeParcoursIntervenantEnum::PassageTermine], true) => 'bg-success/10 text-success border-success/20',
                        $etape === EtapeParcoursIntervenantEnum::EnPreparation => 'bg-purple-500/10 text-purple-600 border-purple-500/20',
                        default => 'bg-primary-fixed/10 text-primary-fixed border-primary-fixed/20',
                    };
                    $label = $etape->labelCourt();
                }

                $heure = null;
                try {
                    $col = $this->colonneEtape($etape);
                    $d = $this->{$col};
                    if ($d instanceof Carbon) {
                        $heure = $d->format('H:i');
                    }
                } catch (\Throwable) {
                }
                if ($this->eta_arrive_minutes && ! $this->arrive_accueil_at && ! $heure) {
                    $duree = Carbon::now()->addMinutes($this->eta_arrive_minutes);
                    $heure = 'ETA '.$duree->format('H:i');
                }

                $suffixe = $heure ? sprintf('<span class="opacity-80 font-medium ml-1">%s</span>', e($heure)) : '';

                return sprintf(
                    '<span class="%s"><i class="fa-solid %s text-[10px]"></i><span>%s</span>%s</span>',
                    $baseCls.$cls,
                    e($etape->icone()),
                    e($label),
                    $suffixe
                );
            },
        );
    }
}
