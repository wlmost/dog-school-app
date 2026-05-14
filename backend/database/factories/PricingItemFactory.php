<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PricingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PricingItem>
 */
class PricingItemFactory extends Factory
{
    protected $model = PricingItem::class;

    public function definition(): array
    {
        return [
            'category'      => fake()->randomElement(['Welpenkurs', 'Einzelstunden', 'Gruppentraining']),
            'title'         => fake()->sentence(3),
            'price'         => fake()->randomFloat(2, 10, 500),
            'unit'          => null,
            'description'   => null,
            'is_from_price' => false,
        ];
    }
}
