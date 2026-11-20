<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalPublication extends Model
{
    protected $fillable = ['title', 'content', 'platform', 'emission_id', 'status', 'created_by', 'approved_by', 'approved_at', 'scheduled_at', 'published_at', 'feedback', 'publication_url', 'editorial_story_id', 'source_sequence_id'];

    protected $casts = ['approved_at' => 'datetime', 'scheduled_at' => 'datetime', 'published_at' => 'datetime'];

    public function sourceSequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class, 'source_sequence_id');
    }

    public function sharedDossier(): ?WorkspaceDossier
    {
        if ($this->editorial_story_id) {
            return WorkspaceDossier::where('editorial_story_id', $this->editorial_story_id)->first();
        }
        if ($this->source_sequence_id) {
            $sequence = $this->sourceSequence;

            return $sequence?->editorial_story_id ? WorkspaceDossier::where('editorial_story_id', $sequence->editorial_story_id)->first() : WorkspaceDossier::where('sequence_id', $this->source_sequence_id)->first();
        }

        return null;
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function emission(): BelongsTo
    {
        return $this->belongsTo(Emission::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->hasAnyRole(['admin', 'superadmin', 'directeur_general', 'chef_redaction', 'chef_edition'])
            ? $query : $query->where('created_by', $user->id);
    }
}
