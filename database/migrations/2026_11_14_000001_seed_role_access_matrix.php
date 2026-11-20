<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_access_matrix_seeds', function (Blueprint $table): void {
            $table->string('record_type', 32);
            $table->string('record_key', 159);
            $table->primary(['record_type', 'record_key']);
        });

        $now = now();
        $roles = config('workspaces.roles');
        $modules = config('role_access.modules');
        $defaults = config('role_access.defaults');

        foreach ($roles as $name => $label) {
            $exists = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->exists();
            DB::table('roles')->insertOrIgnore([
                'name' => $name,
                'label' => $label,
                'guard_name' => 'web',
                'level' => 0,
                'is_assignable' => $name !== 'superadmin',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (! $exists) {
                $this->trackSeed('role', $name);
            }
        }

        foreach ($modules as $module => $definition) {
            foreach ($definition['permissions'] as $name => $label) {
                $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
                DB::table('permissions')->insertOrIgnore([
                    'name' => $name,
                    'label' => $label,
                    'guard_name' => 'web',
                    'module' => $module,
                    'group' => $definition['label'],
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if (! $exists) {
                    $this->trackSeed('permission', $name);
                }
            }
        }

        foreach ($defaults as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
            foreach ($permissionNames as $permissionName) {
                $permissionId = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->value('id');
                if ($roleId && $permissionId && ! DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists()) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                    $this->trackSeed('role_permission', $roleId.':'.$permissionId);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_access_matrix_seeds')) {
            return;
        }

        foreach (DB::table('role_access_matrix_seeds')->where('record_type', 'role_permission')->pluck('record_key') as $key) {
            [$roleId, $permissionId] = array_map('intval', explode(':', $key, 2));
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }

        foreach (DB::table('role_access_matrix_seeds')->where('record_type', 'permission')->pluck('record_key') as $name) {
            $permission = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            if ($permission
                && ! DB::table('role_has_permissions')->where('permission_id', $permission->id)->exists()
                && ! DB::table('model_has_permissions')->where('permission_id', $permission->id)->exists()) {
                DB::table('permissions')->where('id', $permission->id)->delete();
            }
        }

        foreach (DB::table('role_access_matrix_seeds')->where('record_type', 'role')->pluck('record_key') as $name) {
            $role = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->first();
            if ($role
                && ! DB::table('role_has_permissions')->where('role_id', $role->id)->exists()
                && ! DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
                DB::table('roles')->where('id', $role->id)->delete();
            }
        }

        Schema::drop('role_access_matrix_seeds');
    }

    private function trackSeed(string $type, string $key): void
    {
        DB::table('role_access_matrix_seeds')->insertOrIgnore([
            'record_type' => $type,
            'record_key' => $key,
        ]);
    }
};
