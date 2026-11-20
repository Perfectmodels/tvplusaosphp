<?php

namespace App\Http\Controllers;

use App\Models\Conducteur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class EmissionInteractionController extends Controller
{
    public function show(string $token): View
    {
        $conducteur = $this->findConducteur($token);

        return view('emission-interactions.show', compact('conducteur'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $conducteur = $this->findConducteur($token);
        $validated = $request->validate([
            'nom' => ['nullable', 'string', 'max:100'],
            'destinataire' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:2', 'max:1000'],
        ], [
            'message.required' => 'Écrivez votre message avant de l’envoyer.',
            'message.min' => 'Votre message doit contenir au moins 2 caractères.',
            'message.max' => 'Votre message ne peut pas dépasser 1 000 caractères.',
        ]);

        $conducteur->emissionInteractionMessages()->create($validated);

        return redirect()
            ->route('emission-interactions.show', $token)
            ->with('success', 'Votre message a été transmis à l’équipe de l’émission.');
    }

    public function generateQr(Conducteur $conducteur): RedirectResponse
    {
        $this->ensureCanManageQr($conducteur);
        $this->ensureToken($conducteur);

        return redirect()
            ->route('conducteurs.emission.show', $conducteur)
            ->with('download_public_qr', true)
            ->with('success', 'QR code public généré.');
    }

    public function showQr(Conducteur $conducteur): Response
    {
        $this->ensureCanManageQr($conducteur);
        $token = $this->ensureToken($conducteur);

        return $this->qrResponse($conducteur, $token, false);
    }

    public function downloadQr(Conducteur $conducteur): Response
    {
        $this->ensureCanManageQr($conducteur);
        $token = $this->ensureToken($conducteur);

        return $this->qrResponse($conducteur, $token, true);
    }

    private function ensureCanManageQr(Conducteur $conducteur): void
    {
        abort_if((string) $conducteur->type !== 'emission', 404);
        Gate::authorize('updateEnTete', $conducteur);
    }

    private function ensureToken(Conducteur $conducteur): string
    {
        return DB::transaction(function () use ($conducteur): string {
            $lockedConducteur = Conducteur::query()
                ->lockForUpdate()
                ->findOrFail($conducteur->getKey());

            if (empty($lockedConducteur->public_interaction_token)) {
                $lockedConducteur->forceFill([
                    'public_interaction_token' => $this->newToken(),
                ])->save();
            }

            return (string) $lockedConducteur->public_interaction_token;
        });
    }

    private function qrResponse(Conducteur $conducteur, string $token, bool $download): Response
    {
        $conducteur->loadMissing('emission');
        $emissionSlug = Str::slug((string) ($conducteur->emission?->nom ?? 'emission')) ?: 'emission';
        $dateEdition = $conducteur->date_edition?->format('Ymd') ?: 'edition';
        $filename = "qr-{$emissionSlug}-{$dateEdition}.svg";
        $url = route('emission-interactions.show', $token);

        $svg = (string) QrCode::size(560)
            ->margin(2)
            ->errorCorrection('H')
            ->backgroundColor(255, 255, 255)
            ->color(0, 26, 65)
            ->generate($url);

        $disposition = $download ? 'attachment' : 'inline';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function findConducteur(string $token): Conducteur
    {
        return Conducteur::query()
            ->where('type', 'emission')
            ->where('public_interaction_token', $token)
            ->with('emission')
            ->firstOrFail();
    }

    private function newToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Conducteur::query()->where('public_interaction_token', $token)->exists());

        return $token;
    }
}
