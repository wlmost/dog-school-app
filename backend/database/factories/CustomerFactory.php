<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->optional()->secondaryAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'country' => 'Deutschland',
            'emergency_contact' => fake()->name() . ' - ' . fake()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the customer has dogs.
     */
    public function hasDogs(int $count = 1): static
    {
        return $this->has(\App\Models\Dog::factory()->count($count), 'dogs');
    }

    /**
     * Indicate that the customer has bookings.
     */
    public function hasBookings(int $count = 1): static
    {
        return $this->has(\App\Models\Booking::factory()->count($count), 'bookings');
    }

    /**
     * Indicate that the customer has invoices.
     */
    public function hasInvoices(int $count = 1): static
    {
        return $this->has(\App\Models\Invoice::factory()->count($count), 'invoices');
    }

    /**
     * Indicate that the customer has credits.
     */
    public function hasCredits(int $count = 1): static
    {
        return $this->has(\App\Models\CustomerCredit::factory()->count($count), 'credits');
    }
}
