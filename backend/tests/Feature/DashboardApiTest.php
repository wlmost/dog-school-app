<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Invoice;
use App\Models\TrainingSession;
use App\Models\User;

beforeEach(function () {
    // Create test users with different roles
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
    
    // Create customer model linked to customer user
    $this->customer = Customer::factory()->for($this->customerUser, 'user')->create();
    
    // Create assigned customer for trainer
    $this->assignedCustomer = Customer::factory()
        ->for($this->trainer, 'trainer')
        ->create();
});

describe('Admin Dashboard', function () {
    it('returns dashboard data for admin users', function () {
        $this->actingAs($this->admin);

        // Create test data
        Customer::factory()->count(5)->create();
        Dog::factory()->count(10)->create();
        Course::factory()->count(3)->create(['status' => 'active']);
        Invoice::factory()->count(2)->create(['status' => 'sent']);
        
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'customers',
                    'dogs',
                    'courses',
                    'invoices',
                    'bookings',
                ],
                'upcomingSessions',
                'recentBookings',
            ]);

        expect($response->json('stats.customers'))->toBeGreaterThanOrEqual(5);
        expect($response->json('stats.dogs'))->toBeGreaterThanOrEqual(10);
        expect($response->json('stats.courses'))->toBeGreaterThanOrEqual(3);
    });

    it('shows upcoming training sessions for admin', function () {
        $this->actingAs($this->admin);

        $course = Course::factory()->create(['status' => 'active']);
        TrainingSession::factory()
            ->for($course)
            ->count(3)
            ->create([
                'session_date' => now()->addDays(5),
                'start_time' => '10:00:00',
            ]);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('upcomingSessions'))->toBeArray()
            ->and(count($response->json('upcomingSessions')))->toBeGreaterThanOrEqual(3);
        expect($response->json('upcomingSessions.0'))->toHaveKeys(['id', 'course', 'date', 'time', 'participants']);
    });

    it('shows recent bookings for admin', function () {
        $this->actingAs($this->admin);

        $course = Course::factory()->create();
        $session = TrainingSession::factory()->for($course)->create();
        $customer = Customer::factory()->create();
        $dog = Dog::factory()->for($customer)->create();
        
        Booking::factory()
            ->for($session, 'trainingSession')
            ->for($customer)
            ->for($dog)
            ->count(3)
            ->create();

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('recentBookings'))->toBeArray()
            ->and(count($response->json('recentBookings')))->toBeGreaterThanOrEqual(3);
        expect($response->json('recentBookings.0'))->toHaveKeys(['id', 'customer', 'dog', 'course', 'status']);
    });
});

describe('Trainer Dashboard', function () {
    it('returns dashboard data for trainer users', function () {
        $this->actingAs($this->trainer);

        // Create trainer's course
        $course = Course::factory()->for($this->trainer, 'trainer')->create(['status' => 'active']);
        
        // Create dogs for assigned customer
        Dog::factory()->for($this->assignedCustomer)->count(3)->create();

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'customers',
                    'dogs',
                    'courses',
                    'invoices',
                    'bookings',
                ],
                'upcomingSessions',
                'recentBookings',
            ]);

        expect($response->json('stats.customers'))->toBeGreaterThanOrEqual(1);
        expect($response->json('stats.dogs'))->toBeGreaterThanOrEqual(3);
        expect($response->json('stats.courses'))->toBeGreaterThanOrEqual(1);
    });

    it('only shows assigned customers statistics for trainer', function () {
        $this->actingAs($this->trainer);

        // Create dogs for assigned customer
        Dog::factory()->for($this->assignedCustomer)->count(2)->create();
        
        // Create dogs for non-assigned customer (should not be counted)
        $otherCustomer = Customer::factory()->create();
        Dog::factory()->for($otherCustomer)->count(5)->create();

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('stats.customers'))->toBe(1);
        expect($response->json('stats.dogs'))->toBe(2);
    });

    it('only shows trainer courses in upcoming sessions', function () {
        $this->actingAs($this->trainer);

        // Trainer's course with session
        $trainerCourse = Course::factory()->for($this->trainer, 'trainer')->create(['status' => 'active']);
        TrainingSession::factory()
            ->for($trainerCourse)
            ->create([
                'session_date' => now()->addDays(3),
                'start_time' => '14:00:00',
            ]);

        // Other trainer's course (should not appear)
        $otherTrainer = User::factory()->create(['role' => 'trainer']);
        $otherCourse = Course::factory()->for($otherTrainer, 'trainer')->create(['status' => 'active']);
        TrainingSession::factory()
            ->for($otherCourse)
            ->create([
                'session_date' => now()->addDays(3),
                'start_time' => '16:00:00',
            ]);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('upcomingSessions'))->toHaveCount(1);
        expect($response->json('upcomingSessions.0.course'))->toBe($trainerCourse->name);
    });
});

describe('Customer Dashboard', function () {
    it('returns dashboard data for customer users', function () {
        $this->actingAs($this->customerUser);

        // Create customer's dogs
        Dog::factory()->for($this->customer)->count(2)->create();
        
        // Create invoices for customer
        Invoice::factory()->for($this->customer, 'customer')->count(1)->create(['status' => 'sent']);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'dogs',
                    'courses',
                    'invoices',
                    'bookings',
                ],
                'upcomingSessions',
                'recentBookings',
            ]);

        expect($response->json('stats.dogs'))->toBe(2);
        expect($response->json('stats.invoices'))->toBe(1);
    });

    it('does not include customers count in customer dashboard', function () {
        $this->actingAs($this->customerUser);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('stats'))->not->toHaveKey('customers');
    });

    it('shows upcoming sessions for customer dogs', function () {
        $this->actingAs($this->customerUser);

        $dog = Dog::factory()->for($this->customer)->create();
        $course = Course::factory()->create(['status' => 'active']);
        $session = TrainingSession::factory()
            ->for($course)
            ->create([
                'session_date' => now()->addDays(5),
                'start_time' => '11:00:00',
            ]);

        Booking::factory()
            ->for($session, 'trainingSession')
            ->for($this->customer)
            ->for($dog)
            ->create(['status' => 'confirmed']);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('upcomingSessions'))->toHaveCount(1);
        expect($response->json('upcomingSessions.0'))->toHaveKeys(['id', 'course', 'dog', 'date', 'time', 'status']);
        expect($response->json('upcomingSessions.0.dog'))->toBe($dog->name);
    });

    it('shows recent bookings for customer', function () {
        $this->actingAs($this->customerUser);

        $dog = Dog::factory()->for($this->customer)->create();
        $course = Course::factory()->create();
        $session = TrainingSession::factory()->for($course)->create();

        Booking::factory()
            ->for($session, 'trainingSession')
            ->for($this->customer)
            ->for($dog)
            ->count(2)
            ->create();

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('recentBookings'))->toHaveCount(2);
        expect($response->json('recentBookings.0'))->toHaveKeys(['id', 'dog', 'course', 'date', 'status']);
        expect($response->json('recentBookings.0.dog'))->toBe($dog->name);
    });

    it('only shows customer own data not other customers', function () {
        $this->actingAs($this->customerUser);

        // Create own dogs
        Dog::factory()->for($this->customer)->count(2)->create();
        
        // Create other customer's dogs (should not be counted)
        $otherCustomer = Customer::factory()->create();
        Dog::factory()->for($otherCustomer)->count(5)->create();

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertOk();
        expect($response->json('stats.dogs'))->toBe(2);
    });
});
