<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $customer = Customer::factory()->create();
        
        return [
            'training_session_id' => TrainingSession::factory(),
            'customer_id' => $customer->id,
            'dog_id' => Dog::factory()->create(['customer_id' => $customer->id])->id,
            'status' => 'pending',
            'booking_date' => now(),
            'attended' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'attended' => true,
        ]);
    }
}
