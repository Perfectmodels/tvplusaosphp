<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'event',
        'route_name',
        'route_uri',
        'http_method',
        'http_status',
        'subject_type',
        'subject_id',
        'route_parameters',
        'request_fields',
        'context',
        'created_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'subject_id' => 'integer',
        'route_parameters' => 'array',
        'request_fields' => 'array',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
