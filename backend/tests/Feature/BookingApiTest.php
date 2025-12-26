<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
    $this->dog = Dog::factory()->create(['customer_id' => $this->customer->id]);
    $this->session = TrainingSession::factory()->create([
        'max_participants' => 5,
        'status' => 'scheduled',
        'session_date' => now()->addDays(7),
    ]);
});

test('admin can list all bookings', function () {
    Booking::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/bookings')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'trainingSession',
                    'customer',
                    'dog',
                    'bookingDate',
                    'status',
                    'attended',
                ],
            ],
        ]);
});

test('customer can list their own bookings', function () {
    Booking::factory()->count(2)->create(['customer_id' => $this->customer->id]);
    Booking::factory()->count(3)->create(); // Other bookings

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/bookings?customerId=' . $this->customer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('bookings can be filtered by status', function () {
    Booking::factory()->count(2)->create(['status' => 'confirmed']);
    Booking::factory()->count(3)->create(['status' => 'pending']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/bookings?status=confirmed')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.status'))->toBe('confirmed');
});

test('bookings can be filtered by training session', function () {
    $session = TrainingSession::factory()->create();
    Booking::factory()->count(2)->create(['training_session_id' => $session->id]);
    Booking::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/bookings?trainingSessionId=' . $session->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('bookings can be filtered by dog', function () {
    $dog = Dog::factory()->create();
    Booking::factory()->count(2)->create(['dog_id' => $dog->id]);
    Booking::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/bookings?dogId=' . $dog->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('admin can view any booking', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/bookings/' . $booking->id)
        ->assertOk()
        ->assertJsonPath('data.id', $booking->id);
});

test('customer can view their own booking', function () {
    $booking = Booking::factory()->create([
        'customer_id' => $this->customer->id,
        'dog_id' => $this->dog->id,
    ]);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/bookings/' . $booking->id)
        ->assertOk()
        ->assertJsonPath('data.id', $booking->id);
});

test('customer cannot view other customers booking', function () {
    $otherBooking = Booking::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/bookings/' . $otherBooking->id)
        ->assertForbidden();
});

test('authenticated user can create a booking', function () {
    $data = [
        'trainingSessionId' => $this->session->id,
        'customerId' => $this->customer->id,
        'dogId' => $this->dog->id,
        'notes' => 'First session',
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/bookings', $data)
        ->assertCreated()
        ->assertJsonPath('data.customer.id', $this->customer->id)
        ->assertJsonPath('data.dog.id', $this->dog->id)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('bookings', [
        'training_session_id' => $this->session->id,
        'customer_id' => $this->customer->id,
        'dog_id' => $this->dog->id,
        'notes' => 'First session',
        'status' => 'pending',
    ]);
});

test('booking fails when session is full', function () {
    // Fill the session
    Booking::factory()->count(5)->create([
        'training_session_id' => $this->session->id,
        'status' => 'confirmed',
    ]);

    $data = [
        'trainingSessionId' => $this->session->id,
        'customerId' => $this->customer->id,
        'dogId' => $this->dog->id,
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/bookings', $data)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Training session is full. Please join the waiting list.');
});

test('booking fails when dog does not belong to customer', function () {
    $otherDog = Dog::factory()->create();

    $data = [
        'trainingSessionId' => $this->session->id,
        'customerId' => $this->customer->id,
        'dogId' => $otherDog->id,
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/bookings', $data)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The selected dog does not belong to this customer.');
});

test('booking fails for duplicate booking', function () {
    // Create existing booking
    Booking::factory()->create([
        'training_session_id' => $this->session->id,
        'dog_id' => $this->dog->id,
        'status' => 'confirmed',
    ]);

    $data = [
        'trainingSessionId' => $this->session->id,
        'customerId' => $this->customer->id,
        'dogId' => $this->dog->id,
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/bookings', $data)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This dog is already booked for this session.');
});

test('trainer can update booking status', function () {
    $booking = Booking::factory()->create(['status' => 'pending']);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/bookings/' . $booking->id, [
            'status' => 'confirmed',
            'attended' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.attended', true);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'confirmed',
        'attended' => true,
    ]);
});

test('customer cannot update booking', function () {
    $booking = Booking::factory()->create([
        'customer_id' => $this->customer->id,
    ]);

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/bookings/' . $booking->id, ['status' => 'confirmed'])
        ->assertForbidden();
});

test('customer can cancel their own booking', function () {
    $booking = Booking::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'confirmed',
    ]);

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/bookings/' . $booking->id . '/cancel', [
            'cancellationReason' => 'Schedule conflict',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
        'cancellation_reason' => 'Schedule conflict',
    ]);
});

test('customer cannot cancel already attended booking', function () {
    $booking = Booking::factory()->create([
        'customer_id' => $this->customer->id,
        'attended' => true,
    ]);

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/bookings/' . $booking->id . '/cancel')
        ->assertForbidden();
});

test('admin can cancel any booking', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($this->admin)
        ->postJson('/api/v1/bookings/' . $booking->id . '/cancel', [
            'cancellationReason' => 'Admin cancellation',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('trainer can confirm booking', function () {
    $booking = Booking::factory()->create(['status' => 'pending']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/bookings/' . $booking->id . '/confirm')
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'confirmed',
    ]);
});

test('admin can delete booking', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/bookings/' . $booking->id)
        ->assertNoContent();

    expect(Booking::find($booking->id))->toBeNull();
});

test('trainer cannot delete booking', function () {
    $booking = Booking::factory()->create();

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/bookings/' . $booking->id)
        ->assertForbidden();
});

test('customer cannot delete booking', function () {
    $booking = Booking::factory()->create([
        'customer_id' => $this->customer->id,
    ]);

    $this->actingAs($this->customerUser)
        ->deleteJson('/api/v1/bookings/' . $booking->id)
        ->assertForbidden();
});
