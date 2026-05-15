<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'course');

beforeEach(function () {
    $this->trainer = User::factory()->trainer()->create();

    $this->courseData = [
        'name'            => 'Welpen Grundkurs',
        'description'     => 'Basis-Training für Welpen.',
        'trainerId'       => $this->trainer->id,
        'courseType'      => 'group',
        'maxParticipants' => 8,
        'durationMinutes' => 60,
        'pricePerSession' => 25.00,
        'totalSessions'   => 4,
        'startDate'       => '2026-06-01',
        'endDate'         => '2026-07-01',
    ];
});

it('erstellt einen kurs mit wöchentlicher rekurrenz und legt die korrekte anzahl sessions in der datenbank an', function () {
    $data = array_merge($this->courseData, [
        'sessionsMode'   => 'recurrence',
        'recurrenceRule' => [
            'type'      => 'weekly',
            'weekday'   => 1,        // Montag
            'startTime' => '10:00',
            'endTime'   => '11:00',
            'startDate' => '2026-06-01', // 2026-06-01 ist ein Montag
            'count'     => 4,
        ],
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertCreated();

    $course = Course::latest()->first();
    $this->assertDatabaseCount('training_sessions', 4);
    expect($course->sessions()->count())->toBe(4);
});

it('erstellt einen kurs mit manuellen sessions und legt die korrekte anzahl sessions in der datenbank an', function () {
    $data = array_merge($this->courseData, [
        'sessionsMode' => 'manual',
        'sessions'     => [
            [
                'sessionDate' => '2026-06-08',
                'startTime'   => '10:00',
                'endTime'     => '11:00',
            ],
            [
                'sessionDate' => '2026-06-15',
                'startTime'   => '10:00',
                'endTime'     => '11:00',
            ],
        ],
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertCreated();

    $this->assertDatabaseCount('training_sessions', 2);
});

it('erstellt einen kurs ohne sessions wenn kein sessionsMode übergeben wird', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $this->courseData)
        ->assertCreated();

    $this->assertDatabaseCount('training_sessions', 0);
});

it('gibt validierungsfehler 422 zurück wenn recurrenceRule.count größer als 52 ist', function () {
    $data = array_merge($this->courseData, [
        'sessionsMode'   => 'recurrence',
        'recurrenceRule' => [
            'type'      => 'weekly',
            'weekday'   => 1,
            'startTime' => '10:00',
            'endTime'   => '11:00',
            'startDate' => '2026-06-01',
            'count'     => 53,
        ],
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recurrenceRule.count');
});
