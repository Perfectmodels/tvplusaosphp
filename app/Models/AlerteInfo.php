<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NiveauAlerteEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlerteInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'message',
        'niveau',
        'actif',
        'created_by',
        'expire_at',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'niveau' => NiveauAlerteEnum::class,
            'actif' => 'boolean',
            'expire_at' => 'datetime',
            'ordre' => 'integer',
        ];
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estActive(): bool
    {
        return $this->actif
            && ($this->expire_at === null || $this->expire_at->isFuture());
    }

    /** @param Builder<AlerteInfo> $query */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('actif', true)
            ->where(function (Builder $q): void {
                $q->whereNull('expire_at')->orWhere('expire_at', '>', now());
            })
            ->orderByDesc('ordre')
            ->orderByDesc('id');
    }
}
