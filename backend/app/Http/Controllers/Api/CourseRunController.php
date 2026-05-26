<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookCourseRunRequest;
use App\Http\Requests\StoreCourseRunRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\CourseRunResource;
use App\Models\Booking;
use App\Models\Course;
use App\Models\CourseRun;
use App\Models\Dog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CourseRun Controller
 *
 * Manages CRUD and booking operations for CourseRun instances.
 * A CourseRun is a scheduled instance of a Course; customers book
 * an entire run (all its sessions) at once.
 */
class CourseRunController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all runs for a course.
     *
     * Public action — no authentication required.
     *
     * @param Course $course
     * @return AnonymousResourceCollection
     */
    public function index(Course $course): AnonymousResourceCollection
    {
        $runs = $course->runs()
            ->with(['sessions' => fn ($q) => $q->orderBy('session_date')])
            ->orderBy('start_date')
            ->get();

        return CourseRunResource::collection($runs);
    }

    /**
     * Create a new run for a course.
     *
     * Only the owning trainer or an admin may create runs.
     *
     * @param StoreCourseRunRequest $request
     * @param Course                $course
     * @return JsonResponse
     */
    public function store(StoreCourseRunRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validated();

        $run = $course->runs()->create([
            'start_date' => $validated['startDate'],
            'end_date'   => $validated['endDate'] ?? null,
            'status'     => $validated['status'] ?? 'active',
        ]);

        return (new CourseRunResource($run->load('sessions')))->response()->setStatusCode(201);
    }

    /**
     * Book all sessions in a CourseRun for a customer.
     *
     * Creates one Booking per TrainingSession in the run, all linked via
     * course_run_id. Sessions that are full or already booked by the same
     * dog are silently skipped; their dates are reported in the `skipped` array.
     *
     * HTTP 201: at least one booking was created (data array + optional skipped list).
     * HTTP 422: run has no bookable sessions, or all sessions were skipped.
     *
     * @param BookCourseRunRequest $request
     * @param CourseRun            $courseRun
     * @return JsonResponse
     */
    public function book(BookCourseRunRequest $request, CourseRun $courseRun): JsonResponse
    {
        $data = $request->validatedSnakeCase();

        $sessions = $courseRun->sessions()
            ->where('status', 'scheduled')
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json(
                ['message' => 'Keine buchbaren Termine in diesem Kursdurchlauf.'],
                422
            );
        }

        // Verify dog belongs to the given customer
        $dog = Dog::findOrFail($data['dog_id']);

        if ($dog->customer_id !== $data['customer_id']) {
            return response()->json(
                ['message' => 'Der ausgewählte Hund gehört nicht zu diesem Kunden.'],
                422
            );
        }

        $created = [];
        $skipped = [];

        foreach ($sessions as $session) {
            // Skip full sessions
            $currentBookings = $session->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();

            if ($currentBookings >= $session->max_participants) {
                $skipped[] = $session->session_date->toDateString() . ' (ausgebucht)';
                continue;
            }

            // Skip duplicate bookings for the same dog
            $alreadyBooked = Booking::where('training_session_id', $session->id)
                ->where('dog_id', $data['dog_id'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if ($alreadyBooked) {
                $skipped[] = $session->session_date->toDateString() . ' (bereits gebucht)';
                continue;
            }

            $booking = Booking::create([
                'training_session_id' => $session->id,
                'customer_id'         => $data['customer_id'],
                'dog_id'              => $data['dog_id'],
                'course_run_id'       => $courseRun->id,
                'notes'               => $data['notes'] ?? null,
                'booking_date'        => now(),
                'status'              => 'pending',
            ]);

            $booking->load(['trainingSession.course', 'customer.user', 'dog']);
            $created[] = new BookingResource($booking);
        }

        if (empty($created)) {
            return response()->json([
                'message' => 'Keine Termine konnten gebucht werden.',
                'skipped' => $skipped,
            ], 422);
        }

        return response()->json([
            'data'    => $created,
            'skipped' => $skipped,
        ], 201);
    }
}
