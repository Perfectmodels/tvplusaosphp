<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'profile_photo_path' => null,
            'phone' => fake()->phoneNumber(),
            'role' => 'journaliste',
            'department' => 'Rédaction',
            'is_active' => true,
            // Un utilisateur de la factory est considéré comme ayant déjà changé son
            // mot de passe initial (sinon le middleware `password.changed` le redirige
            // vers /first-login-change-password et casse les tests Breeze).
            'password_changed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
