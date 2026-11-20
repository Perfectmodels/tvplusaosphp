<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceDossier extends Model
{
    protected $fillable = ['source_key', 'editorial_story_id', 'sequence_id', 'merged_into_id'];

    protected $casts = ['editorial_story_id' => 'integer', 'sequence_id' => 'integer', 'merged_into_id' => 'integer'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(EditorialStory::class, 'editorial_story_id');
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_dossier_user')->withPivot(['is_creator', 'is_assignee', 'is_invited'])->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkspaceTask::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkspaceComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkspaceAttachment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkspaceActivity::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WorkspaceNotification::class);
    }

    public function getTitleAttribute(): string
    {
        return $this->story?->title ?? $this->sequence?->title ?? 'Source supprimée';
    }

    public function getSourceAvailableAttribute(): bool
    {
        return $this->story !== null || $this->sequence !== null;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->checkPermissionTo('workspace.view_all', 'web') ? $query
            : $query->whereHas('members', fn ($member) => $member->where('users.id', $user->id));
    }
}
