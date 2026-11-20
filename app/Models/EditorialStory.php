<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutSujetEditorialEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EditorialStory extends Model
{
    use HasFactory;

    protected $fillable = [
        'editorial_event_id',
        'title',
        'summary',
        'angle',
        'status',
        'assigned_journaliste_id',
        'assigned_by',
        'assigned_at',
        'production_started_at',
        'submitted_for_validation_by',
        'submitted_for_validation_at',
        'created_by',
        'validated_by',
        'validated_at',
        'verification_exception_reason',
        'verification_exception_by',
        'verification_exception_at',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'status' => StatutSujetEditorialEnum::class,
        'assigned_at' => 'datetime',
        'production_started_at' => 'datetime',
        'submitted_for_validation_at' => 'datetime',
        'validated_at' => 'datetime',
        'verification_exception_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function sharedDossier(): HasOne
    {
        return $this->hasOne(WorkspaceDossier::class, 'editorial_story_id');
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(EditorialEvent::class, 'editorial_event_id');
    }

    public function journalisteAssigne(): BelongsTo
    {
        return $this->belongsTo(Journaliste::class, 'assigned_journaliste_id');
    }

    public function affectePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function soumisPourValidationPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_for_validation_by');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function exceptionAutoriseePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verification_exception_by');
    }

    public function rejetePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class, 'editorial_story_id');
    }
}
