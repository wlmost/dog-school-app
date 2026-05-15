<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\CourseSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'course');

beforeEach(function () {
    $this->service = new CourseSessionService();
    $this->trainer = User::factory()->trainer()->create();
    $this->course  = Course::factory()->create(['trainer_id' => $this->trainer->id]);
});

// ---------------------------------------------------------------------------
// syncSessions
// ---------------------------------------------------------------------------

describe('syncSessions', function () {
    it('löscht alle unbuchten sessions wenn leeres sessions-array übergeben wird', function () {
        TrainingSession::factory()->count(3)->create(['course_id' => $this->course->id]);

        $warnings = $this->service->syncSessions($this->course, []);

        $this->assertDatabaseCount('training_sessions', 0);
        expect($warnings)->toBeEmpty();
    });

    it('gibt leeres warnings-array zurück wenn keine gebuchten sessions vorhanden sind', function () {
        TrainingSession::factory()->create(['course_id' => $this->course->id]);

        $warnings = $this->service->syncSessions($this->course, []);

        expect($warnings)->toBeArray();
        expect($warnings)->toBeEmpty();
    });

    it('legt neue sessions in der datenbank an mit korrekter course_id', function () {
        $sessionData = [
            [
                'trainer_id'       => $this->trainer->id,
                'session_date'     => '2025-06-02',
                'start_time'       => '09:00',
                'end_time'         => '10:00',
                'location'         => null,
                'max_participants' => 10,
                'status'           => 'scheduled',
                'notes'            => null,
            ],
            [
                'trainer_id'       => $this->trainer->id,
                'session_date'     => '2025-06-09',
                'start_time'       => '09:00',
                'end_time'         => '10:00',
                'location'         => null,
                'max_participants' => 10,
                'status'           => 'scheduled',
                'notes'            => null,
            ],
        ];

        $this->service->syncSessions($this->course, $sessionData);

        $this->assertDatabaseCount('training_sessions', 2);

        // Use Eloquent + Carbon to avoid driver-specific date format differences
        $created = TrainingSession::where('course_id', $this->course->id)
            ->orderBy('session_date')
            ->get();

        expect($created)->toHaveCount(2);
        expect($created[0]->session_date->format('Y-m-d'))->toBe('2025-06-02');
        expect($created[1]->session_date->format('Y-m-d'))->toBe('2025-06-09');
    });

    it('erhält eine gebuchte session und gibt ein warning zurück', function () {
        $session = TrainingSession::factory()->create([
            'course_id'    => $this->course->id,
            'session_date' => '2025-06-02',
        ]);

        Booking::factory()->create(['training_session_id' => $session->id]);

        $warnings = $this->service->syncSessions($this->course, []);

        // Session is preserved in the database
        $this->assertDatabaseHas('training_sessions', ['id' => $session->id]);

        // Warning is returned
        expect($warnings)->toHaveCount(1);
        expect($warnings[0]['type'])->toBe('protected_session');
        expect($warnings[0]['sessionDate'])->toBe('2025-06-02');
        expect($warnings[0]['bookingCount'])->toBe(1);
    });

    it('verhindert duplikat-insert wenn neuer termin mit gebuchter session kollidiert', function () {
        $session = TrainingSession::factory()->create([
            'course_id'    => $this->course->id,
            'session_date' => '2025-06-02',
        ]);

        Booking::factory()->create(['training_session_id' => $session->id]);

        // New sessions list also contains 2025-06-02 — should be skipped
        $sessionData = [
            [
                'trainer_id'       => $this->trainer->id,
                'session_date'     => '2025-06-02',
                'start_time'       => '09:00',
                'end_time'         => '10:00',
                'location'         => null,
                'max_participants' => 10,
                'status'           => 'scheduled',
                'notes'            => null,
            ],
            [
                'trainer_id'       => $this->trainer->id,
                'session_date'     => '2025-06-09',
                'start_time'       => '09:00',
                'end_time'         => '10:00',
                'location'         => null,
                'max_participants' => 10,
                'status'           => 'scheduled',
                'notes'            => null,
            ],
        ];

        $this->service->syncSessions($this->course, $sessionData);

        // Exactly 2 rows: the protected original + the new non-colliding one
        $this->assertDatabaseCount('training_sessions', 2);

        // Only one row for 2025-06-02 (no duplicate)
        // whereDate() handles driver-specific date format differences (SQLite, MySQL, PgSQL)
        $count = TrainingSession::where('course_id', $this->course->id)
            ->whereDate('session_date', '2025-06-02')
            ->count();

        expect($count)->toBe(1);
    });
});

// ---------------------------------------------------------------------------
// getBookingCount
// ---------------------------------------------------------------------------

describe('getBookingCount', function () {
    it('gibt 0 zurück wenn keine buchungen vorhanden sind', function () {
        $session = TrainingSession::factory()->create(['course_id' => $this->course->id]);

        $count = $this->service->getBookingCount($session);

        expect($count)->toBe(0);
    });

    it('gibt die korrekte anzahl buchungen zurück wenn buchungen vorhanden sind', function () {
        $session = TrainingSession::factory()->create(['course_id' => $this->course->id]);

        Booking::factory()->count(3)->create(['training_session_id' => $session->id]);

        $count = $this->service->getBookingCount($session);

        expect($count)->toBe(3);
    });

    it('zählt buchungen anderer sessions nicht mit', function () {
        $session        = TrainingSession::factory()->create(['course_id' => $this->course->id]);
        $otherSession   = TrainingSession::factory()->create(['course_id' => $this->course->id]);

        Booking::factory()->count(2)->create(['training_session_id' => $session->id]);
        Booking::factory()->count(5)->create(['training_session_id' => $otherSession->id]);

        $count = $this->service->getBookingCount($session);

        expect($count)->toBe(2);
    });
});
