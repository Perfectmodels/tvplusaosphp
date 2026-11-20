<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConferenceDecision extends Model
{
    use HasFactory;

    protected $fillable = [
        'conference_id',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'taken_by',
        'assigned_to',
        'related_subject_id',
        'related_sequence_id',
        'metadata',
    ];

    protected $casts = [
        'priority' => 'enum:basse,moyenne,haute,critique',
        'status' => 'enum:ouverte,en_cours,terminée,en_attente,abandonnée',
        'due_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function relatedSubject(): BelongsTo
    {
        return $this->belongsTo(VeilleSubject::class, 'related_subject_id');
    }

    public function relatedSequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class, 'related_sequence_id');
    }
}
