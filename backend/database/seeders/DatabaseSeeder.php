<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );

        // Create Trainer User
        User::firstOrCreate(
            ['email' => 'trainer@example.com'],
            [
                'first_name' => 'Trainer',
                'last_name' => 'User',
                'password' => bcrypt('password'),
                'role' => 'trainer',
            ]
        );

        // Create Customer User
        User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'first_name' => 'Customer',
                'last_name' => 'User',
                'password' => bcrypt('password'),
                'role' => 'customer',
            ]
        );

        // Seed anamnesis templates
        $this->call(AnamnesisTemplateSeeder::class);

        // Seed default settings
        $this->call(SettingsSeeder::class);
    }
}
