<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Journaliste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JournalisteAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'admin'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'nom' => 'Mabicka',
            'prenom' => 'Anne',
            'email' => 'anne.mabicka@example.org',
            'telephone' => '+24177000000',
        ], $overrides);
    }

    public function test_creation_enregistre_un_compte_lie_avec_matricule_et_premiere_connexion_obligatoire(): void
    {
        $actor = $this->actor();
        $response = $this->actingAs($actor)->post(route('journalistes.store'), $this->payload());
        $journaliste = Journaliste::firstOrFail();
        $user = $journaliste->user;
        $response->assertRedirect(route('journalistes.show', $journaliste))->assertSessionHasNoErrors();
        $credentials = session('journaliste_credentials');
        $this->assertSame($journaliste->id, $user->journaliste_id);
        $this->assertSame('anne.mabicka@example.org', $user->email);
        $this->assertSame('Anne Mabicka', $user->name);
        $this->assertSame($actor->id, $user->created_by);
        $this->assertTrue($user->hasRole('journaliste'));
        $this->assertNull($user->password_changed_at);
        $this->assertTrue(Hash::check($credentials['password'], $user->password));
        $this->assertNotSame($credentials['password'], $user->password);
        $this->assertMatchesRegularExpression('/^J-\d{4}-\d{4}$/', $journaliste->matricule);
        $this->get(route('journalistes.show', $journaliste))
            ->assertOk()->assertSee($credentials['password'])->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('journalistes.show', $journaliste))->assertDontSee($credentials['password']);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('first-login-change-password.show'));
    }

    public function test_un_chef_edition_ne_peut_creer_que_le_role_journaliste_depuis_la_fiche(): void
    {
        $this->actingAs($this->actor('chef_edition'))->post(route('journalistes.store'), $this->payload([
            'role' => 'admin', 'password_changed_at' => now(),
        ]))->assertSessionHasNoErrors();

        $user = Journaliste::firstOrFail()->user;
        $this->assertSame(['journaliste'], $user->roles->pluck('name')->all());
        $this->assertSame('journaliste', $user->role);
        $this->assertNull($user->password_changed_at);
    }

    public function test_email_reel_obligatoire_sans_creation_partielle(): void
    {
        $this->actingAs($this->actor())->post(route('journalistes.store'), $this->payload(['email' => '']))
            ->assertSessionHasErrors(['email' => 'Le champ adresse e-mail est obligatoire.']);
        $this->assertDatabaseCount('journalistes', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_un_email_de_compte_deja_utilise_demande_un_rattachement_explicite(): void
    {
        $existing = User::factory()->create(['email' => 'anne.mabicka@example.org']);
        $this->actingAs($this->actor())->post(route('journalistes.store'), $this->payload())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('journalistes', 0);
        $this->assertNull($existing->fresh()->journaliste_id);
    }

    public function test_admin_peut_rattacher_un_compte_existant_sans_changer_ses_acces(): void
    {
        $actor = $this->actor();
        $existing = $this->actor('chef_edition');
        $password = $existing->password;
        $this->actingAs($actor)->post(route('journalistes.store'), $this->payload([
            'user_id' => $existing->id, 'email' => $existing->email,
        ]))->assertSessionHasNoErrors();
        $journaliste = Journaliste::firstOrFail();
        $this->assertSame($existing->id, $journaliste->user_id);
        $this->assertSame($journaliste->id, $existing->fresh()->journaliste_id);
        $this->assertSame($password, $existing->fresh()->password);
        $this->assertTrue($existing->fresh()->hasRole('chef_edition'));
        $this->assertNull(session('journaliste_credentials'));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_rattachement_avec_mauvais_email_annule_aussi_la_fiche_et_le_matricule(): void
    {
        $actor = $this->actor();
        $existing = User::factory()->create();
        $this->actingAs($actor)->post(route('journalistes.store'), $this->payload(['user_id' => $existing->id]))
            ->assertSessionHasErrors(['email' => 'Utilisez l’adresse e-mail du compte sélectionné.']);
        $this->assertDatabaseCount('journalistes', 0);
        $this->assertDatabaseCount('employee_number_sequences', 0);
        $this->assertNull($existing->fresh()->journaliste_id);
    }

    public static function linkDirections(): array
    {
        return ['fiche vers compte' => [false], 'compte vers fiche' => [true]];
    }

    #[DataProvider('linkDirections')]
    public function test_compte_deja_rattache_non_propose_et_refuse(bool $reverse): void
    {
        $actor = $this->actor();
        $existing = User::factory()->create(['email' => 'anne.mabicka@example.org']);
        $journaliste = Journaliste::factory()->create(['user_id' => $reverse ? null : $existing->id]);
        if ($reverse) {
            $existing->update(['journaliste_id' => $journaliste->id]);
        }
        $this->actingAs($actor)->get(route('journalistes.create'))->assertDontSee('data-email="anne.mabicka@example.org"', false);
        $this->post(route('journalistes.store'), $this->payload(['user_id' => $existing->id]))->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('journalistes', 1);
    }

    public function test_chef_edition_ne_peut_pas_rattacher_un_compte_existant(): void
    {
        $existing = User::factory()->create();
        $this->actingAs($this->actor('chef_edition'))->post(route('journalistes.store'), $this->payload([
            'user_id' => $existing->id, 'email' => $existing->email,
        ]))->assertForbidden();
        $this->assertDatabaseCount('journalistes', 0);
    }

    public function test_modification_synchronise_le_compte_sans_changer_matricule_role_ni_mot_de_passe(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->post(route('journalistes.store'), $this->payload());
        $journaliste = Journaliste::firstOrFail();
        $password = $journaliste->user->password;
        $matricule = $journaliste->matricule;
        $this->put(route('journalistes.update', $journaliste), $this->payload([
            'prenom' => 'Anna', 'email' => 'anna.mabicka@example.org', 'is_active' => '0',
        ]))->assertSessionHasNoErrors();
        $this->assertSame($matricule, $journaliste->fresh()->matricule);
        $user = $journaliste->fresh()->user;
        $this->assertSame('Anna Mabicka', $user->name);
        $this->assertSame('anna.mabicka@example.org', $user->email);
        $this->assertFalse($user->is_active);
        $this->assertSame($password, $user->password);
        $this->assertTrue($user->hasRole('journaliste'));
        $this->assertNull(session('journaliste_credentials'));
    }

    public function test_ancienne_fiche_peut_recevoir_un_compte_lors_de_la_modification(): void
    {
        $journaliste = Journaliste::factory()->create(['email' => 'anne.mabicka@example.org']);
        $matricule = $journaliste->matricule;
        $this->actingAs($this->actor())->put(route('journalistes.update', $journaliste), $this->payload(['creer_compte' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertNotNull($journaliste->fresh()->user_id);
        $this->assertSame($matricule, $journaliste->fresh()->matricule);
        $this->assertSame($journaliste->id, $journaliste->fresh()->user->journaliste_id);
    }

    public function test_modification_ne_permet_pas_de_remplacer_le_compte_par_un_autre(): void
    {
        $journaliste = Journaliste::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($this->actor())->put(route('journalistes.update', $journaliste), $this->payload(['user_id' => $other->id]))
            ->assertSessionHasErrors('user_id');
        $this->assertNull($journaliste->fresh()->user_id);
    }

    public function test_matricule_supprime_non_reutilise_et_compteur_annuel(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13));
        $journaliste = Journaliste::factory()->create(['matricule' => null]);
        $this->assertSame('J-2026-0001', $journaliste->matricule);
        $journaliste->delete();
        $this->assertSame('J-2026-0002', Journaliste::factory()->create(['matricule' => null])->matricule);
        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('J-2027-0001', Journaliste::factory()->create(['matricule' => null])->matricule);
    }

    public function test_matricule_manuel_supprime_reserve_son_numero(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13));
        Journaliste::factory()->create(['matricule' => 'J-2026-0123'])->delete();
        $this->assertSame('J-2026-0124', Journaliste::factory()->create(['matricule' => null])->matricule);
    }

    public function test_fiche_sans_compte_n_affiche_plus_d_acces_ou_d_historique_inventes(): void
    {
        $journaliste = Journaliste::factory()->create();
        $this->actingAs($this->actor())->get(route('journalistes.show', $journaliste))->assertOk()
            ->assertSee('Aucun compte utilisateur lié')->assertDontSee('Import RH')->assertDontSee('Créé par Admin')
            ->assertDontSee('Accès Conducteurs')->assertDontSee('Rôle par défaut')
            ->assertDontSee('Gabonaise')->assertDontSee('CDI');
        $withoutEmail = Journaliste::create(['prenom' => 'Sans', 'nom' => 'Email']);
        $this->assertNull($withoutEmail->email);
    }

    public function test_journaliste_non_autorise_ne_peut_pas_creer_de_compte(): void
    {
        $this->actingAs($this->actor('journaliste'))->post(route('journalistes.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('journalistes', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_visiteur_doit_se_connecter_avant_la_creation(): void
    {
        $this->post(route('journalistes.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_creation_depuis_utilisateurs_rattache_aussi_une_ancienne_fiche(): void
    {
        Role::findOrCreate('journaliste', 'web');
        $journaliste = Journaliste::factory()->create(['is_active' => true]);
        $response = $this->actingAs($this->actor())->post(route('users.store'), [
            'mode' => 'from_journaliste', 'journaliste_id' => $journaliste->id,
            'email' => 'connexion@example.org', 'role' => 'journaliste',
        ]);
        $this->assertSame('success', session('swal.type'), session('swal.text') ?? '');
        $response->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $this->assertNotNull($journaliste->fresh()->user_id);
        $this->assertSame('connexion@example.org', $journaliste->fresh()->email);
        $this->assertSame($journaliste->id, $journaliste->fresh()->user->journaliste_id);
        $this->get(route('users.index'))->assertOk()->assertSee($journaliste->matricule);
    }

    public function test_creation_depuis_utilisateurs_refuse_un_second_compte_pour_la_meme_fiche(): void
    {
        $journaliste = Journaliste::factory()->create();
        User::factory()->create(['journaliste_id' => $journaliste->id]);
        $this->actingAs($this->actor())->post(route('users.store'), [
            'mode' => 'from_journaliste', 'journaliste_id' => $journaliste->id,
            'email' => 'autre@example.org', 'role' => 'journaliste',
        ])->assertSessionHasErrors('journaliste_id');
        $this->assertDatabaseCount('users', 2);
        $this->assertNull($journaliste->fresh()->user_id);
    }

    public function test_chef_edition_ne_peut_pas_modifier_un_compte_administrateur_via_sa_fiche(): void
    {
        $admin = $this->actor();
        $journaliste = Journaliste::factory()->create(['user_id' => $admin->id, 'email' => $admin->email]);
        $admin->update(['journaliste_id' => $journaliste->id]);
        $this->actingAs($this->actor('chef_edition'))->put(route('journalistes.update', $journaliste), $this->payload())
            ->assertForbidden();
        $this->assertSame($admin->email, $admin->fresh()->email);
        $this->assertSame($admin->email, $journaliste->fresh()->email);
    }
}
