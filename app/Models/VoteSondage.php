<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoteSondage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sondage_id',
        'candidat_id',
        'voter_id',
        'voter_hash',
    ];

    public function sondage(): BelongsTo
    {
        return $this->belongsTo(Sondage::class);
    }

    public function candidat(): BelongsTo
    {
        return $this->belongsTo(SondageCandidat::class, 'candidat_id');
    }
}
