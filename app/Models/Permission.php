<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'description',
        'guard_name',
        'module',
        'group',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function scopeModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
