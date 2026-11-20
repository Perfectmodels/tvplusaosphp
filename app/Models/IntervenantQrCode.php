<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosteScanEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPEC §7.2 `intervenant_qr_codes` — 1 unique par Intervenant en général (hasOne)
 */
class IntervenantQrCode extends Model
{
    use HasFactory;

    protected $table = 'intervenants_qr_codes';

    protected $fillable = [
        'intervenant_id',
        'token',
        'payload',
        'generated_at',
        'revoked_at',
        'expires_at',
        'used_at',
        'is_active',
        'qr_png_storage_path',
        'qr_svg_storage_path',
    ];

    protected $casts = [
        'payload'       => 'array',
        'generated_at'  => 'datetime',
        'revoked_at'    => 'datetime',
        'expires_at'    => 'datetime',
        'used_at'       => 'datetime',
        'is_active'     => 'boolean',
    ];

    // =====================================================================
    // RELATIONS
    // =====================================================================

    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(Intervenant::class);
    }

    // =====================================================================
    // HELPERS RESSOURCES
    // =====================================================================

    public function getPngPublicUrl(): ?string
    {
        if (!empty($this->qr_png_storage_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->qr_png_storage_path);
        }
        return null;
    }

    public function getSvgPublicUrl(): ?string
    {
        if (!empty($this->qr_svg_storage_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->qr_svg_storage_path);
        }
        return null;
    }

    public function revoke(): void
    {
        $this->forceFill([
            'revoked_at' => now(),
            'is_active'  => false,
        ])->save();
    }
}
