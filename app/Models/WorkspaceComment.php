<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceComment extends Model
{
    protected $fillable = ['workspace_dossier_id', 'user_id', 'body'];

    protected $casts = ['workspace_dossier_id' => 'integer', 'user_id' => 'integer'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(WorkspaceDossier::class, 'workspace_dossier_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
