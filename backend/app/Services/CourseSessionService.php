<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\TrainingSession;

/**
 * CourseSessionService
 *
 * Handles recurrence-based session generation and session synchronisation
 * for courses. The generation logic is pure (no DB writes); persistence
 * happens only in {@see syncSessions()}.
 */
class CourseSessionService
{
    /**
     * Generates normalised session data from a recurrence rule.
     *
     * No database writes are performed — the method returns an array of
     * session attribute arrays ready to be passed to {@see syncSessions()}.
     *
     * Supported rule types:
     * - `weekly`:  Generates `count` sessions on the given `weekday`
     *              (0 = Sunday … 6 = Saturday), starting from the first
     *              occurrence on or after `startDate`.
     * - `monthly`: Generates `count` sessions on `dayOfMonth` of each
     *              consecutive calendar month, starting from the month
     *              containing `startDate`. Months where `dayOfMonth` does
     *              not exist (e.g. 31 in February) are skipped, but the
     *              counter continues until exactly `count` valid dates are
     *              collected.
     *
     * @param array{
     *     type: string,
     *     weekday?: int,
     *     dayOfMonth?: int,
     *     startTime: string,
     *     endTime: string,
     *     startDate: string,
     *     count: int,
     *     location?: string|null,
     *     maxParticipants?: int|null,
     * } $rule
     * @param int $trainerId
     * @param int $fallbackMaxParticipants  Used when $rule['maxParticipants'] is absent/null.
     * @return array<int, array<string, mixed>>
     */
    public function generateFromRecurrence(
        array $rule,
        int $trainerId,
        int $fallbackMaxParticipants,
    ): array {
        $type = $rule['type'];
        $count = (int) $rule['count'];
        $startTime = substr($rule['startTime'], 0, 5);
        $endTime = substr($rule['endTime'], 0, 5);
        $location = $rule['location'] ?? null;
        $maxParticipants = isset($rule['maxParticipants']) && $rule['maxParticipants'] !== null
            ? (int) $rule['maxParticipants']
            : $fallbackMaxParticipants;

        $dates = match ($type) {
            'weekly'  => $this->generateWeeklyDates($rule['startDate'], (int) $rule['weekday'], $count),
            'monthly' => $this->generateMonthlyDates($rule['startDate'], (int) $rule['dayOfMonth'], $count),
            default   => [],
        };

        $sessions = [];
        foreach ($dates as $date) {
            $sessions[] = [
                'trainer_id'       => $trainerId,
                'session_date'     => $date,
                'start_time'       => $startTime,
                'end_time'         => $endTime,
                'location'         => $location,
                'max_participants' => $maxParticipants,
                'status'           => 'scheduled',
                'notes'            => null,
            ];
        }

        return $sessions;
    }

    /**
     * Synchronises training sessions for a course.
     *
     * Existing sessions without any bookings are deleted; sessions that have
     * at least one booking are preserved and reported as warnings. The
     * provided $sessions array is then inserted as new records.
     *
     * New sessions whose `session_date` collides with a protected (booked)
     * session are silently skipped to prevent duplicate rows.
     *
     * @note The caller (CourseController) is responsible for wrapping this
     *       method in a DB::transaction() to ensure atomicity.
     *
     * @param Course                             $course
     * @param array<int, array<string, mixed>>   $sessions  Normalised session data (from generateFromRecurrence or manual input).
     * @return array<int, array<string, mixed>>  Warning entries for each preserved session (empty when no conflicts).
     */
    public function syncSessions(Course $course, array $sessions): array
    {
        $warnings = [];
        $protectedDates = [];

        /** @var \Illuminate\Database\Eloquent\Collection<int, TrainingSession> $existing */
        $existing = $course->sessions()->get();

        foreach ($existing as $existingSession) {
            $bookingCount = $existingSession->bookings()->count();
            $dateString = $existingSession->session_date instanceof \DateTimeInterface
                ? $existingSession->session_date->format('Y-m-d')
                : (string) $existingSession->session_date;

            if ($bookingCount > 0) {
                $protectedDates[] = $dateString;
                $warnings[] = [
                    'type'         => 'protected_session',
                    'sessionDate'  => $dateString,
                    'message'      => 'Session hat aktive Buchungen und wurde nicht gelöscht.',
                    'bookingCount' => $bookingCount,
                ];
            } else {
                $existingSession->delete();
            }
        }

        foreach ($sessions as $sessionData) {
            // Skip new sessions that would duplicate a protected (booked) session.
            if (in_array($sessionData['session_date'], $protectedDates, true)) {
                continue;
            }
            TrainingSession::create(array_merge($sessionData, ['course_id' => $course->id]));
        }

        return $warnings;
    }

    /**
     * Returns the number of bookings for a given session.
     *
     * @param TrainingSession $session
     * @return int
     */
    public function getBookingCount(TrainingSession $session): int
    {
        return $session->bookings()->count();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Generates exactly $count date strings (Y-m-d) for the given weekday,
     * starting from the first occurrence on or after $startDate.
     *
     * @param string $startDate  ISO date string (Y-m-d)
     * @param int    $weekday    0 = Sunday … 6 = Saturday (PHP date('w') convention)
     * @param int    $count
     * @return list<string>
     */
    private function generateWeeklyDates(string $startDate, int $weekday, int $count): array
    {
        $current = new \DateTimeImmutable($startDate);
        $currentWeekday = (int) $current->format('w'); // 0=Sun … 6=Sat

        // Advance to the first matching weekday on or after startDate.
        $daysUntilTarget = ($weekday - $currentWeekday + 7) % 7;
        if ($daysUntilTarget > 0) {
            $current = $current->add(new \DateInterval("P{$daysUntilTarget}D"));
        }

        $interval = new \DateInterval('P7D');
        $dates = [];

        for ($i = 0; $i < $count; $i++) {
            $dates[] = $current->format('Y-m-d');
            $current = $current->add($interval);
        }

        return $dates;
    }

    /**
     * Generates exactly $count date strings (Y-m-d) for the given dayOfMonth,
     * starting from the calendar month of $startDate.
     *
     * Months where dayOfMonth does not exist (e.g. 31 in February) are
     * skipped; the iteration continues until $count valid dates are found.
     *
     * @param string $startDate   ISO date string (Y-m-d)
     * @param int    $dayOfMonth  1–28 (spec constraint)
     * @param int    $count
     * @return list<string>
     */
    private function generateMonthlyDates(string $startDate, int $dayOfMonth, int $count): array
    {
        $start = new \DateTimeImmutable($startDate);
        $year = (int) $start->format('Y');
        $month = (int) $start->format('n');
        $startDay = (int) $start->format('j');

        // If the target dayOfMonth in the start month has already passed,
        // advance to the next month so we never return a date < startDate.
        if ($dayOfMonth < $startDay) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        $dates = [];
        $safetyLimit = $count + 12; // prevent infinite loops (at most 12 extra months needed)
        $iterations = 0;

        while (count($dates) < $count && $iterations < $safetyLimit) {
            if (checkdate($month, $dayOfMonth, $year)) {
                $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $dayOfMonth);
            }

            // Advance to next calendar month.
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }

            $iterations++;
        }

        return $dates;
    }
}
