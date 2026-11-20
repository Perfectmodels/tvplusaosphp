<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatutVerificationEditorialEnum;
use App\Models\EditorialEvent;
use App\Models\EditorialVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialVerification>
 */
class EditorialVerificationFactory extends Factory
{
    protected $model = EditorialVerification::class;

    public function definition(): array
    {
        return [
            'editorial_event_id' => EditorialEvent::factory(),
            'type' => 'source_confirmation',
            'claim' => fake()->sentence(),
            'status' => StatutVerificationEditorialEnum::EnAttente,
            'created_by' => User::factory(),
        ];
    }
}
