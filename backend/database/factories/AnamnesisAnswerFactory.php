<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnamnesisAnswer;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnamnesisAnswer>
 */
class AnamnesisAnswerFactory extends Factory
{
    protected $model = AnamnesisAnswer::class;

    public function definition(): array
    {
        return [
            'response_id' => AnamnesisResponse::factory(),
            'question_id' => AnamnesisQuestion::factory(),
            'answer_value' => fake()->sentence(),
        ];
    }
}
