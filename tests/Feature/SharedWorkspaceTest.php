<?php

namespace Tests\Feature;

use App\Enums\TypeEmissionEnum;
use App\Enums\TypeSequenceEnum;
use App\Models\Conducteur;
use App\Models\DigitalPublication;
use App\Models\EditorialStory;
use App\Models\Emission;
use App\Models\Journaliste;
use App\Models\Sequence;
use App\Models\User;
use App\Models\WorkspaceAttachment;
use App\Models\WorkspaceDossier;
use App\Models\WorkspaceTask;
use App\Services\SharedWorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SharedWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'journaliste'): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function dossier(User $owner): WorkspaceDossier
    {
        $story = EditorialStory::factory()->create(['created_by' => $owner->id, 'title' => 'Enquête commune réelle']);

        return WorkspaceDossier::where('editorial_story_id', $story->id)->sole();
    }

    private function sequence(User $owner, array $attributes = []): Sequence
    {
        $emission = Emission::create(['nom' => 'JT', 'title' => 'JT', 'slug' => 'jt-'.uniqid(), 'type' => TypeEmissionEnum::JT, 'duration_minutes' => 30, 'is_active' => true]);
        $conducteur = Conducteur::create(['emission_id' => $emission->id, 'nom' => 'Édition du jour', 'date_edition' => today(), 'owner_id' => $owner->id, 'type' => 'redaction', 'status' => 'Brouillon']);

        return Sequence::create(array_replace(['conducteur_id' => $conducteur->id, 'title' => 'Sujet partagé', 'type' => TypeSequenceEnum::Reportage, 'position' => 1, 'duration_seconds' => 90], $attributes));
    }

    #[DataProvider('roles')]
    public function test_chaque_metier_retrouve_un_dossier_partage_sans_gagner_de_droits_metier(string $role): void
    {
        $manager = $this->actor('chef_edition');
        $person = $this->actor($role);
        $dossier = $this->dossier($manager);
        app(SharedWorkspaceService::class)->addMember($dossier, $manager, $person);
        $this->actingAs($person)->get(route('workspace.show', $dossier))->assertOk()->assertSee($dossier->title)->assertSee('Production et antenne');
        $this->get(route('workspace.index'))->assertOk()->assertSee($dossier->title);
        if ($person->checkPermissionTo('editorial.view', 'web')) {
            $this->get(route('workspace.show', $dossier))->assertSee('Ouvrir la fiche éditoriale');
        }
        if (! in_array($role, ['chef_edition', 'chef_redaction', 'admin', 'superadmin'], true)) {
            $this->assertFalse($person->can('manageMembers', $dossier));
        }
    }

    public static function roles(): array
    {
        return array_map(fn ($r) => [$r], ['journaliste', 'chef_redaction', 'chef_edition', 'realisateur', 'community_manager', 'correspondant', 'presentateur', 'monteur', 'documentaliste', 'responsable_administratif', 'directeur_general', 'admin', 'superadmin', 'chef_plateau', 'habilleur', 'receptionniste', 'lecture']);
    }

    public function test_un_collegue_non_invite_ne_voit_ni_dossier_tache_commentaire_fichier_ou_notification(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $dossier = $this->dossier($owner);
        $this->actingAs($other)->get(route('workspace.show', $dossier))->assertForbidden();
        $this->get(route('workspace.state', $dossier))->assertForbidden();
        $this->get(route('workspace.index'))->assertOk()->assertDontSee('Enquête commune réelle');
        $this->post(route('workspace.comments.store', $dossier), ['body' => 'Intrusion'])->assertForbidden();
        $this->post(route('workspace.tasks.store', $dossier), ['title' => 'Intrusion', 'type' => 'terrain', 'assigned_to' => $other->id])->assertForbidden();
        $this->assertDatabaseCount('workspace_comments', 0);
        $this->assertDatabaseCount('workspace_tasks', 0);
    }

    public function test_un_relais_notifie_la_personne_et_seul_le_responsable_ou_le_chef_change_sa_tache(): void
    {
        $chef = $this->actor('chef_edition');
        $monteur = $this->actor('monteur');
        $journaliste = $this->actor();
        $dossier = $this->dossier($chef);
        $service = app(SharedWorkspaceService::class);
        $service->addMember($dossier, $chef, $journaliste);
        $this->actingAs($chef)->post(route('workspace.tasks.store', $dossier), ['title' => 'Monter le reportage', 'type' => 'montage', 'assigned_to' => $monteur->id, 'due_at' => now()->addHour()->format('Y-m-d\TH:i'), 'status' => 'done', 'created_by' => $journaliste->id])->assertRedirect();
        $task = WorkspaceTask::sole();
        $this->assertSame('todo', $task->status);
        $this->assertSame($chef->id, $task->created_by);
        $this->assertTrue($dossier->hasMember($monteur));
        $this->actingAs($monteur)->getJson(route('workspace.unread'))->assertOk()->assertJsonPath('unread', 2);
        $this->get(route('dashboard', ['role' => 'monteur']))->assertOk()->assertSee('Monter le reportage');
        $this->actingAs($journaliste)->patch(route('workspace.tasks.update', [$dossier, $task]), ['status' => 'done'])->assertForbidden();
        $this->actingAs($monteur)->patchJson(route('workspace.tasks.update', [$dossier, $task]), ['status' => 'blocked'])->assertUnprocessable()->assertJsonValidationErrors('blocked_reason');
        $this->patch(route('workspace.tasks.update', [$dossier, $task]), ['status' => 'blocked', 'blocked_reason' => 'Rushes manquants'])->assertRedirect();
        $this->assertSame('blocked', $task->fresh()->status);
        $this->patch(route('workspace.tasks.update', [$dossier, $task]), ['status' => 'done'])->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertNull($task->fresh()->blocked_reason);
        $this->assertSame('draft', $dossier->story->fresh()->status->value);
    }

    public function test_retirer_un_acces_cache_aussi_les_notifications_et_les_taches(): void
    {
        $chef = $this->actor('chef_edition');
        $user = $this->actor('monteur');
        $dossier = $this->dossier($chef);
        $service = app(SharedWorkspaceService::class);
        $service->addMember($dossier, $chef, $user);
        $service->createTask($dossier, $chef, ['title' => 'Mission retirée', 'type' => 'montage', 'assigned_to' => $user->id]);
        $this->actingAs($chef)->delete(route('workspace.members.destroy', [$dossier, $user]))->assertRedirect();
        $this->actingAs($user)->getJson(route('workspace.unread'))->assertJsonPath('unread', 0);
        $this->get(route('workspace.notifications'))->assertOk()->assertDontSee($dossier->title);
        $this->get(route('dashboard', ['role' => 'monteur']))->assertOk()->assertDontSee('Mission retirée');
        $this->get(route('workspace.show', $dossier))->assertForbidden();
    }

    public function test_les_affectations_reelles_actualisent_les_acces_automatiques_sans_retirer_les_invites(): void
    {
        $chef = $this->actor('chef_edition');
        $old = $this->actor();
        $new = $this->actor();
        $j1 = Journaliste::create(['nom' => 'Ancien', 'prenom' => 'Journaliste', 'user_id' => $old->id]);
        $j2 = Journaliste::create(['nom' => 'Nouveau', 'prenom' => 'Journaliste', 'user_id' => $new->id]);
        $dossier = $this->dossier($chef);
        $dossier->story->update(['assigned_journaliste_id' => $j1->id]);
        $this->assertTrue($dossier->hasMember($old));
        $dossier->story->update(['assigned_journaliste_id' => $j2->id]);
        $this->assertFalse($dossier->hasMember($old));
        $this->assertTrue($dossier->hasMember($new));
        app(SharedWorkspaceService::class)->addMember($dossier, $chef, $old);
        $dossier->story->update(['assigned_journaliste_id' => null]);
        $this->assertTrue($dossier->hasMember($old));
        $this->assertFalse($dossier->hasMember($new));
    }

    public function test_les_sujets_manuels_sont_relies_et_les_elements_de_structure_sont_ignores(): void
    {
        $owner = $this->actor();
        $sequence = $this->sequence($owner, ['notes_perso' => 'CONFIDENTIEL PRESENTATEUR']);
        $this->sequence($owner, ['type' => TypeSequenceEnum::Generique]);
        $dossier = WorkspaceDossier::sole();
        $this->assertSame($sequence->id, $dossier->sequence_id);
        $this->actingAs($owner)->get(route('workspace.show', $dossier))->assertOk()->assertSee('90 s prévues')->assertDontSee('CONFIDENTIEL PRESENTATEUR');
        $this->getJson(route('workspace.state', $dossier))->assertOk()->assertDontSee('CONFIDENTIEL PRESENTATEUR');
        $this->artisan('workspace:sync')->assertSuccessful();
        $this->artisan('workspace:sync')->assertSuccessful();
        $this->assertDatabaseCount('workspace_dossiers', 1);
        $this->assertDatabaseCount('workspace_activities', 2);
    }

    public function test_relier_un_sujet_au_conducteur_reunit_les_echanges_dans_un_seul_dossier(): void
    {
        $chef = $this->actor('chef_edition');
        $sequence = $this->sequence($chef);
        $old = WorkspaceDossier::sole();
        app(SharedWorkspaceService::class)->comment($old, $chef, 'Consigne conservée');
        $dossier = $this->dossier($chef);
        $sequence->update(['editorial_story_id' => $dossier->editorial_story_id]);
        $this->assertSame($dossier->id, $old->fresh()->merged_into_id);
        $this->assertNull($old->fresh()->sequence_id);
        $this->assertSame(1, $dossier->comments()->count());
        $this->actingAs($chef)->get(route('workspace.show', $old))->assertRedirect(route('workspace.show', $dossier));
        $this->get(route('workspace.show', $dossier))->assertOk()->assertSee('Consigne conservée')->assertSee('90 s prévues');
        $this->post(route('workspace.comments.store', $old), ['body' => 'Écriture périmée'])->assertForbidden();
    }

    public function test_un_document_est_prive_et_un_identifiant_d_un_autre_dossier_est_refuse(): void
    {
        Storage::fake('local');
        $owner = $this->actor();
        $other = $this->actor();
        $dossier = $this->dossier($owner);
        $another = $this->dossier($owner);
        $file = UploadedFile::fake()->createWithContent('consigne.txt', 'Texte privé');
        $this->actingAs($owner)->post(route('workspace.files.store', $dossier), ['file' => $file])->assertRedirect();
        $attachment = WorkspaceAttachment::sole();
        $this->assertStringStartsWith('private/workspace/', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('workspace.files.download', [$dossier, $attachment]))->assertDownload('consigne.txt');
        $this->get(route('workspace.files.download', [$another, $attachment]))->assertNotFound();
        $this->actingAs($other)->get(route('workspace.files.download', [$dossier, $attachment]))->assertForbidden();
    }

    public function test_les_fichiers_executables_deguises_et_trop_volumineux_sont_refuses(): void
    {
        Storage::fake('local');
        $owner = $this->actor();
        $dossier = $this->dossier($owner);
        $this->actingAs($owner)->postJson(route('workspace.files.store', $dossier), ['file' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')])->assertUnprocessable();
        $this->postJson(route('workspace.files.store', $dossier), ['file' => UploadedFile::fake()->create('grand.pdf', 10241, 'application/pdf')])->assertUnprocessable();
        $this->assertDatabaseCount('workspace_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_une_publication_reste_liee_au_sujet_et_garde_son_circuit_de_validation(): void
    {
        $chef = $this->actor('chef_edition');
        $cm = $this->actor('community_manager');
        $dossier = $this->dossier($chef);
        app(SharedWorkspaceService::class)->addMember($dossier, $chef, $cm);
        $this->actingAs($cm)->get(route('digital.create', ['dossier' => $dossier->id]))->assertOk()->assertSee('dossier_id');
        $this->post(route('digital.store'), ['dossier_id' => $dossier->id, 'title' => 'Déclinaison du sujet', 'content' => 'Texte numérique', 'platform' => 'site'])->assertRedirect();
        $pub = DigitalPublication::sole();
        $this->assertSame($dossier->editorial_story_id, (int) $pub->editorial_story_id);
        $this->get(route('workspace.show', $dossier))->assertOk()->assertSee('Déclinaison du sujet');
        $this->postJson(route('digital.transition', $pub), ['action' => 'publish', 'publication_url' => 'https://example.org/article'])->assertUnprocessable();
        $this->post(route('digital.transition', $pub), ['action' => 'submit'])->assertRedirect();
        $this->actingAs($chef)->post(route('digital.transition', $pub), ['action' => 'approve'])->assertRedirect();
        $this->assertTrue($dossier->activities()->where('kind', 'publication_updated')->exists());
    }

    public function test_un_cm_ne_peut_pas_lier_un_dossier_non_partage(): void
    {
        $chef = $this->actor('chef_edition');
        $cm = $this->actor('community_manager');
        $dossier = $this->dossier($chef);
        $this->actingAs($cm)->get(route('digital.create', ['dossier' => $dossier->id]))->assertForbidden();
        $this->post(route('digital.store'), ['dossier_id' => $dossier->id, 'title' => 'Intrusion', 'content' => 'Texte', 'platform' => 'site'])->assertForbidden();
        $this->assertDatabaseCount('digital_publications', 0);
    }

    public function test_la_lecture_seule_et_les_permissions_personnalisees_sont_respectees(): void
    {
        $chef = $this->actor('chef_edition');
        $reader = $this->actor('lecture');
        $dossier = $this->dossier($chef);
        app(SharedWorkspaceService::class)->addMember($dossier, $chef, $reader);
        $this->actingAs($reader)->get(route('workspace.show', $dossier))->assertOk()->assertDontSee('Partager le commentaire')->assertDontSee('Attribuer la tâche');
        $this->post(route('workspace.comments.store', $dossier), ['body' => 'Refusé'])->assertForbidden();
        Role::findByName('lecture')->revokePermissionTo('workspace.view');
        $reader->unsetRelation('roles')->unsetRelation('permissions');
        $this->get(route('workspace.show', $dossier))->assertForbidden();
    }

    public function test_les_commentaires_sont_echappes_et_l_actualisation_ne_renvoie_rien_sans_changement(): void
    {
        $owner = $this->actor();
        $dossier = $this->dossier($owner);
        $revision = $dossier->activities()->max('id');
        $this->actingAs($owner)->getJson(route('workspace.state', [$dossier, 'revision' => ['invalid']]))->assertUnprocessable();
        $this->getJson(route('workspace.state', [$dossier, 'revision' => $revision]))->assertOk()->assertJsonMissingPath('html')->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('workspace.comments.store', $dossier), ['body' => '<script>alert(1)</script>', 'user_id' => 999])->assertRedirect();
        $this->assertSame($owner->id, $dossier->comments()->sole()->user_id);
        $response = $this->getJson(route('workspace.state', [$dossier, 'revision' => $revision]))->assertOk();
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->json('html'));
    }

    public function test_supprimer_la_source_conserve_l_historique_et_ferme_les_modifications(): void
    {
        $owner = $this->actor();
        $dossier = $this->dossier($owner);
        app(SharedWorkspaceService::class)->comment($dossier, $owner, 'Historique réel');
        $dossier->story->delete();
        $this->actingAs($owner)->get(route('workspace.show', $dossier))->assertOk()->assertSee('Historique réel')->assertSee('La source a été supprimée')->assertDontSee('Partager le commentaire');
        $this->post(route('workspace.comments.store', $dossier), ['body' => 'Interdit'])->assertForbidden();
    }

    public function test_le_compte_cree_apres_une_affectation_retrouve_ses_sujets_et_le_miroir_ancien_ne_donne_pas_un_acces_indu(): void
    {
        $chef = $this->actor('chef_edition');
        $journaliste = Journaliste::create(['nom' => 'Compte', 'prenom' => 'Lié']);
        $other = Journaliste::create(['nom' => 'Autre', 'prenom' => 'Affectation']);
        $dossier = $this->dossier($chef);
        $dossier->story->update(['assigned_journaliste_id' => $journaliste->id]);
        $account = $this->actor();
        $journaliste->update(['user_id' => $account->id]);
        $this->assertTrue($dossier->hasMember($account));
        $account->update(['journaliste_id' => $other->id]);
        $this->assertFalse($dossier->hasMember($account));
    }

    public function test_changer_le_responsable_du_conducteur_actualise_le_dossier_manuel(): void
    {
        $old = $this->actor();
        $new = $this->actor();
        $sequence = $this->sequence($old);
        $dossier = WorkspaceDossier::sole();
        $sequence->conducteur->update(['owner_id' => $new->id]);
        $this->assertFalse($dossier->hasMember($old));
        $this->assertTrue($dossier->hasMember($new));
    }
}
