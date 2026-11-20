<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutVerificationEditorialEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'editorial_event_id',
        'type',
        'claim',
        'status',
        'evidence',
        'evidence_url',
        'created_by',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'status' => StatutVerificationEditorialEnum::class,
        'checked_at' => 'datetime',
    ];

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(EditorialEvent::class, 'editorial_event_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
