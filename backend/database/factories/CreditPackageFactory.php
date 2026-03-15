<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CreditPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditPackage>
 */
class CreditPackageFactory extends Factory
{
    protected $model = CreditPackage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['5-Session Package', '10-Session Package', '20-Session Package']),
            'description' => fake()->sentence(),
            'total_credits' => fake()->randomElement([5, 10, 20]),
            'price' => fake()->randomFloat(2, 50, 200),
            'validity_days' => fake()->randomElement([90, 180, 365]),
        ];
    }


}
