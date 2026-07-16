<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DogRegistrationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DogRegistrationRequest>
 */
class DogRegistrationRequestFactory extends Factory
{
    /** @var class-string<DogRegistrationRequest> */
    protected $model = DogRegistrationRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id'  => Customer::factory(),
            'name'         => fake()->firstName(),
            'breed'        => fake()->randomElement(['German Shepherd', 'Golden Retriever', 'Labrador', 'Border Collie', 'Poodle', 'Mixed Breed']),
            'gender'       => fake()->randomElement(['male', 'female']),
            'date_of_birth' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'neutered'     => fake()->boolean(),
            'chip_number'  => fake()->optional()->numerify('##########'),
            'owner_since'  => fake()->optional()->dateTimeBetween('-5 years', 'now'),
            'age_at_acquisition' => fake()->optional()->randomElement(['ca. 2 Jahre', 'Welpe', 'ca. 6 Monate', 'ca. 1 Jahr']),
            'origin'       => fake()->optional()->randomElement(['breeder', 'shelter', 'private', 'unknown']),
            'notes'        => fake()->optional()->sentence(),
            'status'       => 'pending',
            'reviewed_by'  => null,
            'reviewed_at'  => null,
        ];
    }

    /**
     * State: already approved.
     *
     * @return static
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'approved',
            'reviewed_by' => User::factory(['role' => 'admin']),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * State: already rejected.
     *
     * @return static
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'rejected',
            'reviewed_by' => User::factory(['role' => 'admin']),
            'reviewed_at' => now(),
        ]);
    }
}
