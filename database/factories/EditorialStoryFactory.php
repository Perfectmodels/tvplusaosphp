<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatutSujetEditorialEnum;
use App\Models\EditorialEvent;
use App\Models\EditorialStory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialStory>
 */
class EditorialStoryFactory extends Factory
{
    protected $model = EditorialStory::class;

    public function definition(): array
    {
        return [
            'editorial_event_id' => EditorialEvent::factory(),
            'title' => fake()->sentence(6),
            'summary' => fake()->paragraph(),
            'angle' => fake()->sentence(),
            'status' => StatutSujetEditorialEnum::Brouillon,
            'created_by' => User::factory(),
        ];
    }
}
