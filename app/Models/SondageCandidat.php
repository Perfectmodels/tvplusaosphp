<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SondageCandidat extends Model
{
    use HasFactory;

    protected $fillable = [
        'sondage_id',
        'intervenant_id',
        'nom',
        'ordre',
    ];

    public function sondage(): BelongsTo
    {
        return $this->belongsTo(Sondage::class);
    }

    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(Intervenant::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(VoteSondage::class, 'candidat_id');
    }
}
