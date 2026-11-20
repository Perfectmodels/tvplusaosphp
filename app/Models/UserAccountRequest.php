<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccountRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'civilite',
        'prenom',
        'nom',
        'email',
        'phone',
        'department',
        'fonction',
        'journaliste_id',
        'matricule',
        'requested_access_type',
        'motivation',
        'charter_accepted',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'created_user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'charter_accepted' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function journaliste(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '').' '.($this->nom ?? ''));
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
