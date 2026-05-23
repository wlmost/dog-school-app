<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\CourseRun;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'course');

beforeEach(function () {
    $this->trainer      = User::factory()->trainer()->create();
    $this->otherTrainer = User::factory()->trainer()->create();

    // Customer A — the user who will be doing the bookings
    $this->customerUser = User::factory()->customer()->create();
    $this->customer     = Customer::factory()->create(['user_id' => $this->customerUser->id]);
    $this->dog          = Dog::factory()->create(['customer_id' => $this->customer->id]);

    // Customer B — a different customer (used for authorization tests)
    $this->otherCustomerUser = User::factory()->customer()->create();
    $this->otherCustomer     = Customer::factory()->create(['user_id' => $this->otherCustomerUser->id]);

    $this->course = Course::factory()->create(['trainer_id' => $this->trainer->id]);
});

// ── index ─────────────────────────────────────────────────────────────────────

it('gibt eine leere liste zurück wenn der kurs noch keine runs hat', function () {
    $this->actingAs($this->customerUser)
        ->getJson("/api/v1/courses/{$this->course->id}/runs")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('gibt alle runs mit ihren sessions zurück', function () {
    $run = CourseRun::factory()->create(['course_id' => $this->course->id]);
    TrainingSession::factory()->count(2)->create([
        'course_id'     => $this->course->id,
        'course_run_id' => $run->id,
        'trainer_id'    => $this->trainer->id,
    ]);

    $response = $this->actingAs($this->customerUser)
        ->getJson("/api/v1/courses/{$this->course->id}/runs")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'courseId', 'startDate', 'status', 'sessions'],
        ],
    ]);

    expect($response->json('data.0.sessions'))->toHaveCount(2);
});

// ── store ─────────────────────────────────────────────────────────────────────

it('erstellt einen neuen run als trainer-owner und gibt 201 zurück', function () {
    $response = $this->actingAs($this->trainer)
        ->postJson("/api/v1/courses/{$this->course->id}/runs", [
            'startDate' => '2026-09-01',
            'endDate'   => '2026-10-01',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'courseId', 'startDate', 'endDate', 'status']]);

    // Verify the JSON response contains the correct dates (avoids SQLite datetime quirks)
    expect($response->json('data.startDate'))->toBe('2026-09-01');
    expect($response->json('data.endDate'))->toBe('2026-10-01');
    expect($response->json('data.status'))->toBe('active');

    $this->assertDatabaseHas('course_runs', [
        'course_id' => $this->course->id,
        'status'    => 'active',
    ]);
});

it('weist einen kunden beim anlegen eines runs mit 403 zurück', function () {
    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/courses/{$this->course->id}/runs", [
            'startDate' => '2026-09-01',
        ])
        ->assertForbidden();
});

it('weist einen anderen trainer beim anlegen eines runs mit 403 zurück', function () {
    $this->actingAs($this->otherTrainer)
        ->postJson("/api/v1/courses/{$this->course->id}/runs", [
            'startDate' => '2026-09-01',
        ])
        ->assertForbidden();
});

it('gibt 401 zurück wenn kein auth-header vorhanden ist', function () {
    $this->postJson("/api/v1/courses/{$this->course->id}/runs", [
        'startDate' => '2026-09-01',
    ])
    ->assertUnauthorized();
});

// ── book ──────────────────────────────────────────────────────────────────────

it('bucht alle sessions eines runs und gibt 201 mit den buchungen zurück', function () {
    $run = CourseRun::factory()->create([
        'course_id' => $this->course->id,
        'status'    => 'active',
    ]);

    TrainingSession::factory()->count(3)->create([
        'course_id'        => $this->course->id,
        'course_run_id'    => $run->id,
        'trainer_id'       => $this->trainer->id,
        'status'           => 'scheduled',
        'max_participants' => 5,
    ]);

    $response = $this->actingAs($this->customerUser)
        ->postJson("/api/v1/course-runs/{$run->id}/book", [
            'customerId' => $this->customer->id,
            'dogId'      => $this->dog->id,
        ]);

    $response->assertCreated();
    expect($response->json('skipped'))->toHaveCount(0);

    $this->assertDatabaseCount('bookings', 3);
    $this->assertDatabaseHas('bookings', [
        'course_run_id' => $run->id,
        'customer_id'   => $this->customer->id,
        'dog_id'        => $this->dog->id,
        'status'        => 'pending',
    ]);
});

it('gibt 422 zurück wenn der run keine buchbaren sessions hat', function () {
    $run = CourseRun::factory()->create(['course_id' => $this->course->id]);
    // No sessions in this run

    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/course-runs/{$run->id}/book", [
            'customerId' => $this->customer->id,
            'dogId'      => $this->dog->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Keine buchbaren Termine in diesem Kursdurchlauf.');
});

it('überspringt volle sessions und gibt die skipped-liste zurück', function () {
    $run = CourseRun::factory()->create(['course_id' => $this->course->id]);

    // Session 1: full (max_participants = 1, already has one booking)
    $fullSession = TrainingSession::factory()->create([
        'course_id'        => $this->course->id,
        'course_run_id'    => $run->id,
        'trainer_id'       => $this->trainer->id,
        'status'           => 'scheduled',
        'max_participants' => 1,
    ]);
    $otherCustomer = Customer::factory()->create();
    $otherDog      = Dog::factory()->create(['customer_id' => $otherCustomer->id]);
    Booking::factory()->create([
        'training_session_id' => $fullSession->id,
        'customer_id'         => $otherCustomer->id,
        'dog_id'              => $otherDog->id,
        'status'              => 'confirmed',
    ]);

    // Session 2: available
    $availableSession = TrainingSession::factory()->create([
        'course_id'        => $this->course->id,
        'course_run_id'    => $run->id,
        'trainer_id'       => $this->trainer->id,
        'status'           => 'scheduled',
        'max_participants' => 5,
    ]);

    $response = $this->actingAs($this->customerUser)
        ->postJson("/api/v1/course-runs/{$run->id}/book", [
            'customerId' => $this->customer->id,
            'dogId'      => $this->dog->id,
        ])
        ->assertCreated();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('skipped'))->toHaveCount(1);
    expect($response->json('skipped.0'))->toContain('ausgebucht');
});

it('gibt 422 zurück wenn alle sessions ausgebucht sind', function () {
    $run = CourseRun::factory()->create(['course_id' => $this->course->id]);

    $fullSession = TrainingSession::factory()->create([
        'course_id'        => $this->course->id,
        'course_run_id'    => $run->id,
        'trainer_id'       => $this->trainer->id,
        'status'           => 'scheduled',
        'max_participants' => 1,
    ]);
    $other    = Customer::factory()->create();
    $otherDog = Dog::factory()->create(['customer_id' => $other->id]);
    Booking::factory()->create([
        'training_session_id' => $fullSession->id,
        'customer_id'         => $other->id,
        'dog_id'              => $otherDog->id,
        'status'              => 'confirmed',
    ]);

    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/course-runs/{$run->id}/book", [
            'customerId' => $this->customer->id,
            'dogId'      => $this->dog->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Keine Termine konnten gebucht werden.');
});

it('gibt 403 zurück wenn ein kunde versucht für einen anderen kunden zu buchen', function () {
    $run = CourseRun::factory()->create(['course_id' => $this->course->id]);

    // Customer A tries to book with Customer B's ID
    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/course-runs/{$run->id}/book", [
            'customerId' => $this->otherCustomer->id,
            'dogId'      => $this->dog->id,
        ])
        ->assertForbidden();
});
