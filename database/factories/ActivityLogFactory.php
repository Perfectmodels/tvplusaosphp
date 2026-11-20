<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'actor_name' => fake()->name(),
            'event' => 'patch',
            'route_name' => 'conducteurs.redaction.sujet.update',
            'route_uri' => 'conducteurs/redaction/{conducteur}/sujets/{sequence}',
            'http_method' => 'PATCH',
            'http_status' => 302,
            'subject_type' => Sequence::class,
            'subject_id' => fake()->randomNumber(5),
            'route_parameters' => ['conducteur' => 1, 'sequence' => 1],
            'request_fields' => ['title', 'angle_editorial'],
            'context' => null,
            'created_at' => now(),
        ];
    }
}
