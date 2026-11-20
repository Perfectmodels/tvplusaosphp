<?php

namespace Tests\Feature;

use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\DigitalPublication;
use App\Models\EditorialStory;
use App\Models\Emission;
use App\Models\Sequence;
use App\Models\User;
use App\Models\WorkspaceDossier;
use App\Services\RoleAccessService;
use App\Services\RoleDashboardService;
use App\Services\SharedWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdministratorWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    #[DataProvider('views')]
    public function test_admin_ouvre_toutes_les_vues_sans_s_attribuer_les_roles_correspondants(string $role): void
    {
        $admin = $this->actor();
        $this->actingAs($admin)->get(route('dashboard', ['role' => $role]))->assertOk()->assertSee(config('workspaces.roles.'.$role));
        $this->assertSame(['admin'], $admin->fresh()->getRoleNames()->all());
    }

    public static function views(): array
    {
        return array_map(fn ($role) => [$role], ['chef_edition', 'chef_redaction', 'journaliste', 'realisateur', 'community_manager', 'correspondant', 'presentateur', 'monteur', 'documentaliste', 'responsable_administratif', 'directeur_general', 'admin', 'superadmin', 'habilleur', 'chef_plateau', 'receptionniste', 'lecture']);
    }

    public function test_la_vue_admin_reste_la_vue_par_defaut_et_le_menu_propose_tous_les_metiers(): void
    {
        $admin = $this->actor();
        $roles = app(RoleDashboardService::class)->roles($admin);
        $this->assertSame('admin', $roles[0]['value']);
        $this->assertCount(count(config('workspaces.roles')), $roles);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('Tableau de bord — Administrateur');
    }

    public function test_admin_possede_toutes_les_permissions_configurees_et_ne_peut_pas_les_perdre_par_la_matrice(): void
    {
        $admin = $this->actor();
        $access = app(RoleAccessService::class);
        $this->assertTrue($admin->hasAllPermissions($access->allPermissionNames()));
        $access->updateRole('admin', []);
        $this->assertTrue($admin->fresh()->hasAllPermissions($access->allPermissionNames()));
        $this->actingAs($admin)->get(route('conducteurs.redaction.create'))->assertOk();
        $this->get(route('conducteurs.emission.create'))->assertOk();
        $this->get(route('digital.create'))->assertOk();
    }

    public function test_la_vue_metier_admin_montre_les_donnees_de_toute_l_equipe(): void
    {
        $admin = $this->actor();
        $journaliste = $this->actor('journaliste');
        $cm = $this->actor('community_manager');
        $emission = Emission::create(['nom' => 'Journal des équipes']);
        $conducteur = Conducteur::create(['type' => 'redaction', 'date_edition' => today(), 'owner_id' => $journaliste->id, 'emission_id' => $emission->id]);
        Sequence::create(['conducteur_id' => $conducteur->id, 'title' => 'Sujet affecté à un collègue', 'type' => TypeSequenceEnum::Reportage, 'position' => 1, 'duration_seconds' => 90]);
        DigitalPublication::create(['title' => 'Publication de la rédaction digitale', 'content' => 'Préparation réelle', 'platform' => 'site', 'status' => 'draft', 'created_by' => $cm->id]);
        $this->actingAs($admin)->get(route('dashboard', ['role' => 'journaliste']))->assertOk()->assertSee('Sujet affecté à un collègue')->assertSee('Vue administrateur');
        $this->get(route('dashboard', ['role' => 'community_manager']))->assertOk()->assertSee('Publication de la rédaction digitale');
        $this->actingAs($this->actor('journaliste'))->get(route('dashboard'))->assertOk()->assertDontSee('Sujet affecté à un collègue');
    }

    public function test_les_taches_partagees_du_metier_sont_visibles_par_admin_meme_affectees_a_un_collegue(): void
    {
        $admin = $this->actor();
        $monteur = $this->actor('monteur');
        $story = EditorialStory::factory()->create(['created_by' => $admin->id]);
        $dossier = WorkspaceDossier::where('editorial_story_id', $story->id)->sole();
        app(SharedWorkspaceService::class)->createTask($dossier, $admin, ['title' => 'Montage confié à un collègue', 'type' => 'montage', 'assigned_to' => $monteur->id]);
        $this->actingAs($admin)->get(route('dashboard', ['role' => 'monteur']))->assertOk()->assertSee('Montage confié à un collègue');
    }

    public function test_le_champ_legacy_admin_ne_donne_pas_de_privileges_et_les_metiers_restent_limites(): void
    {
        $journaliste = $this->actor('journaliste');
        $journaliste->update(['role' => 'admin']);
        $this->actingAs($journaliste)->get(route('dashboard', ['role' => 'admin']))->assertForbidden();
        $this->get(route('dashboard', ['role' => 'realisateur']))->assertForbidden();
        $this->assertFalse($journaliste->hasPermissionTo('users.create'));
    }
}
