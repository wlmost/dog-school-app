<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TestingSeeder
 *
 * Provides a reusable, consistent baseline dataset for the test suite.
 * Every Feature test starts with this data already in the database.
 *
 * Fixed credentials so tests can look up users by well-known e-mail addresses:
 *   Admin    → admin@test.local    / password: secret
 *   Trainer  → trainer@test.local  / password: secret
 *   Customer → customer@test.local / password: secret
 *
 * Settings are seeded so that application logic relying on configuration
 * values (e.g. company_small_business) works without additional setup.
 */
class TestingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ── Application settings ──────────────────────────────────────────────
        $this->call(SettingsSeeder::class);

        // ── Base users ────────────────────────────────────────────────────────
        $password = Hash::make('secret');

        User::factory()->admin()->create([
            'first_name'         => 'Test',
            'last_name'          => 'Admin',
            'email'              => 'admin@test.local',
            'password'           => $password,
            'email_verified_at'  => now(),
        ]);

        User::factory()->trainer()->create([
            'first_name'         => 'Test',
            'last_name'          => 'Trainer',
            'email'              => 'trainer@test.local',
            'password'           => $password,
            'email_verified_at'  => now(),
        ]);

        /** @var User $customerUser */
        $customerUser = User::factory()->customer()->create([
            'first_name'         => 'Test',
            'last_name'          => 'Customer',
            'email'              => 'customer@test.local',
            'password'           => $password,
            'email_verified_at'  => now(),
        ]);

        // ── Customer profile linked to the base customer user ─────────────────
        Customer::factory()->for($customerUser, 'user')->create([
            'address_line1' => 'Musterstraße 1',
            'postal_code'   => '12345',
            'city'          => 'Musterstadt',
            'country'       => 'Deutschland',
        ]);
    }
}
