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
        $package = CreditPackage::factory()->create();
        
        return [
            'customer_id' => Customer::factory(),
            'credit_package_id' => $package->id,
            'remaining_credits' => $package->total_credits,
            'purchase_date' => now(),
            'expiry_date' => now()->addDays($package->validity_days ?? 365),
            'status' => 'active',
        ];
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
            'expiry_date' => now()->subDays(10),
            'status' => 'expired',
        ]);
    }
}
