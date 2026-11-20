<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string ...$roles): User
    {
        $user = User::factory()->create(['role' => $roles[0]]);
        $user->assignRole($roles);

        return $user;
    }

    public function test_permissions_cumulees_sont_l_union_des_roles_et_une_restriction_est_effective(): void
    {
        $user = $this->actor('journaliste', 'community_manager');
        $this->assertTrue($user->hasPermissionTo('dashboard.view', 'web'));

        Role::findByName('journaliste', 'web')->revokePermissionTo('dashboard.view');
        $this->assertTrue($user->fresh()->hasPermissionTo('dashboard.view', 'web'));

        Role::findByName('community_manager', 'web')->revokePermissionTo('dashboard.view');
        $this->assertFalse($user->fresh()->hasPermissionTo('dashboard.view', 'web'));
    }

    public function test_la_matrice_est_modifiable_par_un_admin_et_preserve_les_permissions_hors_matrice(): void
    {
        $admin = $this->actor('admin');
        $this->actingAs($admin)->get(route('utilisateurs.permissions'))->assertOk();

        $role = Role::findByName('journaliste', 'web');
        $legacy = Permission::create([
            'name' => 'legacy.read',
            'label' => 'Permission historique',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($legacy);

        $this->actingAs($admin)
            ->from(route('utilisateurs.permissions'))
            ->put(route('utilisateurs.permissions.update', 'journaliste'), [
                'permissions' => ['dashboard.view'],
            ])
            ->assertRedirect(route('utilisateurs.permissions'));

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('dashboard.view'));
        $this->assertTrue($role->hasPermissionTo('legacy.read'));
        $this->assertFalse($role->hasPermissionTo('conducteurs.view'));
    }

    public function test_un_admin_ne_peut_pas_modifier_le_role_superadmin(): void
    {
        $this->actingAs($this->actor('admin'))
            ->put(route('utilisateurs.permissions.update', 'superadmin'), ['permissions' => []])
            ->assertForbidden();
    }

    public function test_un_superadmin_conserve_les_permissions_systeme_protegees(): void
    {
        $this->actingAs($this->actor('superadmin'))
            ->from(route('utilisateurs.permissions'))
            ->put(route('utilisateurs.permissions.update', 'superadmin'), ['permissions' => []])
            ->assertRedirect(route('utilisateurs.permissions'));

        $role = Role::findByName('superadmin', 'web');
        $this->assertTrue($role->hasPermissionTo('dashboard.view'));
        $this->assertTrue($role->hasPermissionTo('role_access.manage'));
        $this->assertTrue($role->hasPermissionTo('users.assign_roles'));
    }

    public function test_un_utilisateur_peut_recevoir_plusieurs_roles_a_la_creation(): void
    {
        $admin = $this->actor('admin');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'mode' => 'manual',
                'role' => 'journaliste',
                'additional_roles' => ['community_manager'],
                'prenom' => 'Awa',
                'nom' => 'Ndiaye',
                'email' => 'awa.ndiaye@example.org',
                'is_active' => true,
            ])
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'awa.ndiaye@example.org')->firstOrFail();
        $this->assertTrue($user->hasAllRoles(['journaliste', 'community_manager']));
    }

    public function test_un_role_sans_droit_ne_peut_pas_consulter_le_tableau_de_bord(): void
    {
        $user = $this->actor('journaliste');
        Role::findByName('journaliste', 'web')->revokePermissionTo('dashboard.view');

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_le_tableau_de_bord_ne_revele_pas_de_donnees_sans_permission_de_lecture(): void
    {
        $user = $this->actor('journaliste');
        Role::findByName('journaliste', 'web')->revokePermissionTo('conducteurs.view');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('sections', fn ($sections) => $sections[0]['title'] === 'Accès aux données'
                && $sections[0]['items'] === []);
    }

    public function test_le_pointage_exige_aussi_le_droit_de_lecture_des_presences(): void
    {
        $user = $this->actor('admin');
        Role::findByName('admin', 'web')->revokePermissionTo('presence.view');

        $this->actingAs($user)->get(route('dashboard.pointage'))->assertForbidden();
    }
}
