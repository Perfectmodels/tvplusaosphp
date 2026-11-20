<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceActivity extends Model
{
    protected $fillable = ['workspace_dossier_id', 'actor_id', 'actor_name', 'kind', 'message'];

    protected $casts = ['workspace_dossier_id' => 'integer', 'actor_id' => 'integer'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(WorkspaceDossier::class, 'workspace_dossier_id');
    }
}
