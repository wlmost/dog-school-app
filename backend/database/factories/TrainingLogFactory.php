<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dog;
use App\Models\TrainingLog;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingLog>
 */
class TrainingLogFactory extends Factory
{
    protected $model = TrainingLog::class;

    public function definition(): array
    {
        return [
            'training_session_id' => TrainingSession::factory(),
            'dog_id' => Dog::factory(),
            'trainer_id' => User::factory()->trainer(),
            'progress_notes' => fake()->paragraph(),
            'behavior_notes' => fake()->optional()->paragraph(),
            'homework' => fake()->optional()->sentence(),
        ];
    }
}
