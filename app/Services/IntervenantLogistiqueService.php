<?php

namespace App\Services;

use App\Models\Intervenant;
use App\Models\IntervenantQrCode;
use App\Models\IntervenantScanLog;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntervenantLogistiqueService
{
    protected QrCodeService $qrCodeService;

    protected array $validPostes = [
        'accueil',
        'maquillage',
        'costume',
        'repetition',
        'studio',
        'cantine',
        'sortie',
        'admin',
    ];

    protected array $posteToPivotColumn = [
        'accueil'    => 'heure_arrive',
        'maquillage' => 'heure_maq_fin',
        'costume'    => 'heure_maq_fin',
        'repetition' => 'heure_plateau',
        'studio'     => 'heure_plateau',
        'plateau'    => 'heure_plateau',
    ];

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    public function scan(string $poste, string $intervenantCodeOrToken, ?int $conducteurId = null): array
    {
        if (!in_array($poste, $this->validPostes, true) && !isset($this->posteToPivotColumn[$poste])) {
            return [
                'success' => false,
                'message' => "Poste invalide: {$poste}",
                'code' => 'invalid_poste',
            ];
        }

        $intervenant = null;
        $qrPayload = null;

        if (str_starts_with($intervenantCodeOrToken, 'eyJ')) {
            $qrPayload = $this->qrCodeService->verify($intervenantCodeOrToken);

            if (!$qrPayload) {
                return [
                    'success' => false,
                    'message' => 'QR Code invalide ou expiré',
                    'code' => 'invalid_qr',
                ];
            }

            $intervenant = Intervenant::find($qrPayload['sub']);
        } else {
            $intervenant = Intervenant::where('code', $intervenantCodeOrToken)->first();
        }

        if (!$intervenant) {
            return [
                'success' => false,
                'message' => 'Intervenant introuvable',
                'code' => 'intervenant_not_found',
            ];
        }

        if (!$intervenant->is_active) {
            return [
                'success' => false,
                'message' => 'Intervenant inactif',
                'code' => 'intervenant_inactive',
            ];
        }

        $userId = Auth::check() ? Auth::id() : null;
        $now = now();

        $qrCodeId = null;
        if ($qrPayload) {
            $qrCodeId = IntervenantQrCode::where('token', $intervenantCodeOrToken)->value('id');
        }

        $log = IntervenantScanLog::create([
            'intervenant_id' => $intervenant->id,
            'poste' => $poste,
            'scanned_at' => $now,
            'qr_code_id' => $qrCodeId,
            'scanned_by_user_id' => $userId,
            'conducteur_id' => $conducteurId,
            'metadata' => [
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ],
        ]);

        $intervenant->update(['last_scan_at' => $now]);

        $pivotUpdated = [];
        $pivotColumn = $this->posteToPivotColumn[$poste] ?? null;
        if ($pivotColumn) {
            $sequencesQuery = DB::table('sequence_intervenants')
                ->where('intervenant_id', $intervenant->id)
                ->whereNull($pivotColumn);

            if ($conducteurId) {
                $sequencesQuery->whereIn('sequence_id', function ($q) use ($conducteurId) {
                    $q->select('id')
                        ->from('sequences')
                        ->where('conducteur_id', $conducteurId);
                });
            } else {
                $sequencesQuery->whereIn('sequence_id', function ($q) {
                    $q->select('id')
                        ->from('sequences')
                        ->whereDate('date_diffusion', '>=', now()->subDay()->toDateString())
                        ->orWhereNull('date_diffusion');
                });
            }

            $affected = $sequencesQuery->update([
                $pivotColumn => $now,
                'scanned_by_user_id' => $userId,
                'updated_at' => $now,
            ]);

            if ($affected > 0) {
                $pivotUpdated = [
                    'column' => $pivotColumn,
                    'rows_affected' => $affected,
                ];
            }
        }

        Log::info("[Logistique] Scan {$poste}: {$intervenant->code} {$intervenant->last_name}" . ($conducteurId ? " (conducteur #{$conducteurId})" : ''));

        return [
            'success' => true,
            'message' => "Scan {$poste} OK pour {$intervenant->first_name} {$intervenant->last_name}",
            'intervenant' => $intervenant->only(['id', 'code', 'first_name', 'last_name', 'role_type', 'organization']),
            'poste' => $poste,
            'scanned_at' => $log->scanned_at,
            'conducteur_id' => $conducteurId,
            'pivot_updated' => $pivotUpdated,
            'previous_scan' => IntervenantScanLog::where('intervenant_id', $intervenant->id)
                ->where('id', '<', $log->id)
                ->latest('scanned_at')
                ->first(['poste', 'scanned_at'])
                ?->toArray(),
        ];
    }

    public function getJourney(Intervenant $intervenant, ?\DateTimeInterface $date = null): array
    {
        $date = $date ?? now();

        return IntervenantScanLog::where('intervenant_id', $intervenant->id)
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at')
            ->get(['poste', 'scanned_at'])
            ->toArray();
    }
}
