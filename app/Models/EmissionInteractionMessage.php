<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmissionInteractionMessage extends Model
{
    protected $fillable = [
        'conducteur_id',
        'nom',
        'destinataire',
        'message',
    ];

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class);
    }
}
