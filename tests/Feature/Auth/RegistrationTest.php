<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * L'auto-inscription est VOLONTAIREMENT désactivée sur TV+AOS :
 * les comptes sont créés par l'administration (module Gestion des Utilisateurs),
 * avec un mot de passe temporaire et un changement imposé à la 1ʳᵉ connexion.
 * @see routes/auth.php (route `register` commentée)
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_cannot_register(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_admin_can_still_create_a_user_account(): void
    {
        // Le canal de création légitime reste le CRUD administrateur
        // (2 modes : depuis un journaliste existant, ou manuel).
        $admin = User::factory()->create();
        Role::findOrCreate('admin');
        Role::findOrCreate('journaliste');
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->from('/users')
            ->post('/users', [
                'mode'         => 'manual',
                'role'         => 'journaliste',
                'civilite'     => 'Monsieur',
                'prenom'       => 'Jean',
                'nom'          => 'Test',
                'email'        => 'cree-par-admin@tvplusos.tv',
                'temp_password' => 'motdepasse-temporaire-123',
            ]);

        $this->assertDatabaseHas('users', ['email' => 'cree-par-admin@tvplusos.tv']);
    }
}
