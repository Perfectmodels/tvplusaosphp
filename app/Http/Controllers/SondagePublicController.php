<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatutSondageEnum;
use App\Http\Requests\VoteSondageRequest;
use App\Models\Sondage;
use App\Models\SondageCandidat;
use App\Models\VoteSondage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SondagePublicController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $sondage = $this->findSondage($token);
        abort_if($sondage->statut === StatutSondageEnum::Brouillon, 404);

        $voterId = $request->cookie($this->cookieName($sondage));
        $aVote = is_string($voterId)
            && $sondage->votes()->where('voter_id', $voterId)->exists();

        return response()->view('sondage.show', [
            'sondage' => $sondage,
            'candidats' => $sondage->resultats(),
            'aVote' => $aVote,
        ])->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function vote(VoteSondageRequest $request, string $token): RedirectResponse
    {
        $sondage = $this->findSondage($token);
        abort_unless($sondage->estOuvert(), 403, 'Ce sondage est fermé.');

        $candidat = $sondage->candidats()->findOrFail($request->validated('candidat_id'));

        $cookieName = $this->cookieName($sondage);
        $voterId = $request->cookie($cookieName);
        $nouveauCookie = false;

        if (! is_string($voterId) || ! Str::isUuid($voterId)) {
            $voterId = (string) Str::uuid();
            $nouveauCookie = true;
        }

        $voterHash = hash_hmac(
            'sha256',
            $voterId.'|'.(string) $request->userAgent(),
            (string) config('app.key'),
        );

        try {
            DB::transaction(function () use ($sondage, $candidat, $voterId, $voterHash): void {
                VoteSondage::create([
                    'sondage_id' => $sondage->id,
                    'candidat_id' => $candidat->id,
                    'voter_id' => $voterId,
                    'voter_hash' => $voterHash,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()
                ->route('sondage.show', $token)
                ->with('error', 'Vous avez déjà voté à ce sondage.');
        }

        $redirect = redirect()
            ->route('sondage.show', $token)
            ->with('success', 'Votre vote a été enregistré. Merci !');

        if ($nouveauCookie) {
            Cookie::queue(Cookie::make($cookieName, $voterId, 60 * 24 * 30, null, null, true, true, 'lax'));
        }

        return $redirect;
    }

    public function resultats(string $token): JsonResponse
    {
        $sondage = $this->findSondage($token);
        abort_if($sondage->statut === StatutSondageEnum::Brouillon, 404);

        $candidats = $sondage->resultats()->map(fn (SondageCandidat $candidat): array => [
            'id' => $candidat->id,
            'nom' => $candidat->nom,
            'votes' => (int) $candidat->votes_count,
        ])->values();

        return response()->json([
            'question' => $sondage->question,
            'statut' => $sondage->statut->value,
            'total' => $candidats->sum('votes'),
            'candidats' => $candidats,
        ])->header('Cache-Control', 'no-store');
    }

    private function findSondage(string $token): Sondage
    {
        return Sondage::query()
            ->where('token', $token)
            ->with('emission')
            ->firstOrFail();
    }

    private function cookieName(Sondage $sondage): string
    {
        return 'sondage_voter_'.$sondage->id;
    }
}
