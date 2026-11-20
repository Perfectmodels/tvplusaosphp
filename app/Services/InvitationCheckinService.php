<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EtapeParcoursIntervenantEnum;
use App\Enums\PosteScanEnum;
use App\Models\IntervenantCheckinAttempt;
use App\Models\IntervenantQrCode;
use App\Models\IntervenantScanLog;
use App\Models\ParcoursIntervenant;
use App\Models\ParcoursIntervenantEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InvitationCheckinService
{
    public function lookup(
        string $code,
        int $emissionId,
        string $scanMethod,
        Request $request,
        User $operator,
        ?int $expectedIntervenantId = null,
    ): array {
        $ambiguous = false;
        $suggestedEmissionId = null;
        $codeManuel = $scanMethod === 'manual' ? $this->codeManuel($code) : null;
        $manualLookup = $scanMethod === 'manual'
            && ($codeManuel !== null || preg_match('/^INT-\d{4}-\d{4,}$/i', trim($code)));
        if ($manualLookup) {
            if ($codeManuel !== null) {
                $parcours = $this->parcoursQuery()->where('code_manuel', $codeManuel)->first();
            } else {
                $candidates = $this->manualCandidates(strtoupper(trim($code)), $emissionId);
                $ambiguous = $candidates->count() > 1;
                $parcours = $ambiguous ? null : $candidates->first();
            }
        } else {
            $resolution = $this->resolveParcours($code, $emissionId);
            $parcours = $resolution['parcours'];
            $ambiguous = $resolution['ambiguous'];
            $suggestedEmissionId = $resolution['suggested_emission_id'];
        }
        $result = 'pending';
        $reason = null;

        if ($ambiguous) {
            $result = 'invalid';
            $reason = $manualLookup
                ? 'Plusieurs invitations correspondent à ce numéro INT pour cette émission. Saisissez plutôt le code à 6 chiffres affiché sous le QR de l’invitation.'
                : 'Plusieurs invitations actives correspondent à ce QR pour cette émission. Utilisez le QR d’accès propre à l’invitation concernée.';
        } elseif (! $parcours) {
            $result = 'invalid';
            $reason = $codeManuel !== null
                ? 'Aucune invitation ne correspond à ce code à 6 chiffres.'
                : ($manualLookup
                    ? 'Aucune invitation active ne correspond à ce code pour l’émission sélectionnée.'
                    : 'QR invalide ou aucune invitation active pour cette émission.');
        } elseif ($expectedIntervenantId !== null && (int) $parcours->intervenant_id !== $expectedIntervenantId) {
            $result = 'invalid';
            $reason = $manualLookup
                ? 'Ce code appartient à un autre intervenant que la fiche sélectionnée.'
                : 'Ce QR appartient à un autre intervenant que la fiche sélectionnée.';
        } elseif ($this->emissionId($parcours) !== $emissionId) {
            $result = 'invalid';
            $suggestedEmissionId = $this->emissionId($parcours);
            $reason = $manualLookup
                ? 'Ce code ne correspond pas à l’émission sélectionnée.'
                : 'Ce QR ne correspond pas à l’émission sélectionnée.';
        } elseif (! $parcours->est_actif || $parcours->no_show || ! $parcours->intervenant?->is_active) {
            $result = 'invalid';
            $reason = 'Cette invitation est inactive ou annulée.';
        } elseif (! $parcours->heure_passage_prevue) {
            $result = 'invalid';
            $reason = 'Aucun horaire de passage n’est programmé. Contactez la production.';
        } elseif ($this->estExpiree($parcours)) {
            $result = 'expired';
            $reason = $manualLookup
                ? 'Ce code a expiré 24 heures après l’horaire prévu du passage.'
                : 'Le QR a expiré 24 heures après l’horaire prévu du passage.';
        } elseif ($this->arriveeDejaEnregistree($parcours)) {
            $result = 'already_recorded';
            $reason = 'L’arrivée de cet invité est déjà enregistrée.';
        }

        $attempt = IntervenantCheckinAttempt::create([
            'parcours_intervenant_id' => $parcours?->id,
            'intervenant_id' => $parcours?->intervenant_id,
            'emission_id' => $emissionId,
            'conducteur_id' => $parcours?->conducteur_id,
            'scanned_by_user_id' => $operator->id,
            'scan_method' => $scanMethod,
            'result' => $result,
            'reason' => $reason,
            'scanned_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        if ($result !== 'pending') {
            return [
                'ok' => false,
                'attempt_id' => $attempt->id,
                'result' => $result,
                'message' => $reason,
                'scanned_at' => $attempt->scanned_at->toIso8601String(),
                'guest' => $parcours ? $this->guestPayload($parcours, $operator) : null,
                'parcours' => $parcours ? $this->parcoursPayload($parcours) : null,
                'suggested_emission_id' => $suggestedEmissionId,
                'suggested_emission_nom' => $suggestedEmissionId ? $this->nomEmission($parcours) : null,
            ];
        }

        return [
            'ok' => true,
            'result' => 'pending',
            'attempt_id' => $attempt->id,
            'scanned_at' => $attempt->scanned_at->toIso8601String(),
            'guest' => $this->guestPayload($parcours, $operator),
            'parcours' => $this->parcoursPayload($parcours),
        ];
    }

    public function tracking(ParcoursIntervenant $parcours): array
    {
        return $this->parcoursPayload($parcours);
    }

    public function confirm(
        int $attemptId,
        string $token,
        int $emissionId,
        User $operator,
    ): array {
        try {
            return DB::transaction(function () use ($attemptId, $token, $emissionId, $operator): array {
                $attempt = IntervenantCheckinAttempt::query()
                    ->whereKey($attemptId)
                    ->where('scanned_by_user_id', $operator->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($attempt->result !== 'pending') {
                    return $this->attemptResult($attempt, $attempt->parcours);
                }

                /** @var ParcoursIntervenant|null $parcours */
                $parcours = ParcoursIntervenant::query()
                    ->with(['intervenant', 'emission', 'sequence.emission', 'conducteur.emission', 'pivotSequenceIntervenant'])
                    ->lockForUpdate()
                    ->find($attempt->parcours_intervenant_id);

                $eventToken = $this->eventToken($token);
                $accessToken = $this->accessToken($token);
                $codeManuel = $attempt->scan_method === 'manual' ? $this->codeManuel($token) : null;
                $guestCodeMatches = $parcours && $attempt->scan_method === 'manual'
                    && preg_match('/^INT-\d{4}-\d{4,}$/i', trim($token))
                    && hash_equals((string) $parcours->intervenant?->code_intervenant, strtoupper(trim($token)));
                $manualCodeMatches = $parcours && $codeManuel !== null
                    && hash_equals((string) $parcours->code_manuel, $codeManuel);
                $eventTokenMatches = $parcours && $eventToken !== null
                    && hash_equals((string) $parcours->qr_token, $eventToken);
                $accessTokenMatches = $parcours && $accessToken !== null
                    && IntervenantQrCode::query()
                        ->where('token', $accessToken)
                        ->where('intervenant_id', $parcours->intervenant_id)
                        ->where('is_active', true)
                        ->whereNull('revoked_at')
                        ->exists();

                if (! $parcours || (! $guestCodeMatches && ! $manualCodeMatches && ! $eventTokenMatches && ! $accessTokenMatches)) {
                    return $this->attemptResult($this->finaliser(
                        $attempt,
                        'invalid',
                        'Le QR ne correspond plus à cette invitation.',
                    ));
                }

                if ($this->emissionId($parcours) !== $emissionId || (int) $attempt->emission_id !== $emissionId) {
                    return $this->attemptResult($this->finaliser(
                        $attempt,
                        'invalid',
                        'Ce QR ne correspond pas à l’émission sélectionnée.',
                    ));
                }

                if (! $parcours->est_actif || $parcours->no_show || ! $parcours->intervenant?->is_active) {
                    return $this->attemptResult($this->finaliser(
                        $attempt,
                        'invalid',
                        'Cette invitation est inactive ou annulée.',
                    ));
                }

                if (! $parcours->heure_passage_prevue) {
                    return $this->attemptResult($this->finaliser(
                        $attempt,
                        'invalid',
                        'Aucun horaire de passage n’est programmé. Contactez la production.',
                    ));
                }

                if ($this->estExpiree($parcours)) {
                    return $this->attemptResult($this->finaliser(
                        $attempt,
                        'expired',
                        'Le QR a expiré 24 heures après l’horaire prévu du passage.',
                    ));
                }

                if ($this->arriveeDejaEnregistree($parcours)) {
                    $attempt = $this->finaliser($attempt, 'already_recorded', 'L’arrivée de cet invité est déjà enregistrée.');
                    $this->creerLogScan($parcours, $attempt, $operator);

                    return $this->attemptResult($attempt, $parcours);
                }

                $validatedAt = Carbon::now();
                $parcours->avancerEtape(
                    EtapeParcoursIntervenantEnum::ArriveAccueil,
                    $operator,
                    'scan_qr',
                    'Entrée validée par scan QR.',
                    $validatedAt,
                );
                app(ParcoursIntervenantService::class)->synchroniserPivot(
                    $parcours->fresh(['pivotSequenceIntervenant']),
                    EtapeParcoursIntervenantEnum::ArriveAccueil,
                    $operator,
                );

                $parcours->intervenant->forceFill(['last_scan_at' => $attempt->scanned_at])->save();
                $attempt = $this->finaliser($attempt, 'accepted', null, $validatedAt);
                $this->creerLogScan($parcours, $attempt, $operator);

                return $this->attemptResult(
                    $attempt,
                    $parcours->fresh(['intervenant', 'emission', 'sequence.emission', 'conducteur.emission']),
                );
            });
        } catch (UniqueConstraintViolationException|QueryException $e) {
            $attempt = IntervenantCheckinAttempt::query()->find($attemptId);
            if ($attempt) {
                if ($attempt->result === 'pending') {
                    $attempt = $this->finaliser($attempt, 'already_recorded', 'L’arrivée de cet invité est déjà enregistrée.');
                }

                return $this->attemptResult($attempt);
            }

            throw $e;
        }
    }

    public function cancel(int $attemptId, User $operator): array
    {
        $attempt = IntervenantCheckinAttempt::query()
            ->whereKey($attemptId)
            ->where('scanned_by_user_id', $operator->id)
            ->firstOrFail();

        if ($attempt->result === 'pending') {
            $attempt = $this->finaliser($attempt, 'cancelled', 'Validation annulée par l’agent.');
        }

        return $this->attemptResult($attempt);
    }

    /**
     * Résout un QR scanné vers le parcours concerné.
     *
     * Deux familles sont acceptées :
     * - le jeton d'événement (QR d'accès d'une invitation précise) ;
     * - le jeton stable du badge intervenant, qui est rattaché à l'émission
     *   choisie dans le scanner.
     *
     * @return array{parcours: ParcoursIntervenant|null, ambiguous: bool, suggested_emission_id: int|null}
     */
    private function resolveParcours(string $code, int $emissionId): array
    {
        $token = $this->eventToken($code);

        if ($token) {
            return [
                'parcours' => $this->parcoursQuery()->where('qr_token', $token)->first(),
                'ambiguous' => false,
                'suggested_emission_id' => null,
            ];
        }

        $accessToken = $this->accessToken($code);
        if (! $accessToken) {
            return ['parcours' => null, 'ambiguous' => false, 'suggested_emission_id' => null];
        }

        $badge = IntervenantQrCode::query()
            ->with('intervenant')
            ->where('token', $accessToken)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if (! $badge?->intervenant) {
            return ['parcours' => null, 'ambiguous' => false, 'suggested_emission_id' => null];
        }

        $candidates = $this->parcoursQuery()
            ->where('intervenant_id', $badge->intervenant_id)
            ->where('est_actif', true)
            ->where('no_show', false)
            ->whereNotNull('heure_passage_prevue')
            ->get();

        $pourEmission = $candidates
            ->filter(fn (ParcoursIntervenant $parcours): bool => $this->emissionId($parcours) === $emissionId)
            ->values();

        $suggested = $candidates
            ->map(fn (ParcoursIntervenant $parcours): ?int => $this->emissionId($parcours))
            ->filter()
            ->first();

        if ($pourEmission->isEmpty()) {
            return [
                'parcours' => null,
                'ambiguous' => false,
                'suggested_emission_id' => $suggested,
            ];
        }

        $nonExpires = $pourEmission->filter(fn (ParcoursIntervenant $parcours): bool => ! $this->estExpiree($parcours));
        $pool = $nonExpires->isNotEmpty() ? $nonExpires : $pourEmission;

        $pending = $pool->filter(fn (ParcoursIntervenant $parcours): bool => $parcours->arrive_accueil_at === null);
        $selection = $pending->isNotEmpty() ? $pending : $pool;

        $moments = $selection
            ->map(fn (ParcoursIntervenant $parcours): string => (string) $parcours->heure_passage_prevue?->format('Y-m-d H:i'))
            ->unique();

        if ($moments->count() > 1) {
            $selection = $selection->sortBy(
                fn (ParcoursIntervenant $parcours): int => abs(($parcours->heure_passage_prevue?->getTimestamp() ?? 0) - now()->getTimestamp())
            )->values();
        }

        return [
            'parcours' => $selection->first(),
            'ambiguous' => $moments->count() === 1 && $selection->count() > 1,
            'suggested_emission_id' => null,
        ];
    }

    /**
     * Extrait le jeton stable du badge depuis une URL de QR ou un jeton brut.
     */
    private function accessToken(string $code): ?string
    {
        $value = trim($code);

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);
            parse_str($parts['query'] ?? '', $query);

            return is_string($query['acces'] ?? null) && $query['acces'] !== ''
                ? $query['acces']
                : null;
        }

        return str_starts_with($value, 'eyJ') && substr_count($value, '.') === 2 ? $value : null;
    }

    /**
     * Normalise un code manuel à 6 chiffres : espaces, points et tirets ignorés.
     */
    private function codeManuel(string $code): ?string
    {
        $value = preg_replace('/[\s\x{00A0}.\x{2013}\x{2014}-]+/u', '', trim($code));

        return is_string($value) && preg_match('/^\d{6}$/', $value) === 1 ? $value : null;
    }

    private function eventToken(string $code): ?string
    {
        $value = trim($code);
        $eventToken = $value;

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);
            parse_str($parts['query'] ?? '', $query);
            if (preg_match('~^/i/checkin/([A-Za-z0-9]{48})\.png$~', $parts['path'] ?? '', $matches)) {
                $eventToken = $matches[1];
            } elseif (is_string($query['invitation'] ?? null)) {
                $eventToken = $query['invitation'];
            } elseif (preg_match('~&invitation=([A-Za-z0-9]{48})$~', $parts['path'] ?? '', $matches)) {
                $eventToken = $matches[1];
            } else {
                $eventToken = '';
            }
        }

        if (! preg_match('/^[A-Za-z0-9]{48}$/', $eventToken)) {
            return null;
        }

        return $eventToken;
    }

    private function manualCandidates(string $code, int $emissionId): Collection
    {
        $query = $this->parcoursQuery()
            ->whereHas('intervenant', fn (Builder $guest) => $guest->where('code_intervenant', $code)->where('is_active', true))
            ->where('est_actif', true)
            ->where('no_show', false)
            ->where('heure_passage_prevue', '>=', now()->subHours(24))
            ->where(function (Builder $emission) use ($emissionId) {
                $emission->where('emission_id', $emissionId)
                    ->orWhere(fn (Builder $fallback) => $fallback->whereNull('emission_id')->whereHas('sequence', fn (Builder $sequence) => $sequence->where('emission_id', $emissionId)))
                    ->orWhere(fn (Builder $fallback) => $fallback->whereNull('emission_id')
                        ->where(fn (Builder $sequence) => $sequence->whereDoesntHave('sequence')->orWhereHas('sequence', fn (Builder $linked) => $linked->whereNull('emission_id')))
                        ->whereHas('conducteur', fn (Builder $conducteur) => $conducteur->where('emission_id', $emissionId)));
            });
        $pending = (clone $query)->whereNull('arrive_accueil_at')->limit(2)->get();

        return $pending->isNotEmpty() ? $pending : $query->limit(2)->get();
    }

    private function parcoursQuery(): Builder
    {
        return ParcoursIntervenant::query()
            ->with(['intervenant', 'emission', 'sequence.emission', 'conducteur.emission', 'pivotSequenceIntervenant']);
    }

    private function emissionId(ParcoursIntervenant $parcours): ?int
    {
        return $parcours->emission_id
            ?? $parcours->sequence?->emission_id
            ?? $parcours->conducteur?->emission_id;
    }

    private function nomEmission(?ParcoursIntervenant $parcours): ?string
    {
        if (! $parcours) {
            return null;
        }

        $nom = $parcours->emission?->nom
            ?? $parcours->sequence?->emission?->nom
            ?? $parcours->conducteur?->emission?->nom;

        return filled($nom) ? (string) $nom : null;
    }

    private function estExpiree(ParcoursIntervenant $parcours): bool
    {
        return $parcours->heure_passage_prevue !== null
            && $parcours->heure_passage_prevue->copy()->addHours(24)->isPast();
    }

    private function arriveeDejaEnregistree(ParcoursIntervenant $parcours): bool
    {
        return $parcours->arrive_accueil_at !== null
            || ParcoursIntervenantEvent::query()
                ->where('parcours_intervenant_id', $parcours->id)
                ->where('etape', EtapeParcoursIntervenantEnum::ArriveAccueil->value)
                ->exists();
    }

    private function guestPayload(ParcoursIntervenant $parcours, User $operator): array
    {
        $emission = $parcours->emission?->nom
            ?? $parcours->sequence?->emission?->nom
            ?? $parcours->conducteur?->emission?->nom
            ?? 'Émission';

        return [
            'parcours_id' => (int) $parcours->id,
            'intervenant_id' => $parcours->intervenant_id,
            'code_intervenant' => $parcours->intervenant?->code_intervenant,
            'fiche_url' => $parcours->intervenant && Gate::forUser($operator)->allows('view', $parcours->intervenant)
                ? route('intervenants.show', $parcours->intervenant)
                : null,
            'nom' => $parcours->intervenant?->nom_complet,
            'fonction' => $parcours->intervenant?->fonction,
            'organisme' => $parcours->intervenant?->organisme,
            'emission' => $emission,
            'heure_prevue' => $parcours->heure_passage_prevue?->toIso8601String(),
            'statut_invitation' => $parcours->confirme_at ? 'Confirmée' : 'En attente de confirmation',
        ];
    }

    private function finaliser(
        IntervenantCheckinAttempt $attempt,
        string $result,
        ?string $reason,
        ?Carbon $validatedAt = null,
    ): IntervenantCheckinAttempt {
        $attempt->forceFill([
            'result' => $result,
            'reason' => $reason,
            'validated_at' => $validatedAt ?? now(),
        ])->save();

        return $attempt;
    }

    private function attemptResult(
        IntervenantCheckinAttempt $attempt,
        ?ParcoursIntervenant $parcours = null,
    ): array {
        $messages = [
            'accepted' => 'Entrée validée.',
            'already_recorded' => 'L’arrivée de cet invité est déjà enregistrée.',
            'invalid' => $attempt->reason ?? 'QR invalide.',
            'expired' => $attempt->reason ?? 'QR expiré.',
            'cancelled' => 'Validation annulée.',
            'pending' => 'La validation est en attente.',
        ];

        return [
            'ok' => $attempt->result === 'accepted',
            'result' => $attempt->result,
            'message' => $messages[$attempt->result] ?? 'Résultat du pointage indisponible.',
            'scanned_at' => $attempt->scanned_at?->toIso8601String(),
            'validated_at' => $attempt->validated_at?->toIso8601String(),
            'parcours' => $parcours ? $this->parcoursPayload($parcours) : null,
        ];
    }

    private function parcoursPayload(ParcoursIntervenant $parcours): array
    {
        $parcours->loadMissing(['intervenant', 'emission', 'sequence.emission', 'conducteur.emission']);
        $etapes = [];

        foreach (EtapeParcoursIntervenantEnum::cases() as $etape) {
            $date = $parcours->{$parcours->colonneEtape($etape)};
            $etapes[] = [
                'value' => $etape->value,
                'label' => $etape->label(),
                'fait' => $date !== null,
                'date' => $date?->toIso8601String(),
                'horodatage' => $date?->format('d/m/Y · H:i'),
            ];
        }

        $prochaineEtape = collect($etapes)->firstWhere('fait', false);

        return [
            'id' => (int) $parcours->id,
            'etapes' => $etapes,
            'etape_courante' => $prochaineEtape['value'] ?? null,
            'etape_courante_label' => $prochaineEtape['label'] ?? 'Terminé',
            'retard_minutes' => (int) $parcours->retard_minutes,
            'en_retard' => $parcours->estEnRetard(),
            'no_show' => (bool) $parcours->no_show,
            'emission_nom' => (string) (
                $parcours->emission?->nom
                ?? $parcours->sequence?->emission?->nom
                ?? $parcours->conducteur?->emission?->nom
                ?? '—'
            ),
            'heure_passage' => $parcours->heure_passage_prevue?->toIso8601String(),
            'intervenant' => [
                'nom_complet' => (string) ($parcours->intervenant?->nom_complet ?? '—'),
            ],
        ];
    }

    private function creerLogScan(
        ParcoursIntervenant $parcours,
        IntervenantCheckinAttempt $attempt,
        User $operator,
    ): void {
        IntervenantScanLog::create([
            'intervenant_id' => $parcours->intervenant_id,
            'parcours_intervenant_id' => $parcours->id,
            'sequence_intervenant_id' => $parcours->sequence_intervenant_id,
            'conducteur_id' => $parcours->conducteur_id,
            'scanned_by_user_id' => $operator->id,
            'poste' => PosteScanEnum::Accueil->value,
            'scan_method' => $attempt->scan_method,
            'etat_resultat' => $attempt->result,
            'scanned_at' => $attempt->scanned_at,
            'metadata' => ['checkin_attempt_id' => $attempt->id],
            'ip_terminal' => $attempt->ip_address,
            'user_agent' => $attempt->user_agent,
        ]);
    }
}
