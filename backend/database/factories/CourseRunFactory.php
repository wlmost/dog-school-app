<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for CourseRun model.
 *
 * @extends Factory<CourseRun>
 */
class CourseRunFactory extends Factory
{
    protected $model = CourseRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+3 months');

        return [
            'course_id'  => Course::factory(),
            'start_date' => $start->format('Y-m-d'),
            'end_date'   => (clone $start)->modify('+4 weeks')->format('Y-m-d'),
            'status'     => 'active',
        ];
    }

    /**
     * Mark the run as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'completed']);
    }

    /**
     * Mark the run as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'cancelled']);
    }
}
