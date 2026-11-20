<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceAttachment extends Model
{
    protected $fillable = ['workspace_dossier_id', 'uploaded_by', 'name', 'path', 'mime_type', 'size'];

    protected $casts = ['workspace_dossier_id' => 'integer', 'uploaded_by' => 'integer', 'size' => 'integer'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(WorkspaceDossier::class, 'workspace_dossier_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
