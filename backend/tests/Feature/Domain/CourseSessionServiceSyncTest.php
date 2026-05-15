<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\Course;
use App\Models\TrainingSession;
use App\Services\CourseSessionService;

uses()->group('domain', 'course');

// ---------------------------------------------------------------------------
// syncSessions
// ---------------------------------------------------------------------------

describe('syncSessions', function () {
    beforeEach(function () {
        $this->service = new CourseSessionService();
        $this->course  = Course::factory()->create();
    });

    it('legt eine neue session in der datenbank an wenn keine buchungen vorhanden sind', function () {
        $sessionData = [
            'trainer_id'       => $this->course->trainer_id,
            'session_date'     => '2026-06-08',
            'start_time'       => '10:00',
            'end_time'         => '11:00',
            'location'         => null,
            'max_participants' => 8,
            'status'           => 'scheduled',
            'notes'            => null,
        ];

        $warnings = $this->service->syncSessions($this->course, [$sessionData]);

        expect($warnings)->toBeEmpty();
        $this->assertDatabaseCount('training_sessions', 1);
        $created = TrainingSession::where('course_id', $this->course->id)->first();
        expect($created)->not->toBeNull();
        expect($created->session_date->toDateString())->toBe('2026-06-08');
    });

    it('löscht eine session ohne buchungen und gibt keine warning zurück', function () {
        $session = TrainingSession::factory()->create([
            'course_id'  => $this->course->id,
            'trainer_id' => $this->course->trainer_id,
        ]);

        $warnings = $this->service->syncSessions($this->course, []);

        expect($warnings)->toBeEmpty();
        $this->assertDatabaseMissing('training_sessions', ['id' => $session->id]);
    });

    it('bewahrt eine session mit buchungen und gibt eine warning zurück', function () {
        $session = TrainingSession::factory()->create([
            'course_id'    => $this->course->id,
            'trainer_id'   => $this->course->trainer_id,
            'session_date' => '2026-06-08',
        ]);
        Booking::factory()->create(['training_session_id' => $session->id]);

        $warnings = $this->service->syncSessions($this->course, []);

        expect($warnings)->not->toBeEmpty();
        expect($warnings[0]['type'])->toBe('protected_session');
        expect($warnings[0]['bookingCount'])->toBeGreaterThanOrEqual(1);
        $this->assertDatabaseHas('training_sessions', ['id' => $session->id]);
    });

    it('überspringt eine neue session deren datum mit einer gebuchten session kollidiert', function () {
        $session = TrainingSession::factory()->create([
            'course_id'    => $this->course->id,
            'trainer_id'   => $this->course->trainer_id,
            'session_date' => '2026-06-08',
        ]);
        Booking::factory()->create(['training_session_id' => $session->id]);

        $newSessionData = [
            'trainer_id'       => $this->course->trainer_id,
            'session_date'     => '2026-06-08', // same date as protected session
            'start_time'       => '14:00',
            'end_time'         => '15:00',
            'location'         => null,
            'max_participants' => 8,
            'status'           => 'scheduled',
            'notes'            => null,
        ];

        $warnings = $this->service->syncSessions($this->course, [$newSessionData]);

        expect($warnings)->not->toBeEmpty();
        // Only the original session should exist (not a duplicate)
        $this->assertDatabaseCount('training_sessions', 1);
    });
});
