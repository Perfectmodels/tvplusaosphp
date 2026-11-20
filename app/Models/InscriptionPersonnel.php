<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscriptionPersonnel extends Model
{
    use HasFactory;

    protected $table = 'inscription_personnel';

    protected $fillable = [
        'civilite',
        'prenom',
        'nom',
        'email',
        'telephone',
        'department',
        'fonction',
        'motivation',
        'statut',
        'validated_by',
        'validated_at',
        'rejection_reason',
        'user_id',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public const STATUT_PENDING = 'pending';
    public const STATUT_APPROVED = 'approved';
    public const STATUT_REJECTED = 'rejected';

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNomCompletAttribute(): string
    {
        return trim(($this->civilite ? $this->civilite.' ' : '').$this->prenom.' '.$this->nom);
    }

    public function isPending(): bool
    {
        return $this->statut === self::STATUT_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->statut === self::STATUT_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->statut === self::STATUT_REJECTED;
    }
}
