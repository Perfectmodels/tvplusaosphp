<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceTask extends Model
{
    protected $fillable = ['workspace_dossier_id', 'title', 'instructions', 'type', 'status', 'assigned_to', 'created_by', 'due_at', 'completed_at', 'blocked_reason'];

    protected $casts = ['workspace_dossier_id' => 'integer', 'assigned_to' => 'integer', 'created_by' => 'integer', 'due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(WorkspaceDossier::class, 'workspace_dossier_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
