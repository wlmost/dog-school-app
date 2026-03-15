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
    $this->otherTrainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
});

test('authenticated user can list courses', function () {
    Course::factory()->count(3)->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/courses')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'trainer',
                    'maxParticipants',
                    'price',
                    'startDate',
                    'endDate',
                    'status',
                ],
            ],
        ]);
});

test('courses can be filtered by trainer', function () {
    Course::factory()->count(2)->create(['trainer_id' => $this->trainer->id]);
    Course::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/courses?trainerId=' . $this->trainer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('courses can be filtered by status', function () {
    Course::factory()->count(2)->create(['status' => 'active']);
    Course::factory()->count(3)->create(['status' => 'planned']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/courses?status=active')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can filter active courses only', function () {
    // Active course (active status and not ended)
    Course::factory()->create([
        'status' => 'active',
        'end_date' => now()->addDays(30),
    ]);

    // Ended course
    Course::factory()->create([
        'status' => 'active',
        'end_date' => now()->subDays(10),
    ]);

    // Planned course
    Course::factory()->create([
        'status' => 'planned',
        'end_date' => now()->addDays(30),
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/courses?activeOnly=true')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

test('courses can be searched by name', function () {
    Course::factory()->create(['name' => 'Welpen Grundkurs']);
    Course::factory()->create(['name' => 'Advanced Agility']);
    Course::factory()->create(['name' => 'Welpen Spielstunde']);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/courses?search=Welpen')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('trainer can create course', function () {
    $data = [
        'name' => 'Beginner Obedience',
        'description' => 'Basic obedience training for all dogs',
        'trainerId' => $this->trainer->id,
        'courseType' => 'group',
        'maxParticipants' => 10,
        'durationMinutes' => 60,
        'pricePerSession' => 25.00,
        'totalSessions' => 8,
        'startDate' => '2025-02-01',
        'endDate' => '2025-03-15',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Beginner Obedience')
        ->assertJsonPath('data.status', 'planned')
        ->assertJsonPath('data.maxParticipants', 10);

    $this->assertDatabaseHas('courses', [
        'name' => 'Beginner Obedience',
        'trainer_id' => $this->trainer->id,
        'status' => 'planned',
    ]);
});

test('customer cannot create course', function () {
    $data = [
        'name' => 'Test Course',
        'trainerId' => $this->trainer->id,
        'courseType' => 'group',
        'maxParticipants' => 10,
        'durationMinutes' => 60,
        'pricePerSession' => 25.00,
        'totalSessions' => 8,
        'startDate' => '2025-02-01',
        'endDate' => '2025-03-01',
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/courses', $data)
        ->assertForbidden();
});

test('course creation validates end date is after start date', function () {
    $data = [
        'name' => 'Test Course',
        'trainerId' => $this->trainer->id,
        'courseType' => 'group',
        'maxParticipants' => 10,
        'durationMinutes' => 60,
        'pricePerSession' => 25.00,
        'totalSessions' => 8,
        'startDate' => '2025-03-01',
        'endDate' => '2025-02-01', // Before start date
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endDate');
});

test('user can view course details', function () {
    $course = Course::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/courses/' . $course->id)
        ->assertOk()
        ->assertJsonPath('data.id', $course->id)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'trainer',
                'sessions',
            ],
        ]);
});

test('trainer can update own course', function () {
    $course = Course::factory()->create(['trainer_id' => $this->trainer->id]);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/courses/' . $course->id, [
            'name' => 'Updated Course Name',
            'status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Course Name')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('courses', [
        'id' => $course->id,
        'name' => 'Updated Course Name',
        'status' => 'active',
    ]);
});

test('trainer cannot update other trainers course', function () {
    $course = Course::factory()->create(['trainer_id' => $this->otherTrainer->id]);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/courses/' . $course->id, ['name' => 'Hacked'])
        ->assertForbidden();
});

test('admin can update any course', function () {
    $course = Course::factory()->create(['trainer_id' => $this->trainer->id]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/courses/' . $course->id, [
            'status' => 'cancelled',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('customer cannot update course', function () {
    $course = Course::factory()->create();

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/courses/' . $course->id, ['name' => 'Hacked'])
        ->assertForbidden();
});

test('admin can delete course without sessions', function () {
    $course = Course::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/courses/' . $course->id)
        ->assertNoContent();

    expect(Course::find($course->id))->toBeNull();
});

test('cannot delete course with existing sessions', function () {
    $course = Course::factory()->create();
    TrainingSession::factory()->create(['course_id' => $course->id]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/courses/' . $course->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Cannot delete course with existing training sessions.');
});

test('trainer cannot delete course', function () {
    $course = Course::factory()->create(['trainer_id' => $this->trainer->id]);

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/courses/' . $course->id)
        ->assertForbidden();
});

test('can view course sessions', function () {
    $course = Course::factory()->create();
    TrainingSession::factory()->count(3)->create(['course_id' => $course->id]);
    TrainingSession::factory()->count(2)->create(); // Other sessions

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/courses/' . $course->id . '/sessions')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('course sessions are ordered by date and time', function () {
    $course = Course::factory()->create();

    TrainingSession::factory()->create([
        'course_id' => $course->id,
        'session_date' => '2025-02-01',
        'start_time' => '14:00',
    ]);
    TrainingSession::factory()->create([
        'course_id' => $course->id,
        'session_date' => '2025-01-15',
        'start_time' => '10:00',
    ]);
    TrainingSession::factory()->create([
        'course_id' => $course->id,
        'session_date' => '2025-01-15',
        'start_time' => '14:00',
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/courses/' . $course->id . '/sessions')
        ->assertOk();

    expect($response->json('data.0.sessionDate'))->toBe('2025-01-15');
    expect($response->json('data.0.startTime'))->toBe('10:00:00');
    expect($response->json('data.1.sessionDate'))->toBe('2025-01-15');
    expect($response->json('data.1.startTime'))->toBe('14:00:00');
    expect($response->json('data.2.sessionDate'))->toBe('2025-02-01');
});

test('can view course participant statistics', function () {
    $course = Course::factory()->create(['max_participants' => 10]);

    // Create sessions for this course
    $session1 = TrainingSession::factory()->create([
        'course_id' => $course->id,
        'max_participants' => 5,
    ]);
    $session2 = TrainingSession::factory()->create([
        'course_id' => $course->id,
        'max_participants' => 5,
    ]);

    // Create bookings
    Booking::factory()->count(3)->create([
        'training_session_id' => $session1->id,
        'status' => 'confirmed',
    ]);
    Booking::factory()->count(2)->create([
        'training_session_id' => $session2->id,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/courses/' . $course->id . '/participants')
        ->assertOk();

    expect($response->json('courseId'))->toBe($course->id);
    expect($response->json('maxParticipants'))->toBe(10);
    expect($response->json('totalBookings'))->toBe(5);
    expect($response->json('totalCapacity'))->toBe(10);
    expect($response->json('sessionsCount'))->toBe(2);
});

test('courses are ordered by start date descending', function () {
    Course::factory()->create(['start_date' => '2025-01-15']);
    Course::factory()->create(['start_date' => '2025-03-01']);
    Course::factory()->create(['start_date' => '2025-02-01']);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/courses')
        ->assertOk();

    expect($response->json('data.0.startDate'))->toBe('2025-03-01');
    expect($response->json('data.1.startDate'))->toBe('2025-02-01');
    expect($response->json('data.2.startDate'))->toBe('2025-01-15');
});
