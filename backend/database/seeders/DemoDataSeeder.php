<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoDataSeeder
 *
 * Seeds a test trainer and a test customer for demonstration purposes.
 * Called optionally during installation via the "Install demo data" checkbox.
 *
 * Credentials:
 *   Trainer  → trainer@example.com / demo1234
 *   Customer → customer@example.com / demo1234
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('demo1234');

        User::factory()->trainer()->create([
            'first_name'        => 'Test',
            'last_name'         => 'Trainer',
            'email'             => 'trainer@example.com',
            'password'          => $password,
            'email_verified_at' => now(),
        ]);

        /** @var User $customerUser */
        $customerUser = User::factory()->customer()->create([
            'first_name'        => 'Test',
            'last_name'         => 'Kunde',
            'email'             => 'customer@example.com',
            'password'          => $password,
            'email_verified_at' => now(),
        ]);

        Customer::factory()->for($customerUser, 'user')->create([
            'address_line1' => 'Musterstraße 1',
            'postal_code'   => '12345',
            'city'          => 'Musterstadt',
            'country'       => 'Deutschland',
        ]);
    }
}
