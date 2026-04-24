<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
    $this->course = Course::factory()->create();
});

test('authenticated user can list training sessions', function () {
    TrainingSession::factory()->count(3)->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/training-sessions')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'course',
                    'trainer',
                    'sessionDate',
                    'startTime',
                    'endTime',
                    'maxParticipants',
                    'status',
                ],
            ],
        ]);
});

test('sessions can be filtered by course', function () {
    $course = Course::factory()->create();
    TrainingSession::factory()->count(2)->create(['course_id' => $course->id]);
    TrainingSession::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-sessions?courseId=' . $course->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('sessions can be filtered by trainer', function () {
    $trainer = User::factory()->create(['role' => 'trainer']);
    TrainingSession::factory()->count(2)->create(['trainer_id' => $trainer->id]);
    TrainingSession::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-sessions?trainerId=' . $trainer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('sessions can be filtered by date range', function () {
    TrainingSession::factory()->create(['session_date' => '2025-01-15']);
    TrainingSession::factory()->create(['session_date' => '2025-02-15']);
    TrainingSession::factory()->create(['session_date' => '2025-03-15']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-sessions?startDate=2025-02-01&endDate=2025-02-28')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.sessionDate'))->toBe('2025-02-15');
});

test('sessions can be filtered by status', function () {
    TrainingSession::factory()->count(2)->create(['status' => 'scheduled']);
    TrainingSession::factory()->count(3)->create(['status' => 'completed']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-sessions?status=scheduled')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can filter available sessions only', function () {
    // Available session (not full, in future)
    $availableSession = TrainingSession::factory()->create([
        'max_participants' => 5,
        'status' => 'scheduled',
        'session_date' => now()->addDays(7),
    ]);

    // Full session
    $fullSession = TrainingSession::factory()->create([
        'max_participants' => 2,
        'status' => 'scheduled',
        'session_date' => now()->addDays(7),
    ]);
    Booking::factory()->count(2)->create([
        'training_session_id' => $fullSession->id,
        'status' => 'confirmed',
    ]);

    // Past session
    TrainingSession::factory()->create([
        'max_participants' => 5,
        'status' => 'completed',
        'session_date' => now()->subDays(7),
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/training-sessions?availableOnly=true')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($availableSession->id);
});

test('user can view training session details', function () {
    $session = TrainingSession::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/training-sessions/' . $session->id)
        ->assertOk()
        ->assertJsonPath('data.id', $session->id)
        ->assertJsonStructure([
            'data' => [
                'id',
                'course',
                'trainer',
                'sessionDate',
                'startTime',
                'endTime',
                'maxParticipants',
                'location',
                'status',
                'bookings',
            ],
        ]);
});

test('user can view bookings for a training session', function () {
    $session = TrainingSession::factory()->create();
    Booking::factory()->count(3)->create(['training_session_id' => $session->id]);
    Booking::factory()->count(2)->create(); // Other bookings

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/training-sessions/' . $session->id . '/bookings')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('can check training session availability', function () {
    $session = TrainingSession::factory()->create([
        'max_participants' => 5,
        'status' => 'scheduled',
    ]);

    // Create 3 confirmed bookings
    Booking::factory()->count(3)->create([
        'training_session_id' => $session->id,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/training-sessions/' . $session->id . '/availability')
        ->assertOk();

    expect($response->json('sessionId'))->toBe($session->id);
    expect($response->json('maxParticipants'))->toBe(5);
    expect($response->json('currentBookings'))->toBe(3);
    expect($response->json('availableSpots'))->toBe(2);
    expect($response->json('isFull'))->toBe(false);
    expect($response->json('isAvailable'))->toBe(true);
});

test('availability shows session as full when max participants reached', function () {
    $session = TrainingSession::factory()->create([
        'max_participants' => 3,
        'status' => 'scheduled',
    ]);

    // Create 3 confirmed bookings (fill the session)
    Booking::factory()->count(3)->create([
        'training_session_id' => $session->id,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/training-sessions/' . $session->id . '/availability')
        ->assertOk();

    expect($response->json('availableSpots'))->toBe(0);
    expect($response->json('isFull'))->toBe(true);
    expect($response->json('isAvailable'))->toBe(false);
});

test('availability does not count cancelled bookings', function () {
    $session = TrainingSession::factory()->create([
        'max_participants' => 5,
        'status' => 'scheduled',
    ]);

    // 2 confirmed, 2 cancelled bookings
    Booking::factory()->count(2)->create([
        'training_session_id' => $session->id,
        'status' => 'confirmed',
    ]);
    Booking::factory()->count(2)->create([
        'training_session_id' => $session->id,
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/training-sessions/' . $session->id . '/availability')
        ->assertOk();

    expect($response->json('currentBookings'))->toBe(2);
    expect($response->json('availableSpots'))->toBe(3);
});

test('sessions are ordered by date and time', function () {
    TrainingSession::factory()->create([
        'session_date' => '2025-02-01',
        'start_time' => '14:00',
    ]);
    TrainingSession::factory()->create([
        'session_date' => '2025-01-15',
        'start_time' => '10:00',
    ]);
    TrainingSession::factory()->create([
        'session_date' => '2025-01-15',
        'start_time' => '14:00',
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/training-sessions')
        ->assertOk();

    expect($response->json('data.0.sessionDate'))->toBe('2025-01-15');
    expect($response->json('data.0.startTime'))->toBe('10:00:00');
    expect($response->json('data.1.sessionDate'))->toBe('2025-01-15');
    expect($response->json('data.1.startTime'))->toBe('14:00:00');
    expect($response->json('data.2.sessionDate'))->toBe('2025-02-01');
});
