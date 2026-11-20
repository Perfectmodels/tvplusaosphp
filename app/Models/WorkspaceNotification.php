<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceNotification extends Model
{
    protected $fillable = ['workspace_dossier_id', 'user_id', 'message', 'read_at'];

    protected $casts = ['workspace_dossier_id' => 'integer', 'user_id' => 'integer', 'read_at' => 'datetime'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(WorkspaceDossier::class, 'workspace_dossier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
