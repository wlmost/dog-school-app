<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnamnesisResponse;
use App\Models\AnamnesisTemplate;
use App\Models\Dog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnamnesisResponse>
 */
class AnamnesisResponseFactory extends Factory
{
    protected $model = AnamnesisResponse::class;

    public function definition(): array
    {
        return [
            'dog_id' => Dog::factory(),
            'template_id' => AnamnesisTemplate::factory(),
            'completed_at' => null,
            'completed_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }
}
