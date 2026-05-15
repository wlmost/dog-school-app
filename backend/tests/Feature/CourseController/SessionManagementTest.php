<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'course');

beforeEach(function () {
    $this->trainer      = User::factory()->trainer()->create();
    $this->otherTrainer = User::factory()->trainer()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->course       = Course::factory()->create(['trainer_id' => $this->trainer->id]);
});

// ── storeSession ──────────────────────────────────────────────────────────────

it('speichert eine neue session für einen kurs als trainer-owner und gibt 201 zurück', function () {
    $this->actingAs($this->trainer)
        ->postJson("/api/v1/courses/{$this->course->id}/sessions", [
            'sessionDate' => '2026-06-08',
            'startTime'   => '10:00',
            'endTime'     => '11:00',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'sessionDate', 'startTime', 'endTime']]);

    $this->assertDatabaseHas('training_sessions', [
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);

    $session = TrainingSession::where('course_id', $this->course->id)->first();
    expect($session->session_date->toDateString())->toBe('2026-06-08');
});

it('weist die anfrage mit 403 zurück wenn ein anderer trainer eine session für einen fremden kurs anlegen will', function () {
    $this->actingAs($this->otherTrainer)
        ->postJson("/api/v1/courses/{$this->course->id}/sessions", [
            'sessionDate' => '2026-06-08',
            'startTime'   => '10:00',
            'endTime'     => '11:00',
        ])
        ->assertForbidden();
});

it('weist die anfrage mit 403 zurück wenn ein kunde eine session anlegen will', function () {
    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/courses/{$this->course->id}/sessions", [
            'sessionDate' => '2026-06-08',
            'startTime'   => '10:00',
            'endTime'     => '11:00',
        ])
        ->assertForbidden();
});

it('weist die anfrage mit 401 zurück wenn kein auth-header vorhanden ist', function () {
    $this->postJson("/api/v1/courses/{$this->course->id}/sessions", [
        'sessionDate' => '2026-06-08',
        'startTime'   => '10:00',
        'endTime'     => '11:00',
    ])
    ->assertUnauthorized();
});

it('gibt validierungsfehler 422 zurück wenn sessionDate fehlt', function () {
    $this->actingAs($this->trainer)
        ->postJson("/api/v1/courses/{$this->course->id}/sessions", [
            'startTime' => '10:00',
            'endTime'   => '11:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sessionDate');
});

// ── updateSession ─────────────────────────────────────────────────────────────

it('aktualisiert eine session ohne buchungen und gibt 200 ohne warnings-key zurück', function () {
    $session = TrainingSession::factory()->create([
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/courses/{$this->course->id}/sessions/{$session->id}", [
            'location' => 'Feld B',
        ])
        ->assertOk();

    $response->assertJsonMissingPath('meta.warnings');
    $this->assertDatabaseHas('training_sessions', [
        'id'       => $session->id,
        'location' => 'Feld B',
    ]);
});

it('aktualisiert eine session mit buchungen und gibt 200 mit warnings-key zurück', function () {
    $session = TrainingSession::factory()->create([
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);
    Booking::factory()->create(['training_session_id' => $session->id]);

    $response = $this->actingAs($this->trainer)
        ->putJson("/api/v1/courses/{$this->course->id}/sessions/{$session->id}", [
            'location' => 'Feld C',
        ])
        ->assertOk();

    $response->assertJsonStructure(['meta' => ['warnings']]);
});

it('gibt 404 zurück wenn die session beim update zu einem anderen kurs gehört (scope-check)', function () {
    $otherCourse    = Course::factory()->create(['trainer_id' => $this->otherTrainer->id]);
    $foreignSession = TrainingSession::factory()->create([
        'course_id'  => $otherCourse->id,
        'trainer_id' => $this->otherTrainer->id,
    ]);

    // Admin kann auf $this->course zugreifen — der Scope-Check (course_id !== course.id) greift trotzdem
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->putJson("/api/v1/courses/{$this->course->id}/sessions/{$foreignSession->id}", [
            'location' => 'Test',
        ])
        ->assertNotFound();
});

it('weist die anfrage mit 403 zurück wenn ein anderer trainer die session aktualisieren will', function () {
    $session = TrainingSession::factory()->create([
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);

    $this->actingAs($this->otherTrainer)
        ->putJson("/api/v1/courses/{$this->course->id}/sessions/{$session->id}", [
            'location' => 'Feld X',
        ])
        ->assertForbidden();
});

// ── destroySession ────────────────────────────────────────────────────────────

it('löscht eine session ohne buchungen und gibt 204 zurück', function () {
    $session = TrainingSession::factory()->create([
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);

    $this->actingAs($this->trainer)
        ->deleteJson("/api/v1/courses/{$this->course->id}/sessions/{$session->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('training_sessions', ['id' => $session->id]);
});

it('löscht eine session mit buchungen und gibt 200 mit deleted-true und warnings zurück', function () {
    $session = TrainingSession::factory()->create([
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);
    Booking::factory()->create(['training_session_id' => $session->id]);

    $response = $this->actingAs($this->trainer)
        ->deleteJson("/api/v1/courses/{$this->course->id}/sessions/{$session->id}")
        ->assertOk();

    $response->assertJsonPath('deleted', true)
             ->assertJsonStructure(['warnings']);
    $this->assertDatabaseMissing('training_sessions', ['id' => $session->id]);
});

it('gibt 404 zurück wenn die zu löschende session zu einem anderen kurs gehört (scope-check)', function () {
    $otherCourse    = Course::factory()->create(['trainer_id' => $this->otherTrainer->id]);
    $foreignSession = TrainingSession::factory()->create([
        'course_id'  => $otherCourse->id,
        'trainer_id' => $this->otherTrainer->id,
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/courses/{$this->course->id}/sessions/{$foreignSession->id}")
        ->assertNotFound();
});

it('weist die anfrage mit 403 zurück wenn ein kunde eine session löschen will', function () {
    $session = TrainingSession::factory()->create([
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);

    $this->actingAs($this->customerUser)
        ->deleteJson("/api/v1/courses/{$this->course->id}/sessions/{$session->id}")
        ->assertForbidden();
});

// ── publicShow ────────────────────────────────────────────────────────────────

it('liefert einen kurs mit sessions ohne auth-header zurück', function () {
    TrainingSession::factory()->count(2)->create([
        'course_id'  => $this->course->id,
        'trainer_id' => $this->trainer->id,
    ]);

    $this->getJson("/api/v1/public/courses/{$this->course->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->course->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'sessions']]);
});

it('gibt 404 zurück wenn der angefragte öffentliche kurs nicht existiert', function () {
    $this->getJson('/api/v1/public/courses/99999')
        ->assertNotFound();
});

it('enthält keine sensiblen trainer-daten in der öffentlichen kurs-antwort', function () {
    $response = $this->getJson("/api/v1/public/courses/{$this->course->id}")
        ->assertOk();

    $trainer = $response->json('data.trainer');
    expect($trainer)->toBeArray();
    expect($trainer)->toHaveKey('id');
    expect($trainer)->toHaveKey('firstName');
    expect($trainer)->toHaveKey('lastName');
    expect(array_key_exists('email', $trainer))->toBeFalse();
    expect(array_key_exists('phone', $trainer))->toBeFalse();
    expect(array_key_exists('mobilePhone', $trainer))->toBeFalse();
    expect(array_key_exists('street', $trainer))->toBeFalse();
});

it('liefert sessions in aufsteigender reihenfolge nach session_date zurück', function () {
    // Absichtlich in umgekehrter Reihenfolge anlegen
    TrainingSession::factory()->create([
        'course_id'    => $this->course->id,
        'trainer_id'   => $this->trainer->id,
        'session_date' => '2026-07-15',
    ]);
    TrainingSession::factory()->create([
        'course_id'    => $this->course->id,
        'trainer_id'   => $this->trainer->id,
        'session_date' => '2026-06-10',
    ]);
    TrainingSession::factory()->create([
        'course_id'    => $this->course->id,
        'trainer_id'   => $this->trainer->id,
        'session_date' => '2026-08-20',
    ]);

    $response = $this->getJson("/api/v1/public/courses/{$this->course->id}")
        ->assertOk();

    $dates = collect($response->json('data.sessions'))->pluck('sessionDate');
    expect($dates->toArray())->toBe($dates->sort()->values()->toArray());
});
