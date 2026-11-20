<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatutEvenementEditorialEnum;
use App\Models\EditorialEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialEvent>
 */
class EditorialEventFactory extends Factory
{
    protected $model = EditorialEvent::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => StatutEvenementEditorialEnum::EnVerification,
            'created_by' => User::factory(),
        ];
    }
}
