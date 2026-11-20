<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutEvenementEditorialEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'created_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'status' => StatutEvenementEditorialEnum::class,
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function sourcesVeille(): BelongsToMany
    {
        return $this->belongsToMany(VeilleSubject::class, 'editorial_event_veille_subject')
            ->withPivot('linked_by')
            ->withTimestamps();
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(EditorialVerification::class);
    }

    public function sujets(): HasMany
    {
        return $this->hasMany(EditorialStory::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejetePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
