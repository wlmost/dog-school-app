<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\Dog;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer belongs to user', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    expect($customer->user)->toBeInstanceOf(User::class);
    expect($customer->user->id)->toBe($user->id);
});

test('customer can have multiple dogs', function () {
    $customer = Customer::factory()->create();
    $dog1 = Dog::factory()->create(['customer_id' => $customer->id]);
    $dog2 = Dog::factory()->create(['customer_id' => $customer->id]);

    expect($customer->dogs)->toHaveCount(2);
    expect($customer->dogs->pluck('id'))->toContain($dog1->id, $dog2->id);
});

test('customer full_address accessor works correctly', function () {
    $customer = Customer::factory()->create([
        'address_line1' => '123 Main St',
        'address_line2' => null,
        'postal_code' => '12345',
        'city' => 'Berlin',
        'country' => 'Germany',
    ]);

    expect($customer->full_address)->toBe('123 Main St, 12345 Berlin, Germany');
});

test('dog belongs to customer', function () {
    $customer = Customer::factory()->create();
    $dog = Dog::factory()->create(['customer_id' => $customer->id]);

    expect($dog->customer)->toBeInstanceOf(Customer::class);
    expect($dog->customer->id)->toBe($customer->id);
});

test('dog age accessor calculates correctly', function () {
    $dog = Dog::factory()->create([
        'date_of_birth' => now()->subYears(3),
    ]);

    expect($dog->age)->toBe(3);
});

test('dog soft deletes work correctly', function () {
    $dog = Dog::factory()->create();
    
    $dog->delete();
    
    expect($dog->trashed())->toBeTrue();
    $this->assertDatabaseHas('dogs', [
        'id' => $dog->id,
    ]);
});

test('course belongs to trainer', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);
    $course = Course::factory()->create(['trainer_id' => $trainer->id]);

    expect($course->trainer)->toBeInstanceOf(User::class);
    expect($course->trainer->id)->toBe($trainer->id);
});

test('course has many training sessions', function () {
    $course = Course::factory()->create();
    $session1 = TrainingSession::factory()->create(['course_id' => $course->id]);
    $session2 = TrainingSession::factory()->create(['course_id' => $course->id]);

    expect($course->sessions)->toHaveCount(2);
});

test('training session belongs to course', function () {
    $course = Course::factory()->create();
    $session = TrainingSession::factory()->create(['course_id' => $course->id]);

    expect($session->course)->toBeInstanceOf(Course::class);
    expect($session->course->id)->toBe($course->id);
});

test('training session can calculate available spots', function () {
    $session = TrainingSession::factory()->create(['max_participants' => 5]);
    
    Booking::factory()->count(2)->create([
        'training_session_id' => $session->id,
        'status' => 'confirmed',
    ]);

    expect($session->available_spots)->toBe(3);
});

test('booking belongs to session customer and dog', function () {
    $session = TrainingSession::factory()->create();
    $customer = Customer::factory()->create();
    $dog = Dog::factory()->create(['customer_id' => $customer->id]);
    
    $booking = Booking::factory()->create([
        'training_session_id' => $session->id,
        'customer_id' => $customer->id,
        'dog_id' => $dog->id,
    ]);

    expect($booking->session)->toBeInstanceOf(TrainingSession::class);
    expect($booking->customer)->toBeInstanceOf(Customer::class);
    expect($booking->dog)->toBeInstanceOf(Dog::class);
});

test('customer credit can use credits', function () {
    $credit = CustomerCredit::factory()->create([
        'remaining_credits' => 5,
        'status' => 'active',
    ]);

    $result = $credit->useCredit(2);

    expect($result)->toBeTrue();
    expect($credit->remaining_credits)->toBe(3);
    expect($credit->status)->toBe('active');
});

test('customer credit depletes when all credits used', function () {
    $credit = CustomerCredit::factory()->create([
        'remaining_credits' => 1,
        'status' => 'active',
    ]);

    $credit->useCredit(1);

    expect($credit->remaining_credits)->toBe(0);
    expect($credit->status)->toBe('used');
});

test('user has customer relationship for customer role', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    expect($user->customer)->toBeInstanceOf(Customer::class);
    expect($user->customer->id)->toBe($customer->id);
});

test('user has courses relationship for trainer role', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);
    $course1 = Course::factory()->create(['trainer_id' => $trainer->id]);
    $course2 = Course::factory()->create(['trainer_id' => $trainer->id]);

    expect($trainer->courses)->toHaveCount(2);
});
