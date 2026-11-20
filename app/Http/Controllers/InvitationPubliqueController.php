<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Intervenant;
use App\Models\ParcoursIntervenant;
use App\Services\InvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Pages PUBLIQUES d'invitation — accessibles depuis le téléphone de
 * l'intervenant, sans connexion.
 *
 * ⚠️ La CONFIRMATION ne se fait PAS ici : elle est validée par un
 * utilisateur connecté qui scanne le QR (voir ScannerQrController).
 * Cette page sert uniquement de fiche + QR à présenter.
 */
class InvitationPubliqueController extends Controller
{
    private const NOINDEX = 'noindex, nofollow';

    public function show(string $token): View|Response
    {
        $intervenant = $this->intervenantParToken($token);

        if (! $intervenant) {
            return response()
                ->view('invitations.show', [
                    'intervenant' => null,
                    'invitations' => collect(),
                    'token'       => $token,
                ], 404)
                ->header('X-Robots-Tag', self::NOINDEX);
        }

        $invitations = $intervenant->parcoursIntervenants()
            ->with(['emission', 'sequence.emission', 'conducteur.emission'])
            ->where('est_actif', true)
            ->whereNull('arrive_accueil_at')
            ->where(static function ($query) {
                $query->whereNull('heure_passage_prevue')
                    ->orWhere('heure_passage_prevue', '>=', now()->subHours(24));
            })
            ->orderBy('heure_passage_prevue')
            ->get();

        return response()
            ->view('invitations.show', [
                'intervenant' => $intervenant,
                'invitations' => $invitations,
                'token'       => $token,
            ])
            ->header('X-Robots-Tag', self::NOINDEX);
    }

    public function qr(string $token, InvitationService $service): Response
    {
        $intervenant = $this->intervenantParToken($token);
        abort_if(! $intervenant, 404);

        return response($service->qrPng($intervenant), 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'inline; filename="qr-' . $intervenant->code_intervenant . '.png"',
            'Cache-Control'       => 'public, max-age=86400',
            'X-Robots-Tag'        => self::NOINDEX,
        ]);
    }

    public function checkinQr(string $token, InvitationService $service): Response
    {
        $parcours = ParcoursIntervenant::query()
            ->where('qr_token', $token)
            ->where('est_actif', true)
            ->firstOrFail();
        abort_if(
            ! $parcours->heure_passage_prevue
                || $parcours->heure_passage_prevue->copy()->addHours(24)->isPast(),
            410,
        );

        return response($service->qrPngPourParcours($parcours), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="qr-acces.png"',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => self::NOINDEX,
        ]);
    }

    private function intervenantParToken(string $token): ?Intervenant
    {
        if ($token === '') {
            return null;
        }

        return Intervenant::query()->where('invitation_token', $token)->first();
    }
}
