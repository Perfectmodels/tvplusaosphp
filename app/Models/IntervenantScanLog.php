<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosteScanEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracé de chaque scan — SPEC §7.2 `intervenant_scan_logs`
 */
class IntervenantScanLog extends Model
{
    use HasFactory;

    protected $table = 'intervenants_scan_logs';

    protected $fillable = [
        'intervenant_id',
        'qr_code_id',
        'conducteur_id',
        'scanned_by_user_id',
        'poste',
        'scanned_at',
        'scan_method',
        'sequence_intervenant_id',
        'parcours_intervenant_id',
        'etat_resultat',
        'metadata',
        'ip_terminal',
        'user_agent',
    ];

    protected $casts = [
        'poste' => PosteScanEnum::class,
        'scanned_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'scan_method' => 'qr_photo',
        'etat_resultat' => 'ok',
    ];

    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(Intervenant::class);
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(IntervenantQrCode::class, 'qr_code_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    public function scannerBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class);
    }

    public function sequenceIntervenant(): BelongsTo
    {
        return $this->belongsTo(SequenceIntervenantPivot::class, 'sequence_intervenant_id');
    }

    public function parcours(): BelongsTo
    {
        return $this->belongsTo(ParcoursIntervenant::class, 'parcours_intervenant_id');
    }
}
