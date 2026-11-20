<?php

namespace App\Observers;

use App\Models\Intervenant;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Log;

class IntervenantObserver
{
    protected QrCodeService $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    public function created(Intervenant $intervenant): void
    {
        try {
            if (empty($intervenant->code)) {
                $intervenant->code = 'INT-' . str_pad($intervenant->id, 6, '0', STR_PAD_LEFT);
                $intervenant->saveQuietly();
            }

            $this->qrCodeService->generate($intervenant);

            Log::info("[Observer] Intervenant #{$intervenant->id} créé - QR Code généré.");
        } catch (\Exception $e) {
            Log::error("[Observer] Erreur création intervenant #{$intervenant->id}: {$e->getMessage()}");
        }
    }

    public function updated(Intervenant $intervenant): void
    {
        if ($intervenant->wasChanged('is_active') && !$intervenant->is_active) {
            $intervenant->qrCodes()->update(['is_active' => false]);
            Log::info("[Observer] Intervenant #{$intervenant->id} désactivé - QR Codes révoqués.");
        }
    }

    public function deleting(Intervenant $intervenant): void
    {
        $intervenant->qrCodes()->delete();
        $intervenant->scanLogs()->delete();
        Log::info("[Observer] Intervenant #{$intervenant->id} supprimé - logs & QR effacés.");
    }
}
