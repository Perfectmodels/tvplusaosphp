<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VeilleRemix extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'source_id',
        'user_id',
        'type',
        'tone',
        'title',
        'content',
        'format',
        'language',
        'ai_model',
        'prompt_used',
        'status',
        'metadata',
        'published_at',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'published_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(VeilleSubject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceVeille::class);
    }
}
