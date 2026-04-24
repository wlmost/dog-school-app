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
        User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Create Trainer User
        User::factory()->create([
            'first_name' => 'Trainer',
            'last_name' => 'User',
            'email' => 'trainer@example.com',
            'password' => bcrypt('password'),
            'role' => 'trainer',
        ]);

        // Create Customer User
        User::factory()->create([
            'first_name' => 'Customer',
            'last_name' => 'User',
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        // Seed anamnesis templates
        $this->call(AnamnesisTemplateSeeder::class);

        // Seed default settings
        $this->call(SettingsSeeder::class);
    }
}
