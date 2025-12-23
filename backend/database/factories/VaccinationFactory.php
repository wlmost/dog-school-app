<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dog;
use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vaccination>
 */
class VaccinationFactory extends Factory
{
    protected $model = Vaccination::class;

    public function definition(): array
    {
        $vaccinationDate = fake()->dateTimeBetween('-2 years', 'now');
        
        return [
            'dog_id' => Dog::factory(),
            'vaccination_type' => fake()->randomElement(['Rabies', 'Distemper', 'Parvovirus', 'Leptospirosis', 'Bordetella']),
            'vaccination_date' => $vaccinationDate,
            'next_due_date' => fake()->dateTimeBetween('now', '+2 years'),
            'veterinarian' => fake()->name(),
            'document_path' => fake()->optional()->filePath(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_date' => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }

    public function dueSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_date' => now()->addDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
