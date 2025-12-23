<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Dog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dog>
 */
class DogFactory extends Factory
{
    protected $model = Dog::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->firstName(),
            'breed' => fake()->randomElement(['German Shepherd', 'Golden Retriever', 'Labrador', 'Border Collie', 'Poodle', 'Mixed Breed']),
            'date_of_birth' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'gender' => fake()->randomElement(['male', 'female']),
            'neutered' => fake()->boolean(),
            'weight' => fake()->randomFloat(2, 5, 80),
            'chip_number' => fake()->optional()->numerify('##########'),
            'veterinarian_name' => fake()->optional()->name(),
            'veterinarian_contact' => fake()->optional()->phoneNumber(),
            'medical_notes' => fake()->optional()->sentence(),
        ];
    }

    public function puppy(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_of_birth' => now()->subMonths(fake()->numberBetween(2, 11)),
        ]);
    }
}
