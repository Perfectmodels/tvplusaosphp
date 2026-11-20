<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRolesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRoles(string ...$roles): User
    {
        $user = User::factory()->create();
        foreach ($roles as $roleName) {
            $user->assignRole(Role::findOrCreate($roleName, 'web'));
        }

        return $user;
    }

    public function test_admin_reçoit_la_vue_globale_et_le_journal_des_activites(): void
    {
        $admin = $this->userWithRoles('admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Administrateur')
            ->assertSee('Vue administrateur')
            ->assertSee(route('conducteurs.redaction.index'), false)
            ->assertSee(route('conducteurs.emission.index'), false)
            ->assertSee(route('veille.sujets.index'), false)
            ->assertSee(route('users.index'), false)
            ->assertSee(route('admin.activity-logs'), false)
            ->assertDontSee('id="edition-filter-panel"', false);
    }

    public function test_un_utilisateur_multi_role_peut_choisir_une_fonction_assignee(): void
    {
        $user = $this->userWithRoles('journaliste', 'presentateur');

        $this->actingAs($user)
            ->get(route('dashboard', ['role' => 'presentateur']))
            ->assertOk()
            ->assertSee('Présentateur')
            ->assertSee('dashboard-role');
    }

    public function test_un_utilisateur_ne_peut_pas_choisir_un_role_non_attribue(): void
    {
        $user = $this->userWithRoles('journaliste');

        $this->actingAs($user)
            ->get(route('dashboard', ['role' => 'chef_redaction']))
            ->assertForbidden();
    }

    public function test_un_non_admin_ne_peut_pas_lire_le_journal_global(): void
    {
        $journaliste = $this->userWithRoles('journaliste');

        $this->actingAs($journaliste)
            ->get(route('admin.activity-logs'))
            ->assertForbidden();
    }

    public function test_une_mutation_authentifiee_est_journalisee_sans_enregistrer_ses_valeurs(): void
    {
        $admin = $this->userWithRoles('admin');

        $this->actingAs($admin)
            ->post(route('veille.sources.store'), [
                'name' => 'Source auditée',
                'url' => 'https://93.184.215.14/audit-feed.xml',
                'type' => 'rss',
                'frequency' => '15min',
            ])
            ->assertRedirect();

        $activity = ActivityLog::query()->where('route_name', 'veille.sources.store')->latest('created_at')->firstOrFail();

        $this->assertSame($admin->id, $activity->actor_id);
        $this->assertSame('post', $activity->event);
        $this->assertContains('name', $activity->request_fields);
        $this->assertContains('url', $activity->request_fields);
        $this->assertStringNotContainsString('Source auditée', json_encode($activity->request_fields));
    }
}
