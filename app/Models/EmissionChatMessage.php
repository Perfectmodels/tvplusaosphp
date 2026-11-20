<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message de la discussion interne d'un conducteur d'émission.
 *
 * Un fil par conducteur, partagé entre les rôles (Chef d'édition,
 * Présentateur, Journalistes…) qui ont accès au conducteur.
 */
final class EmissionChatMessage extends Model
{
    protected $fillable = [
        'conducteur_id',
        'user_id',
        'message',
    ];

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Conducteur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
