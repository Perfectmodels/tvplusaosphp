<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'profile_photo_path',
        'phone',
        'role',
        'department',
        'is_active',
        'password_changed_at',
        'journaliste_id',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'             => 'hashed',
        'is_active'            => 'boolean',
        'password_changed_at'  => 'datetime',
    ];

    public function journalistes(): HasMany
    {
        return $this->hasMany(Journaliste::class, 'user_id');
    }

    /** Miroir principal: 1 User = 0 ou 1 Journaliste MAX (hasOne BelongsTo coté journaliste user_id) */
    public function journaliste(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Journaliste::class, 'user_id');
    }

    /**
     * Journaliste rattaché à cet utilisateur — résolution ROBUSTE.
     *
     * Deux liens coexistent en base :
     *   1. `users.journaliste_id`  → lien explicite posé par le CRUD Utilisateurs
     *   2. `journalistes.user_id`  → miroir historique (relation `journaliste()`)
     * On privilégie (1), puis on retombe sur (2).
     */
    public function journalisteLie(): ?Journaliste
    {
        try {
            if (! empty($this->journaliste_id)) {
                $j = Journaliste::find((int) $this->journaliste_id);
                if ($j) {
                    return $j;
                }
            }
        } catch (\Throwable) {
        }

        try {
            return $this->journaliste;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Auteur de la création du compte utilisateur */
    public function createdByUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getNomCompletAttribute(): string
    {
        return trim($this->name) ?: trim($this->journaliste?->nom_complet ?? '') ?: 'Utilisateur sans nom';
    }

    public function getInitialesAttribute(): string
    {
        $parts = explode(' ', trim((string)$this->name));
        $i = '';
        foreach ($parts as $p) {
            if (mb_strlen($p) > 0) { $i .= mb_strtoupper(mb_substr($p, 0, 1)); }
        }
        return $i ?: 'U';
    }

    public function getPhotoUrlAttribute(): string
    {
        if (!empty($this->profile_photo_path)) {
            return (string)$this->profile_photo_path;
        }
        if ($this->journaliste && !empty($this->journaliste->photoUrl)) {
            return (string)$this->journaliste->photoUrl;
        }
        $n = urlencode($this->initiales);
        return "https://ui-avatars.com/api/?name={$n}&background=001a41&color=ffffff&size=256";
    }

    // =====================================================================
    // SCOPES utiles pour index filtrable
    // =====================================================================

    public function scopeRecherche($query, string $q)
    {
        $s = '%' . mb_strtolower(trim($q)) . '%';
        return $query->whereRaw('LOWER(name) LIKE ?', [$s])
            ->orWhereRaw('LOWER(COALESCE(email,\'\')) LIKE ?', [$s])
            ->orWhereRaw('LOWER(COALESCE(phone,\'\')) LIKE ?', [$s]);
    }

    public function scopeJournalistesLies($query)
    {
        return $query->whereNotNull('journaliste_id');
    }

    public function scopeStandalone($query)
    {
        return $query->whereNull('journaliste_id');
    }

    public function scopeByRole($query, string $roleName)
    {
        return $query->whereHas('roles', fn($qq) => $qq->where('name', $roleName));
    }

    public function conferences(): BelongsToMany
    {
        return $this->belongsToMany(Conference::class, 'conference_users');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ConferenceDecision::class, 'taken_by');
    }
}
