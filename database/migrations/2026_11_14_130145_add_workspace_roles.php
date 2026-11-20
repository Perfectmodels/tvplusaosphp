<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (array_keys(config('workspaces.roles')) as $name) {
            Role::findOrCreate($name, 'web');
        }
        User::query()->whereDoesntHave('roles')->whereIn('role', ['admin', 'chef_edition', 'journaliste', 'presentateur', 'habilleur', 'lecture', 'receptionniste', 'chef_plateau', 'chef_redaction', 'directeur_general'])->eachById(function (User $user): void {
            $user->assignRole($user->role);
        });
    }

    public function down(): void
    {
        // Les rôles et leurs affectations restent conservés en cas de retour applicatif.
    }
};
