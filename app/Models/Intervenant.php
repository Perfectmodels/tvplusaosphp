<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CiviliteCast;
use App\Enums\CiviliteEnum;
use App\Enums\EtapeParcoursIntervenantEnum;
use App\Enums\PosteScanEnum;
use App\Enums\StatutDispoIntervenantEnum;
use App\Enums\StatutLogistiquePivotEnum;
use App\Enums\TypeIntervenantEnum;
use App\Services\MediaValidation;
use Firebase\JWT\JWT;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Intervenant externe (invité, expert, politicien, etc.) SPEC §3
 * - Photo portrait via MediaLibrary
 * - QR Code 1-to-1 (IntervenantQrCode) GÉNÉRÉ AUTOMATIQUEMENT au created
 * - Pivot sequence_intervenants contient les horodatages Arrivé/Maquillage/Plateau
 */
class Intervenant extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'code_intervenant',
        'uuid_intervenant',
        'invitation_token',
        'code',
        'civilite',
        'nom',
        'prenom',
        'first_name',
        'last_name',
        'date_naissance',
        'lieu_naissance',
        'piece_identite',
        'fonction',
        'organisme',
        'organization',
        'poste',
        'role_type',
        'type',
        'domaines',
        'specialty',
        'langues',
        'language',
        'availability',
        'ville_residence',
        'city',
        'pays_residence',
        'country',
        'telephone_1',
        'phone',
        'telephone_2',
        'email',
        'adresse_physique',
        'address',
        'contact_urgence',
        'presentation_courte',
        'bio_short',
        'biographie_longue',
        'bio_long',
        'sujets_sensibles',
        'taille_habillement',
        'regime_alimentaire',
        'observations_admin',
        'notes',
        'contract_type',
        'fee_amount',
        'disponibilite',
        'photo',
        'is_active',
        'last_scan_at',
    ];

    protected $casts = [
        'civilite' => CiviliteCast::class,
        'type' => TypeIntervenantEnum::class,
        'disponibilite' => StatutDispoIntervenantEnum::class,
        'domaines' => 'array',
        'langues' => 'array',
        'contact_urgence' => 'array',
        'date_naissance' => 'date',
        'last_scan_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
        'disponibilite' => StatutDispoIntervenantEnum::Disponible->value,
        'type' => TypeIntervenantEnum::Invite->value,
        'civilite' => CiviliteEnum::Monsieur->value,
    ];

    // =====================================================================
    // BOOT — UUID + Code auto génération + QR Code auto création
    // =====================================================================

    protected static function booted(): void
    {
        static::saving(function (self $i) {
            if (empty($i->first_name) && ! empty($i->prenom)) {
                $i->first_name = $i->prenom;
            }
            if (empty($i->last_name) && ! empty($i->nom)) {
                $i->last_name = $i->nom;
            }
            if (empty($i->code) && ! empty($i->code_intervenant)) {
                $i->code = $i->code_intervenant;
            }
            if (empty($i->phone) && ! empty($i->telephone_1)) {
                $i->phone = $i->telephone_1;
            }
            if (empty($i->organization) && ! empty($i->organisme)) {
                $i->organization = $i->organisme;
            }
            if (empty($i->address) && ! empty($i->adresse_physique)) {
                $i->address = $i->adresse_physique;
            }
            if (empty($i->city) && ! empty($i->ville_residence)) {
                $i->city = $i->ville_residence;
            }
            if (empty($i->country) && ! empty($i->pays_residence)) {
                $i->country = $i->pays_residence;
            }
        });

        static::creating(function (self $i) {
            if (empty($i->uuid_intervenant)) {
                $i->uuid_intervenant = (string) Str::uuid();
            }
            if (empty($i->code_intervenant)) {
                $annee = date('Y');
                $prefix = "INT-{$annee}-";
                $connection = config('database.default');
                if ($connection === 'mysql') {
                    $max = (int) self::query()
                        ->where('code_intervenant', 'LIKE', "{$prefix}%")
                        ->max(DB::raw("CAST(SUBSTRING_INDEX(code_intervenant, '-', -1) AS UNSIGNED)"));
                } else {
                    $max = 0;
                    foreach (self::query()->where('code_intervenant', 'LIKE', "{$prefix}%")->pluck('code_intervenant') as $code) {
                        $suffix = (string) Str::afterLast((string) $code, '-');
                        $num = (int) preg_replace('/\D+/', '', $suffix);
                        if ($num > $max) {
                            $max = $num;
                        }
                    }
                }
                $i->code_intervenant = sprintf('INT-%s-%04d', $annee, $max + 1);
            }
        });

        // SPEC §3.4 règle importante : QR Code généré AUTOMATIQUEMENT à la création
        static::created(function (self $i) {
            if (! $i->qrCode()->exists()) {
                $i->generateQrCode();
            }
        });
    }

    // =====================================================================
    // ACCESSORS
    // =====================================================================

    protected function nomComplet(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(
                ($this->civilite ? ($this->civilite->value.' ') : '')
                .($this->prenom ?? '').' '.($this->nom ?? '')
            ),
        );
    }

    protected function initiales(): Attribute
    {
        return Attribute::make(
            get: fn (): string => strtoupper(
                mb_substr((string) ($this->prenom ?? ''), 0, 1)
                .mb_substr((string) ($this->nom ?? ''), 0, 1)
            )
        );
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                try {
                    if ($this->hasMedia('portrait')) {
                        return $this->getFirstMediaUrl('portrait', 'thumb');
                    }
                } catch (\Throwable) {
                    // Media table missing or Spatie MediaLibrary not installed
                }
                if (! empty($this->photo)) {
                    return (string) $this->photo;
                }
                $n = urlencode($this->initiales ?: 'IV');

                return "https://ui-avatars.com/api/?name={$n}&background=0054cd&color=ffffff&size=256";
            }
        );
    }

    protected function historiquePassagesCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) $this->sequences()->count(),
        );
    }

    // =====================================================================
    // SCOPES (SPEC §3.3 barre outils)
    // =====================================================================

    public function scopeRecherche($query, string $q)
    {
        $s = '%'.mb_strtolower(trim($q)).'%';

        return $query->where(static function ($qq) use ($s) {
            $qq->whereRaw('LOWER(nom) LIKE ?', [$s])
                ->orWhereRaw('LOWER(prenom) LIKE ?', [$s])
                ->orWhereRaw('LOWER(code_intervenant) LIKE ?', [$s])
                ->orWhereRaw('LOWER(COALESCE(fonction,\'\')) LIKE ?', [$s])
                ->orWhereRaw('LOWER(COALESCE(organisme,\'\')) LIKE ?', [$s])
                ->orWhereRaw('LOWER(COALESCE(telephone_1,\'\')) LIKE ?', [$s])
                ->orWhereRaw('LOWER(COALESCE(email,\'\')) LIKE ?', [$s]);
        });
    }

    public function scopeDisponibles($query)
    {
        return $query->where('disponibilite', StatutDispoIntervenantEnum::Disponible->value)
            ->where('is_active', true);
    }

    public function scopeReserveDansConducteurs($query, array $filters = [])
    {
        $periode = $filters['periode'] ?? 'today';
        $emissionId = $filters['emission_id'] ?? null;
        $conducteurId = $filters['conducteur_id'] ?? null;

        [$dateDebut, $dateFin] = match ($periode) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7j' => [now()->startOfDay(), now()->addDays(7)->endOfDay()],
            '30j' => [now()->startOfDay(), now()->addDays(30)->endOfDay()],
            'futur' => [now()->startOfDay(), null],
            'passe' => [null, now()->subDay()->endOfDay()],
            default => [null, null],
        };

        return $query->whereHas('sequences', static function ($q) use ($dateDebut, $dateFin, $emissionId, $conducteurId) {
            $q->join('conducteurs', static function ($j) {
                $j->on('conducteurs.id', '=', 'sequences.conducteur_id')
                    ->whereIn('conducteurs.type', ['chef_edition', 'presentateur', 'redaction']);
            });
            if ($dateDebut) {
                $q->whereDate('conducteurs.date_edition', '>=', $dateDebut->toDateString());
            }
            if ($dateFin) {
                $q->whereDate('conducteurs.date_edition', '<=', $dateFin->toDateString());
            }
            if ($emissionId) {
                $q->where('sequences.emission_id', (int) $emissionId);
            }
            if ($conducteurId) {
                $q->where('conducteurs.id', (int) $conducteurId);
            }
        });
    }

    public function scopeNonReserveDansPeriode($query, array $filters = [])
    {
        return $query->whereNotIn(
            'id',
            self::query()->reserveDansConducteurs($filters)->select('intervenants.id')
        );
    }

    protected function prochainesReservations(): Attribute
    {
        return Attribute::make(
            get: function (): Collection {
                $today = now()->startOfDay();

                return $this->sequences()
                    ->join('conducteurs', 'conducteurs.id', '=', 'sequences.conducteur_id')
                    ->whereDate('conducteurs.date_edition', '>=', $today->toDateString())
                    ->whereIn('conducteurs.type', ['chef_edition', 'presentateur', 'redaction'])
                    ->orderBy('conducteurs.date_edition', 'ASC')
                    ->orderBy('sequences.position', 'ASC')
                    ->with(['emission', 'conducteur'])
                    ->limit(5)
                    ->get(['sequences.*', 'conducteurs.date_edition', 'conducteurs.type AS conducteur_type', 'conducteurs.nom AS conducteur_nom']);
            },
        );
    }

    protected function nombreReservationsFutures(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $today = now()->startOfDay();

                return (int) $this->sequences()
                    ->join('conducteurs', 'conducteurs.id', '=', 'sequences.conducteur_id')
                    ->whereDate('conducteurs.date_edition', '>=', $today->toDateString())
                    ->whereIn('conducteurs.type', ['chef_edition', 'presentateur', 'redaction'])
                    ->count();
            },
        );
    }

    protected function estReserveAujourdhui(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $today = now()->toDateString();

                return (bool) $this->sequences()
                    ->join('conducteurs', 'conducteurs.id', '=', 'sequences.conducteur_id')
                    ->whereDate('conducteurs.date_edition', $today)
                    ->whereIn('conducteurs.type', ['chef_edition', 'presentateur', 'redaction'])
                    ->exists();
            },
        );
    }

    protected function prochaineReservationHtml(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $first = $this->prochaines_reservations->first();
                if (! $first) {
                    return '';
                }
                try {
                    $date = $first->date_edition ? Carbon::parse($first->date_edition) : null;
                } catch (\Throwable) {
                    $date = null;
                }
                $dateStr = $date ? $date->format('d/m/Y') : 'Date inconnue';
                $emission = e($first->emission?->nom ?? 'Sans émission');
                $sujet = e(Str::limit($first->title ?? 'Sans sujet', 36));
                $typeLabel = match ((string) ($first->conducteur_type ?? '')) {
                    'chef_edition' => '🖋️ Chef Édition',
                    'redaction' => '📰 Rédaction',
                    'presentateur' => '🎙️ Présentateur',
                    default => '🎬 Conducteur',
                };

                return sprintf(
                    '<div class="w-full rounded-lg bg-primary-fixed/8 border border-primary-fixed/15 p-2.5 text-[11px] leading-snug">'
                        .'<div class="flex items-center justify-between gap-2 mb-1">'
                            .'<span class="font-bold text-primary-fixed truncate">%s</span>'
                            .'<span class="text-[10px] text-primary-fixed/70 whitespace-nowrap">%s</span>'
                        .'</div>'
                        .'<div class="text-on-surface-variant truncate">%s</div>'
                        .'<div class="text-on-surface font-semibold truncate mt-0.5">%s</div>'
                    .'</div>',
                    $typeLabel, $dateStr, $emission, $sujet
                );
            },
        );
    }

    // =====================================================================
    // RELATIONS
    // =====================================================================

    public function qrCode(): HasOne
    {
        return $this->hasOne(IntervenantQrCode::class, 'intervenant_id')
            ->where('is_active', true)
            ->latestOfMany();
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(IntervenantQrCode::class, 'intervenant_id')
            ->orderByDesc('is_active')
            ->latest('id');
    }

    public function sequences(): BelongsToMany
    {
        return $this->belongsToMany(Sequence::class, 'sequence_intervenants')
            ->using(SequenceIntervenantPivot::class)
            ->withTimestamps()
            ->withPivot([
                'position', 'time_slot', 'status', 'notes',
                'heure_arrive', 'heure_maq_debut', 'heure_maq_fin', 'heure_plateau', 'heure_diffusion',
                'scanned_by_user_id',
                // Nouvelles SPEC colonnes pivot
                'role_dans_sequence', 'ordre_passage', 'duree_allouee_sec', 'heure_passage_prevue',
                'heure_termine_passage', 'statut_logistique', 'no_show', 'observations_logistique',
            ]);
    }

    /** 1:N — Tous les parcours de cet intervenant (historique linéaire) */
    public function parcoursIntervenants(): HasMany
    {
        return $this->hasMany(ParcoursIntervenant::class, 'intervenant_id')
            ->with(['sequence.emission', 'conducteur.emission', 'pivotSequenceIntervenant'])
            ->latest('created_at');
    }

    /** Raccourci: parcours du jour (dans conducteurs date_edition = today) */
    public function parcoursToday(): HasMany
    {
        return $this->parcoursIntervenants()->today();
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(IntervenantScanLog::class, 'intervenant_id')->latest('scanned_at');
    }

    // =====================================================================
    // QR CODE GÉNÉRATION — SPEC §3.4 + §3.5 + §7.2
    // =====================================================================

    public function checkInUrl(): string
    {
        try {
            return route('intervenants.qr.checkin', [
                'code' => $this->code_intervenant,
                't' => $this->uuid_intervenant,
            ], true);
        } catch (\Throwable) {
            return url('/intervenants/checkin').'?code='.urlencode((string) $this->code_intervenant).'&t='.urlencode((string) $this->uuid_intervenant);
        }
    }

    // =====================================================================
    // INVITATION PUBLIQUE — jeton permanent + lien WhatsApp
    // =====================================================================

    /**
     * Jeton public permanent de l'intervenant (généré à la demande).
     *
     * 48 caractères aléatoires : suffisamment long pour ne pas être deviné,
     * et stable dans le temps pour que le QR envoyé reste valable.
     */
    public function tokenInvitation(): string
    {
        if (filled($this->invitation_token)) {
            return (string) $this->invitation_token;
        }

        do {
            $token = Str::random(48);
        } while (static::query()->where('invitation_token', $token)->exists());

        $this->invitation_token = $token;

        // saveQuietly : un simple jeton ne doit pas rejouer les observers métier.
        $this->saveQuietly();

        return $token;
    }

    /** Régénère le jeton : l'ancien QR devient immédiatement invalide. */
    public function regenererTokenInvitation(): string
    {
        $this->invitation_token = null;
        $this->saveQuietly();

        return $this->tokenInvitation();
    }

    /** URL publique stable encodée dans le QR. */
    public function urlInvitation(): string
    {
        try {
            return route('invitations.show', ['token' => $this->tokenInvitation()], true);
        } catch (\Throwable) {
            return url('/i/'.$this->tokenInvitation());
        }
    }

    /**
     * URL d'invitation « complète » : le jeton public reste le seul paramètre.
     * Le QR d'accès scannable est produit par urlQrAcces().
     */
    public function urlInvitationComplete(): string
    {
        return $this->urlInvitation();
    }

    /**
     * Jeton stable du badge QR, utilisé par le scanner interne.
     * Il est révocable et régénéré avec le QR de l'intervenant.
     */
    public function qrAccesToken(): string
    {
        $qr = $this->qrCode()->first();

        if (! $qr) {
            $this->generateQrCode();
            $qr = $this->qrCode()->first();
        }

        return (string) ($qr?->token ?? '');
    }

    /**
     * URL scannable du badge : la page publique reste accessible au téléphone,
     * tandis que le scanner de TV+A OS reconnaît le jeton `acces`.
     */
    public function urlQrAcces(): string
    {
        $token = $this->qrAccesToken();

        if ($token === '') {
            return $this->urlInvitation();
        }

        return $this->urlInvitation().'?'.http_build_query(['acces' => $token]);
    }

    /** Invitations encore à confirmer (parcours actifs, non confirmés). */
    public function invitationsEnAttente(): HasMany
    {
        return $this->hasMany(ParcoursIntervenant::class, 'intervenant_id')
            ->with(['emission', 'sequence.emission'])
            ->whereNull('confirme_at')
            ->where('est_actif', true)
            ->orderByRaw('COALESCE(heure_passage_prevue, created_at) ASC');
    }

    /** Invitation en attente la plus proche (celle à envoyer par WhatsApp). */
    public function derniereInvitationEnAttente(): ?ParcoursIntervenant
    {
        return $this->invitationsEnAttente()->first();
    }

    /**
     * Génération JWT court terme (QR)
     */
    public function generateJwtToken(): string
    {
        $payload = [
            'app' => 'tvplus-os',
            'type' => 'intervenant',
            'code' => $this->code_intervenant,
            'uuid' => $this->uuid_intervenant,
            'iat' => now()->timestamp,
            'iss' => config('app.url'),
        ];
        /** @var string $secret */
        $secret = config('app.tvplus_qr_jwt_secret', config('app.key'));

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Génère l'enregistrement IntervenantQrCode + rendus PNG / SVG
     *
     * @return array{qr:IntervenantQrCode, png_path:string|null, svg_path:string|null}
     */
    public function generateQrCode(): array
    {
        // Un seul badge actif à la fois : l'ancien QR est révoqué avant
        // d'en créer un nouveau, sinon deux badges resteraient valides.
        IntervenantQrCode::query()
            ->where('intervenant_id', $this->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'revoked_at' => now()]);

        $jwt = $this->generateJwtToken();
        $checkIn = $this->checkInUrl();
        $payload = [
            'app' => 'tvplus-os',
            'type' => 'intervenant',
            'code' => $this->code_intervenant,
            'uuid' => $this->uuid_intervenant,
            'token' => $jwt,
            'check_in_url' => $checkIn,
        ];

        $qr = IntervenantQrCode::create([
            'intervenant_id' => $this->id,
            'token' => $jwt,
            'payload' => $payload,
            'generated_at' => now(),
            'is_active' => true,
        ]);

        // Générer PNG/SVG via SimpleSoftware QR Code dans storage/app/public/qr/
        try {
            $disk = Storage::disk('public');
            $dir = 'qr-codes';
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }
            $base = "{$dir}/intervenant-{$this->id}-{$qr->id}";

            $pngPath = "{$base}.png";
            $png = QrCode::format('png')
                ->size(512)
                ->errorCorrection('H')
                ->margin(2)
                ->generate(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $disk->put($pngPath, $png);

            $svgPath = "{$base}.svg";
            $svg = QrCode::format('svg')
                ->size(512)
                ->errorCorrection('H')
                ->margin(2)
                ->generate(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $disk->put($svgPath, $svg);

            $qr->forceFill([
                'qr_png_storage_path' => $pngPath,
                'qr_svg_storage_path' => $svgPath,
            ])->save();

            return [
                'qr' => $qr,
                'png_path' => $disk->url($pngPath),
                'svg_path' => $disk->url($svgPath),
            ];
        } catch (\Throwable $e) {
            logger()->warning('Intervenant QR render failed', [
                'intervenant' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return ['qr' => $qr, 'png_path' => null, 'svg_path' => null];
        }
    }

    // =====================================================================
    // MEDIA LIBRARY
    // =====================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('portrait')
            ->singleFile()
            // 🔒 Type + taille + SVG refusé (vecteur XSS).
            ->acceptsFile([MediaValidation::class, 'imagePortrait'])
            ->useFallbackUrl((string) $this->getRawOriginal('photo') ?: ('https://ui-avatars.com/api/?name='.urlencode($this->initiales)))
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->fit(Fit::Crop, 256, 256)->nonQueued()->performOnCollections('portrait');
                $this->addMediaConversion('xs')
                    ->fit(Fit::Crop, 64, 64)->nonQueued()->performOnCollections('portrait');
            });

        $this->addMediaCollection('documents')
            ->acceptsFile([MediaValidation::class, 'document'])
            ->acceptsMimeTypes(MediaValidation::TYPES_DOCUMENT);
    }

    // =====================================================================
    // SPEC §6.3 WORKFLOW SCAN PAR POSTE
    // =====================================================================

    /**
     * Déclencher une étape logistique (Accueil / Maquillage double / Plateau)
     *
     * @return array{etape:string,date:Carbon,sequence_intervenant:SequenceIntervenantPivot|null,message:string,deja_fait:bool}
     */
    public function triggerScanWorkflow(
        PosteScanEnum $poste,
        ?int $userIdScanner = null,
        ?Sequence $sequence = null,
    ): array {
        // Séquence ciblée = soit param soit la 1ere séquence du jour
        $pivot = null;
        if ($sequence) {
            $found = $this->sequences()
                ->where('sequences.id', $sequence->id)
                ->first();
            if ($found) {
                $pivot = SequenceIntervenantPivot::query()
                    ->where('sequence_id', $sequence->id)
                    ->where('intervenant_id', $this->id)
                    ->first();
            }
        }
        if (! $pivot) {
            $today = now()->startOfDay();
            $hasDateJournal = Schema::hasColumn('conducteurs', 'date_journal');
            $pivot = SequenceIntervenantPivot::query()
                ->where('intervenant_id', $this->id)
                ->when($hasDateJournal, static fn ($q) => $q
                    ->whereHas('sequence.conducteur', static fn ($cq) => $cq
                        ->whereDate('date_journal', '>=', $today->toDateString())
                    )
                )
                ->orderByRaw('COALESCE(heure_passage_prevue, time_slot) ASC')
                ->first();
        }

        $horodatage = null;
        $dejaFait = false;
        $parcours = null;
        $userScan = $userIdScanner ? (User::query()->find($userIdScanner) ?? null) : null;
        $etapeParcours = match ($poste) {
            PosteScanEnum::Accueil => EtapeParcoursIntervenantEnum::ArriveAccueil,
            PosteScanEnum::Maquillage => null,
            PosteScanEnum::Plateau => EtapeParcoursIntervenantEnum::SurPlateau,
            PosteScanEnum::Invitation => EtapeParcoursIntervenantEnum::Confirme,
        };
        $etapeParcoursSecondaire = null;
        switch ($poste) {
            case PosteScanEnum::Accueil:
                if ($pivot && ! empty($pivot->heure_arrive)) {
                    $dejaFait = true;
                }
                $horodatage = $pivot?->heure_arrive ?? now();
                if ($pivot) {
                    $pivot->heure_arrive = $horodatage;
                    $pivot->statut_logistique = StatutLogistiquePivotEnum::Arrive->value;
                    $pivot->save();
                }
                $this->last_scan_at = now();
                $this->save();
                break;

            case PosteScanEnum::Maquillage:
                // Double scan: si pas début → début ; sinon fin
                if (! $pivot?->heure_maq_debut) {
                    $horodatage = now();
                    $etapeParcours = EtapeParcoursIntervenantEnum::Maquillage;
                    if ($pivot) {
                        $pivot->heure_maq_debut = $horodatage;
                        $pivot->statut_logistique = StatutLogistiquePivotEnum::Maquillage->value;
                        $pivot->save();
                    }
                } else {
                    if (! empty($pivot?->heure_maq_fin)) {
                        $dejaFait = true;
                    }
                    $horodatage = $pivot->heure_maq_fin ?? now();
                    $etapeParcours = EtapeParcoursIntervenantEnum::Maquillage;
                    $etapeParcoursSecondaire = EtapeParcoursIntervenantEnum::PretPlateau;
                    if ($pivot) {
                        $pivot->heure_maq_fin = $horodatage;
                        $pivot->statut_logistique = StatutLogistiquePivotEnum::Pret->value;
                        $pivot->save();
                    }
                }
                break;

            case PosteScanEnum::Plateau:
                if ($pivot && ! empty($pivot->heure_plateau)) {
                    $dejaFait = true;
                }
                $horodatage = $pivot?->heure_plateau ?? now();
                if ($pivot) {
                    $pivot->heure_plateau = $horodatage;
                    $pivot->statut_logistique = StatutLogistiquePivotEnum::SurPlateau->value;
                    $pivot->save();
                }
                break;
        }

        // ➡️ SYNC PARCOURS INTERVENANT (9 étapes, ⑤→⑧ déclenchés ici)
        if ($pivot && $etapeParcours) {
            try {
                $parcours = $pivot->parcours;
                if (! $parcours) {
                    $parcours = ParcoursIntervenant::query()->firstOrCreate(
                        [
                            'sequence_intervenant_id' => $pivot->id,
                            'intervenant_id' => $pivot->intervenant_id,
                            'sequence_id' => $pivot->sequence_id,
                        ],
                        [
                            'conducteur_id' => $pivot->sequence?->conducteur_id,
                            'last_trigger_medium' => 'scan_qr',
                            'invitation_envoyee_at' => $pivot->created_at ?? now(),
                        ]
                    );
                }
                $parcours->avancerEtape($etapeParcours, $userScan, 'scan_qr', null, $horodatage);
                if ($etapeParcoursSecondaire) {
                    $parcours->avancerEtape($etapeParcoursSecondaire, $userScan, 'scan_qr', null, $horodatage);
                }
            } catch (\Throwable $e) {
                \Log::warning('triggerScanWorkflow → sync parcours failed', [
                    'intervenant' => $this->id,
                    'pivot' => $pivot->id ?? null,
                    'err' => $e->getMessage(),
                ]);
            }
        }

        // Log trace
        $this->scanLogs()->create([
            'parcours_intervenant_id' => $parcours?->id,
            'sequence_intervenant_id' => $pivot?->id,
            'poste' => $poste->value,
            'scanned_at' => $horodatage ?? now(),
            'metadata' => [
                'sequence_intervenant_id' => $pivot?->id,
                'sequence_id' => $sequence?->id ?? $pivot?->sequence_id,
                'deja_fait' => $dejaFait,
                'ip' => request()?->ip(),
                'ua' => request()?->userAgent(),
            ],
            'qr_code_id' => $this->qrCode?->id,
            'scanned_by_user_id' => $userIdScanner,
            'conducteur_id' => $sequence?->conducteur_id ?? $pivot?->sequence?->conducteur_id,
        ]);

        return [
            'etape' => match ($poste) {
                PosteScanEnum::Accueil => 'Arrivé',
                PosteScanEnum::Maquillage => ($dejaFait || ($pivot?->heure_maq_fin)) ? 'Maquillage terminé' : 'Maquillage débuté',
                PosteScanEnum::Plateau => 'Sur plateau',
                PosteScanEnum::Invitation => 'Invitation confirmée',
            },
            'date' => $horodatage ?? now(),
            'sequence_intervenant_id' => $pivot?->id,
            'message' => $dejaFait ? 'Étape déjà enregistrée, mise à jour heure.' : 'OK',
            'deja_fait' => $dejaFait,
        ];
    }
}
