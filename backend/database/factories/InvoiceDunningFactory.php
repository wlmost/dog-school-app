<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceDunning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDunning>
 */
class InvoiceDunningFactory extends Factory
{
    protected $model = InvoiceDunning::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'level' => fake()->numberBetween(1, 3),
            'dunning_date' => now(),
            'fee_amount' => fake()->randomFloat(2, 0, 25),
        ];
    }
}
