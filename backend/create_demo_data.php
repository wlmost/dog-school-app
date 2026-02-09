<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create a dog
$customer = App\Models\Customer::first();
if (!$customer) {
    $userCustomer = App\Models\User::where('role', 'customer')->first();
    if ($userCustomer) {
        $customer = $userCustomer->customer;
    }
}

if (!$customer) {
    echo "No customer found. Creating one...\n";
    $user = App\Models\User::create([
        'email' => 'customer@test.com',
        'password' => bcrypt('password'),
        'role' => 'customer',
        'first_name' => 'Test',
        'last_name' => 'Customer',
    ]);
    $customer = App\Models\Customer::create([
        'user_id' => $user->id,
    ]);
    echo "Customer created with ID: {$customer->id}\n";
}

$dog = App\Models\Dog::first();
if (!$dog) {
    $dog = App\Models\Dog::create([
        'customer_id' => $customer->id,
        'name' => 'Max',
        'breed' => 'Labrador',
        'date_of_birth' => '2020-01-01',
        'gender' => 'male',
        'chip_number' => '123456789'
    ]);
    echo "Dog created with ID: {$dog->id}\n";
} else {
    echo "Dog already exists with ID: {$dog->id}\n";
}

// Create a training log
$trainer = App\Models\User::where('role', 'trainer')->first();
if ($trainer) {
    $log = App\Models\TrainingLog::create([
        'dog_id' => $dog->id,
        'trainer_id' => $trainer->id,
        'progress_notes' => 'Demo Training Log für File Upload Testing',
        'behavior_notes' => 'Test-Notizen',
        'homework' => 'Test-Hausaufgaben'
    ]);
    echo "TrainingLog created with ID: {$log->id}\n";
} else {
    echo "No trainer found\n";
}

echo "Demo data created successfully!\n";
