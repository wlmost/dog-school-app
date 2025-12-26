<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CreditPackage;
use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerCredit>
 */
class CustomerCreditFactory extends Factory
{
    protected $model = CustomerCredit::class;

    public function definition(): array
    {
        $totalCredits = fake()->numberBetween(5, 20);
        
        return [
            'customer_id' => Customer::factory(),
            'credit_package_id' => CreditPackage::factory(),
            'total_credits' => $totalCredits,
            'remaining_credits' => $totalCredits,
            'purchase_date' => now(),
            'expiration_date' => now()->addDays(365),
            'status' => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $totalCredits = $attributes['total_credits'] ?? 10;
            return [
                'status' => 'active',
                'total_credits' => $totalCredits,
                'remaining_credits' => fake()->numberBetween(1, $totalCredits),
                'expiration_date' => now()->addDays(30),
            ];
        });
    }

    public function depleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining_credits' => 0,
            'status' => 'used',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiration_date' => now()->subDays(10),
            'status' => 'expired',
        ]);
    }
}
