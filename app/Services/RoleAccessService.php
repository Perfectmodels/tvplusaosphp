<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleAccessService
{
    public function modules(): array
    {
        return config('role_access.modules', []);
    }

    public function allPermissionNames(): array
    {
        return collect($this->modules())
            ->flatMap(static fn (array $module): array => array_keys($module['permissions']))
            ->values()
            ->all();
    }

    public function matrix(): Collection
    {
        $labels = config('workspaces.roles', []);
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys($labels))
            ->with('permissions:id,name')
            ->get()
            ->keyBy('name');

        return collect($labels)->map(function (string $label, string $name) use ($roles): array {
            $role = $roles->get($name);

            return [
                'name' => $name,
                'label' => $label,
                'permissions' => $role?->permissions->pluck('name')->all() ?? [],
                'assignable' => $name !== 'superadmin',
            ];
        })->values();
    }

    /**
     * @param  list<string>  $permissions
     */
    public function updateRole(string $roleName, array $permissions): void
    {
        $role = Role::findByName($roleName, 'web');
        $protected = config('role_access.protected_permissions', []);
        $unmanagedPermissions = $role->permissions()
            ->whereNotIn('name', $this->allPermissionNames())
            ->pluck('name')
            ->all();
        if ($roleName === 'admin') {
            $permissions = $this->allPermissionNames();
        }
        if ($roleName === 'superadmin') {
            $permissions = array_values(array_unique([...$permissions, ...$protected]));
        }

        $role->syncPermissions(array_values(array_unique([...$permissions, ...$unmanagedPermissions])));
    }

    public function canManage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    public function canEditRole(User $user, string $roleName): bool
    {
        return $this->canManage($user) && ($roleName !== 'superadmin' || $user->hasRole('superadmin'));
    }

    public function assertCanEditRole(User $user, string $roleName): void
    {
        if (! $this->canEditRole($user, $roleName)) {
            throw new AuthorizationException('Vous ne pouvez pas modifier les permissions de ce rôle.');
        }
    }
}
