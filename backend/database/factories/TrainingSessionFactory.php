<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    protected $model = TrainingSession::class;

    public function definition(): array
    {
        $course = Course::factory()->create();
        
        return [
            'course_id' => $course->id,
            'trainer_id' => $course->trainer_id,
            'session_date' => fake()->dateTimeBetween('now', '+3 months'),
            'start_time' => fake()->time('H:i:s'),
            'end_time' => fake()->time('H:i:s'),
            'location' => fake()->randomElement(['Training Field A', 'Training Field B', 'Indoor Arena', 'Park']),
            'max_participants' => $course->max_participants,
            'notes' => fake()->optional()->sentence(),
            'status' => 'scheduled',
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_date' => fake()->dateTimeBetween('now', '+2 months'),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'session_date' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'session_date' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
    }
}
