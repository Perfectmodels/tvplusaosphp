<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (config('role_access.modules.workspace.permissions') as $name => $label) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if ($exists) {
                continue;
            }
            $id = DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web', 'label' => $label, 'module' => 'workspace', 'group' => 'Espace métier partagé', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_access_matrix_seeds')->insertOrIgnore(['record_type' => 'shared_permission', 'record_key' => $name]);
            foreach (config('role_access.defaults') as $roleName => $permissions) {
                if (! in_array($name, $permissions, true)) {
                    continue;
                }
                $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
                if ($roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $roleId]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $names = DB::table('role_access_matrix_seeds')->where('record_type', 'shared_permission')->pluck('record_key');
        DB::table('permissions')->whereIn('name', $names)->where('guard_name', 'web')->delete();
        DB::table('role_access_matrix_seeds')->where('record_type', 'shared_permission')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
