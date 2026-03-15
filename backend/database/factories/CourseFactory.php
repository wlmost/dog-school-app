<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'trainer_id' => User::factory()->trainer(),
            'name' => fake()->randomElement(['Puppy Training', 'Basic Obedience', 'Advanced Training', 'Agility Course', 'Behavioral Therapy']),
            'description' => fake()->paragraph(),
            'course_type' => fake()->randomElement(['group', 'individual', 'workshop']),
            'max_participants' => fake()->numberBetween(4, 12),
            'price_per_session' => fake()->randomFloat(2, 15, 75),
            'duration_minutes' => fake()->randomElement([45, 60, 90, 120]),
            'total_sessions' => fake()->numberBetween(5, 12),
            'start_date' => now(),
            'end_date' => now()->addWeeks(12),
            'status' => 'active',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
