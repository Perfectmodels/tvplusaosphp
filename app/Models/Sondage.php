<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutSondageEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Sondage extends Model
{
    use HasFactory;

    protected $fillable = [
        'emission_id',
        'sequence_id',
        'created_by',
        'question',
        'token',
        'statut',
        'ouvert_at',
        'clos_at',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutSondageEnum::class,
            'ouvert_at' => 'datetime',
            'clos_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sondage $sondage): void {
            if (! empty($sondage->token)) {
                return;
            }

            do {
                $token = Str::random(48);
            } while (static::query()->where('token', $token)->exists());

            $sondage->token = $token;
        });
    }

    public function emission(): BelongsTo
    {
        return $this->belongsTo(Emission::class);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(Sequence::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function candidats(): HasMany
    {
        return $this->hasMany(SondageCandidat::class)->orderBy('ordre');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(VoteSondage::class);
    }

    public function estOuvert(): bool
    {
        return $this->statut === StatutSondageEnum::Ouvert;
    }

    /** @return Collection<int, SondageCandidat> */
    public function resultats(): Collection
    {
        return $this->candidats()->withCount('votes')->get();
    }

    public function totalVotes(): int
    {
        return $this->votes()->count();
    }

    public function scopeOuverts(Builder $query): void
    {
        $query->where('statut', StatutSondageEnum::Ouvert->value);
    }
}
