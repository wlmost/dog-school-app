<?php

declare(strict_types=1);

use App\Services\CourseSessionService;

uses()->group('unit', 'course');

// ---------------------------------------------------------------------------
// weekly
// ---------------------------------------------------------------------------

describe('generateFromRecurrence (weekly)', function () {
    beforeEach(function () {
        $this->service = new CourseSessionService();
    });

    it('erzeugt die korrekte anzahl sessions bei weekly-rekurrenz', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1, // Monday
            'startDate' => '2025-03-03',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 5,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions)->toHaveCount(5);
    });

    it('trifft den richtigen wochentag bei allen generierten sessions', function () {
        // startDate 2025-03-01 = Saturday → first Monday is 2025-03-03
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1, // Monday
            'startDate' => '2025-03-01',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 4,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        foreach ($sessions as $session) {
            $date = new \DateTimeImmutable($session['session_date']);
            expect((int) $date->format('w'))->toBe(1);
        }
    });

    it('nimmt startDate direkt wenn es bereits der richtige wochentag ist', function () {
        // 2025-03-03 = Monday → no forward-skip
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1,
            'startDate' => '2025-03-03',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 1,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions[0]['session_date'])->toBe('2025-03-03');
    });

    it('setzt kein datum vor startDate', function () {
        $startDate = '2025-03-01'; // Saturday
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1,
            'startDate' => $startDate,
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 3,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        foreach ($sessions as $session) {
            expect($session['session_date'] >= $startDate)->toBeTrue();
        }
    });

    it('hält einen abstand von exakt 7 tagen zwischen aufeinanderfolgenden sessions', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 3,
            'startDate' => '2025-03-01',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 4,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        for ($i = 1; $i < count($sessions); $i++) {
            $prev = new \DateTimeImmutable($sessions[$i - 1]['session_date']);
            $curr = new \DateTimeImmutable($sessions[$i]['session_date']);
            expect($curr->diff($prev)->days)->toBe(7);
        }
    });

    it('erzeugt genau eine session wenn count 1 ist', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1,
            'startDate' => '2025-03-03',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 1,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions)->toHaveCount(1);
    });
});

// ---------------------------------------------------------------------------
// monthly
// ---------------------------------------------------------------------------

describe('generateFromRecurrence (monthly)', function () {
    beforeEach(function () {
        $this->service = new CourseSessionService();
    });

    it('erzeugt die korrekte anzahl sessions bei monthly-rekurrenz', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'       => 'monthly',
            'dayOfMonth' => 15,
            'startDate'  => '2025-01-01',
            'startTime'  => '09:00',
            'endTime'    => '10:00',
            'count'      => 3,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions)->toHaveCount(3);
        expect($sessions[0]['session_date'])->toBe('2025-01-15');
        expect($sessions[1]['session_date'])->toBe('2025-02-15');
        expect($sessions[2]['session_date'])->toBe('2025-03-15');
    });

    it('überspringt monate in denen dayOfMonth nicht existiert', function () {
        // dayOfMonth=31: Feb and Apr have no 31st
        // Starting 2025-01-01, count=3 → Jan 31, Mar 31, May 31
        $sessions = $this->service->generateFromRecurrence([
            'type'       => 'monthly',
            'dayOfMonth' => 31,
            'startDate'  => '2025-01-01',
            'startTime'  => '09:00',
            'endTime'    => '10:00',
            'count'      => 3,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions)->toHaveCount(3);
        expect($sessions[0]['session_date'])->toBe('2025-01-31');
        expect($sessions[1]['session_date'])->toBe('2025-03-31');
        expect($sessions[2]['session_date'])->toBe('2025-05-31');
    });

    it('überspringt den startmonat wenn dayOfMonth vor dem starttag liegt', function () {
        // startDate=2025-03-20, dayOfMonth=10: March 10 < March 20 → skip March → April 10
        $sessions = $this->service->generateFromRecurrence([
            'type'       => 'monthly',
            'dayOfMonth' => 10,
            'startDate'  => '2025-03-20',
            'startTime'  => '09:00',
            'endTime'    => '10:00',
            'count'      => 1,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions[0]['session_date'])->toBe('2025-04-10');
    });

    it('nimmt den startmonat auf wenn dayOfMonth dem starttag entspricht', function () {
        // startDate=2025-03-10, dayOfMonth=10: March 10 == start date → include March
        $sessions = $this->service->generateFromRecurrence([
            'type'       => 'monthly',
            'dayOfMonth' => 10,
            'startDate'  => '2025-03-10',
            'startTime'  => '09:00',
            'endTime'    => '10:00',
            'count'      => 1,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions[0]['session_date'])->toBe('2025-03-10');
    });

    it('nimmt den startmonat auf wenn dayOfMonth nach dem starttag liegt', function () {
        // startDate=2025-03-05, dayOfMonth=10: March 10 > March 5 → include March
        $sessions = $this->service->generateFromRecurrence([
            'type'       => 'monthly',
            'dayOfMonth' => 10,
            'startDate'  => '2025-03-05',
            'startTime'  => '09:00',
            'endTime'    => '10:00',
            'count'      => 1,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions[0]['session_date'])->toBe('2025-03-10');
    });
});

// ---------------------------------------------------------------------------
// edge cases (maxParticipants, location, status)
// ---------------------------------------------------------------------------

describe('generateFromRecurrence (edge cases)', function () {
    beforeEach(function () {
        $this->service = new CourseSessionService();
    });

    it('verwendet fallbackMaxParticipants wenn maxParticipants in der regel fehlt', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1,
            'startDate' => '2025-03-03',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 1,
        ], trainerId: 1, fallbackMaxParticipants: 12);

        expect($sessions[0]['max_participants'])->toBe(12);
    });

    it('verwendet fallbackMaxParticipants wenn maxParticipants in der regel null ist', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'            => 'weekly',
            'weekday'         => 1,
            'startDate'       => '2025-03-03',
            'startTime'       => '09:00',
            'endTime'         => '10:00',
            'count'           => 1,
            'maxParticipants' => null,
        ], trainerId: 1, fallbackMaxParticipants: 12);

        expect($sessions[0]['max_participants'])->toBe(12);
    });

    it('überschreibt fallbackMaxParticipants mit maxParticipants aus der regel', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'            => 'weekly',
            'weekday'         => 1,
            'startDate'       => '2025-03-03',
            'startTime'       => '09:00',
            'endTime'         => '10:00',
            'count'           => 1,
            'maxParticipants' => 8,
        ], trainerId: 1, fallbackMaxParticipants: 12);

        expect($sessions[0]['max_participants'])->toBe(8);
    });

    it('setzt location auf null wenn keine location angegeben', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1,
            'startDate' => '2025-03-03',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 1,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        expect($sessions[0]['location'])->toBeNull();
    });

    it('setzt status scheduled für alle generierten sessions', function () {
        $sessions = $this->service->generateFromRecurrence([
            'type'      => 'weekly',
            'weekday'   => 1,
            'startDate' => '2025-03-03',
            'startTime' => '09:00',
            'endTime'   => '10:00',
            'count'     => 3,
        ], trainerId: 1, fallbackMaxParticipants: 10);

        foreach ($sessions as $session) {
            expect($session['status'])->toBe('scheduled');
        }
    });
});


