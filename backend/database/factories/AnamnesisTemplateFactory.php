<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnamnesisTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnamnesisTemplate>
 */
class AnamnesisTemplateFactory extends Factory
{
    protected $model = AnamnesisTemplate::class;

    public function definition(): array
    {
        return [
            'trainer_id' => User::factory()->trainer(),
            'name' => fake()->randomElement(['Health Assessment', 'Behavioral Evaluation', 'Training Intake Form', 'Puppy Questionnaire', 'Advanced Assessment']),
            'description' => fake()->paragraph(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
