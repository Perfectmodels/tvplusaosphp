<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        if (! $roleId) {
            return;
        }
        $permissions = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', config('role_access.defaults.admin'))->pluck('id');
        foreach ($permissions as $permissionId) {
            $inserted = DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            if ($inserted) {
                DB::table('role_access_matrix_seeds')->insertOrIgnore(['record_type' => 'admin_all_permission', 'record_key' => $roleId.':'.$permissionId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (DB::table('role_access_matrix_seeds')->where('record_type', 'admin_all_permission')->pluck('record_key') as $key) {
            [$roleId, $permissionId] = array_map('intval', explode(':', $key));
            DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', $permissionId)->delete();
        }
        DB::table('role_access_matrix_seeds')->where('record_type', 'admin_all_permission')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
