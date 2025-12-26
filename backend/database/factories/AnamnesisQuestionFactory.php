<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnamnesisQuestion>
 */
class AnamnesisQuestionFactory extends Factory
{
    protected $model = AnamnesisQuestion::class;

    public function definition(): array
    {
        $questionType = fake()->randomElement(['text', 'textarea', 'select', 'radio', 'checkbox']);
        
        return [
            'template_id' => AnamnesisTemplate::factory(),
            'question_text' => fake()->sentence() . '?',
            'question_type' => $questionType,
            'options' => in_array($questionType, ['select', 'radio', 'checkbox']) 
                ? ['Option 1', 'Option 2', 'Option 3'] 
                : null,
            'is_required' => fake()->boolean(70),
            'order' => 0,
        ];
    }
}
