<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $parcours_intervenant_id
 * @property int|null $intervenant_id
 * @property int|null $emission_id
 * @property int|null $conducteur_id
 * @property int|null $scanned_by_user_id
 * @property string $scan_method
 * @property string $result
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $scanned_at
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class IntervenantCheckinAttempt extends Model
{
    protected $fillable = [
        'parcours_intervenant_id',
        'intervenant_id',
        'emission_id',
        'conducteur_id',
        'scanned_by_user_id',
        'scan_method',
        'result',
        'reason',
        'scanned_at',
        'validated_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function parcours(): BelongsTo
    {
        return $this->belongsTo(ParcoursIntervenant::class, 'parcours_intervenant_id');
    }

    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(Intervenant::class);
    }

    public function emission(): BelongsTo
    {
        return $this->belongsTo(Emission::class);
    }

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class);
    }

    public function scannerBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }
}
