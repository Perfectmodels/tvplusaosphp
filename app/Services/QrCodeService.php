<?php

namespace App\Services;

use App\Models\Intervenant;
use App\Models\IntervenantQrCode;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    protected string $jwtSecret;
    protected string $jwtAlgo = 'HS256';

    public function __construct()
    {
        $this->jwtSecret = env('TVPLUS_QR_JWT_SECRET', 'tvplus-qr-secret-change-in-production');
    }

    public function generate(Intervenant|string $intervenantCode, int $ttlHours = 720): IntervenantQrCode
    {
        if ($intervenantCode instanceof Intervenant) {
            $intervenant = $intervenantCode;
        } else {
            $intervenant = Intervenant::where('code', $intervenantCode)->firstOrFail();
        }

        $payload = [
            'iss' => config('app.name', 'TV+AOS'),
            'sub' => $intervenant->id,
            'code' => $intervenant->code,
            'iat' => now()->timestamp,
            'exp' => now()->addHours($ttlHours)->timestamp,
            'jti' => Str::uuid()->toString(),
            'context' => [
                'nom' => $intervenant->last_name,
                'prenom' => $intervenant->first_name,
                'type' => $intervenant->role_type,
            ],
        ];

        $token = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgo);

        return $intervenant->qrCodes()->create([
            'token' => $token,
            'payload' => $payload,
            'expires_at' => now()->addHours($ttlHours),
            'is_active' => true,
        ]);
    }

    public function verify(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgo));
            $payload = (array)$decoded;

            $qrCode = IntervenantQrCode::where('token', $token)->first();
            if (!$qrCode || !$qrCode->is_active || $qrCode->expires_at->isPast()) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function generateImage(IntervenantQrCode $qrCode, int $size = 512): string
    {
        return QrCode::size($size)
            ->margin(2)
            ->errorCorrection('H')
            ->backgroundColor(255, 255, 255)
            ->color(0, 26, 65)
            ->generate($qrCode->token);
    }

    public function revoke(IntervenantQrCode $qrCode): bool
    {
        return $qrCode->update([
            'is_active' => false,
            'used_at' => now(),
        ]);
    }
}
