<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EtapeParcoursIntervenantEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcoursIntervenantEvent extends Model
{
    protected $table = 'parcours_intervenant_events';

    protected $fillable = [
        'parcours_intervenant_id',
        'etape',
        'recorded_at',
        'triggered_by_user_id',
        'source',
        'notes',
    ];

    protected $casts = [
        'etape' => EtapeParcoursIntervenantEnum::class,
        'recorded_at' => 'datetime',
    ];

    public function parcours(): BelongsTo
    {
        return $this->belongsTo(ParcoursIntervenant::class, 'parcours_intervenant_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
