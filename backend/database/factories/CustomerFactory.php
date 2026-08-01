<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\Dog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
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
            'emergency_contact' => fake()->name().' - '.fake()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the customer has dogs.
     */
    public function hasDogs(int $count = 1): static
    {
        return $this->has(Dog::factory()->count($count), 'dogs');
    }

    /**
     * Indicate that the customer has bookings.
     */
    public function hasBookings(int $count = 1): static
    {
        return $this->has(Booking::factory()->count($count), 'bookings');
    }

    /**
     * Indicate that the customer has invoices.
     */
    public function hasInvoices(int $count = 1): static
    {
        return $this->has(Invoice::factory()->count($count), 'invoices');
    }

    /**
     * Indicate that the customer has credits.
     */
    public function hasCredits(int $count = 1): static
    {
        return $this->has(CustomerCredit::factory()->count($count), 'credits');
    }
}
