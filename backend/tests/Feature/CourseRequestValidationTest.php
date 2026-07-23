<?php

declare(strict_types=1);

use App\Http\Requests\StoreCourseRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'course');

beforeEach(function () {
    $this->trainer = User::factory()->trainer()->create();
    $this->admin   = User::factory()->admin()->create();

    $this->baseData = [
        'name'            => 'Welpen Grundkurs',
        'description'     => 'Grundlagen für Welpen',
        'trainerId'       => $this->trainer->id,
        'courseType'      => 'group',
        'maxParticipants' => 10,
        'durationMinutes' => 60,
        'pricePerSession' => 25.00,
        'totalSessions'   => 8,
        'startDate'       => '2025-06-01',
        'endDate'         => '2025-08-01',
    ];

    $this->validRecurrenceRule = [
        'type'      => 'weekly',
        'weekday'   => 1,
        'startTime' => '09:00',
        'endTime'   => '10:00',
        'startDate' => '2025-06-02',
        'count'     => 8,
    ];
});

// ======== StoreCourseRequest — AC1 ========

it('weist die anfrage zurück wenn sessionsMode manual ist aber sessions fehlt', function () {
    $data = array_merge($this->baseData, ['sessionsMode' => 'manual']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sessions']);
});

// ======== StoreCourseRequest — AC2 ========

it('akzeptiert eine anfrage ohne sessionsMode zur abwärtskompatibilität', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $this->baseData)
        ->assertCreated();
});

// ======== StoreCourseRequest — AC3 ========

it('weist die anfrage zurück wenn recurrenceRule.count größer als 52 ist', function () {
    $data = array_merge($this->baseData, [
        'sessionsMode'   => 'recurrence',
        'recurrenceRule' => array_merge($this->validRecurrenceRule, ['count' => 53]),
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recurrenceRule.count']);
});

// ======== StoreCourseRequest — AC4 ========

it('speichert recurrence_rule in der datenbank wenn sessionsMode recurrence ist', function () {
    $data = array_merge($this->baseData, [
        'sessionsMode'   => 'recurrence',
        'recurrenceRule' => $this->validRecurrenceRule,
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertCreated();

    $course = Course::latest('id')->first();
    expect($course->recurrence_rule)->not->toBeNull();
});

// ======== StoreCourseRequest — Weitere Validierungsregeln ========

it('weist die anfrage zurück wenn sessionsMode recurrence ist aber recurrenceRule fehlt', function () {
    $data = array_merge($this->baseData, ['sessionsMode' => 'recurrence']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recurrenceRule']);
});

it('akzeptiert einen request mit sessionsMode manual und vollständigen sessions', function () {
    $data = array_merge($this->baseData, [
        'sessionsMode' => 'manual',
        'sessions'     => [
            [
                'sessionDate' => '2025-06-02',
                'startTime'   => '09:00',
                'endTime'     => '10:00',
            ],
        ],
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertCreated();
});

it('akzeptiert einen request mit sessionsMode recurrence und vollständiger recurrenceRule', function () {
    $data = array_merge($this->baseData, [
        'sessionsMode'   => 'recurrence',
        'recurrenceRule' => $this->validRecurrenceRule,
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertCreated();
});

it('weist die anfrage zurück wenn recurrenceRule.weekday bei type weekly fehlt', function () {
    $rule = $this->validRecurrenceRule;
    unset($rule['weekday']);

    $data = array_merge($this->baseData, [
        'sessionsMode'   => 'recurrence',
        'recurrenceRule' => $rule,
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recurrenceRule.weekday']);
});

it('weist die anfrage zurück wenn recurrenceRule.dayOfMonth bei type monthly fehlt', function () {
    $rule = array_merge($this->validRecurrenceRule, ['type' => 'monthly']);
    unset($rule['weekday']);

    $data = array_merge($this->baseData, [
        'sessionsMode'   => 'recurrence',
        'recurrenceRule' => $rule,
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/courses', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recurrenceRule.dayOfMonth']);
});

// ======== StoreCourseRequest — Getter-Tests ========

it('konvertiert recurrenceRule-keys von camelCase zu snake_case', function () {
    $user = $this->trainer;

    $request = StoreCourseRequest::create(
        '/api/v1/courses',
        'POST',
        array_merge($this->baseData, [
            'trainerId'      => $user->id,
            'sessionsMode'   => 'recurrence',
            'recurrenceRule' => $this->validRecurrenceRule,
        ])
    );
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->validateResolved();

    $converted = $request->getRecurrenceRule();

    expect($converted)->not->toBeNull();
    expect($converted)->toHaveKey('start_date');
    expect($converted['start_date'])->toBe('2025-06-02');
    expect($converted)->toHaveKey('start_time');
    expect($converted['start_time'])->toBe('09:00');
    expect($converted)->toHaveKey('end_time');
    expect($converted['end_time'])->toBe('10:00');
    expect($converted)->not->toHaveKey('startDate');
    expect($converted)->not->toHaveKey('startTime');
    expect($converted)->not->toHaveKey('endTime');
});

it('liefert null von getSessionsPayload wenn kein sessions-input vorhanden ist', function () {
    $user = $this->trainer;

    $request = StoreCourseRequest::create(
        '/api/v1/courses',
        'POST',
        array_merge($this->baseData, ['trainerId' => $user->id])
    );
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->getSessionsPayload())->toBeNull();
});

it('liefert null von getRecurrenceRule wenn kein recurrenceRule-input vorhanden ist', function () {
    $user = $this->trainer;

    $request = StoreCourseRequest::create(
        '/api/v1/courses',
        'POST',
        array_merge($this->baseData, ['trainerId' => $user->id])
    );
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->getRecurrenceRule())->toBeNull();
});

// ======== UpdateCourseRequest — Validierungsregeln ========

it('weist den update-request zurück wenn ein session-item ohne sessionDate gesendet wird', function () {
    $course = Course::factory()->create(['trainer_id' => $this->trainer->id]);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/courses/' . $course->id, [
            'sessions' => [
                [
                    'startTime' => '09:00',
                    'endTime'   => '10:00',
                    // sessionDate fehlt absichtlich
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sessions.0.sessionDate']);
});

it('weist den update-request zurück wenn ein session-item ohne startTime gesendet wird', function () {
    $course = Course::factory()->create(['trainer_id' => $this->trainer->id]);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/courses/' . $course->id, [
            'sessions' => [
                [
                    'sessionDate' => '2025-06-02',
                    // startTime fehlt absichtlich
                    'endTime' => '10:00',
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sessions.0.startTime']);
});

it('akzeptiert einen update-request ohne sessions-feld zur abwärtskompatibilität', function () {
    $course = Course::factory()->create(['trainer_id' => $this->trainer->id]);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/courses/' . $course->id, [
            'name' => 'Aktualisierter Kursname',
        ])
        ->assertOk();
});

it('weist den update-request zurück wenn recurrenceRule.type fehlt', function () {
    $course = Course::factory()->create(['trainer_id' => $this->trainer->id]);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/courses/' . $course->id, [
            'recurrenceRule' => [
                // type fehlt absichtlich
                'weekday'   => 1,
                'startTime' => '09:00',
                'endTime'   => '10:00',
                'startDate' => '2025-06-02',
                'count'     => 8,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recurrenceRule.type']);
});
