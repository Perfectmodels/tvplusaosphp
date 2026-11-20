<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conference extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'scheduled_at',
        'duration_minutes',
        'location',
        'video_conference_url',
        'status',
        'summary',
        'agenda',
        'outcome',
        'organizer_id',
        'related_emission_id',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'agenda' => 'array',
        'metadata' => 'array',
        'type' => 'enum:redaction,technique,production,management,urgent,autre',
        'status' => 'enum:scheduled,in_progress,completed,cancelled,postponed',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function relatedEmission(): BelongsTo
    {
        return $this->belongsTo(Emission::class, 'related_emission_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conference_users')
            ->withPivot(['role', 'attended_at', 'left_at'])
            ->withTimestamps();
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ConferenceDecision::class);
    }
}
