<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\DatabaseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\StoreCourseSessionRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Requests\UpdateCourseSessionRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\CourseRunResource;
use App\Http\Resources\TrainingSessionResource;
use App\Models\Course;
use App\Models\TrainingSession;
use App\Services\CourseSessionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Course Controller
 *
 * Handles course management operations.
 */
class CourseController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CourseSessionService $sessionService)
    {
    }

    /**
     * Display a listing of courses with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Course::class);

        $query = Course::query()->with(['trainer', 'sessions']);

        $user = $request->user();

        // Role-based filtering
        if ($user->isTrainer()) {
            // Trainer sees only their own courses
            $query->where('trainer_id', $user->id);
        } elseif ($user->isCustomer()) {
            // Customer sees all available courses (for browsing/booking)
            // Or optionally: only courses they have bookings for
            // For now, show all active courses
            $query->where('status', 'active');
        }
        // Admin sees everything (no filter)

        // Filter by trainer
        if ($request->has('trainerId')) {
            $query->where('trainer_id', $request->input('trainerId'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by course type
        if ($request->has('courseType')) {
            $query->where('course_type', $request->input('courseType'));
        }

        // Filter active courses (active and not completed/cancelled)
        if ($request->boolean('activeOnly')) {
            $query->where('status', 'active')
                ->where('end_date', '>=', now());
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', DatabaseHelper::caseInsensitiveLike(), "%{$search}%")
                  ->orWhere('description', DatabaseHelper::caseInsensitiveLike(), "%{$search}%");
            });
        }

        return CourseResource::collection(
            $query->orderBy('start_date', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created course.
     *
     * @param StoreCourseRequest $request
     * @return CourseResource
     */
    public function store(StoreCourseRequest $request): CourseResource
    {
        $this->authorize('create', Course::class);

        $warnings = [];

        $course = DB::transaction(function () use ($request, &$warnings): Course {
            $course = Course::create($request->validatedSnakeCase());

            $sessionsPayload = $request->getSessionsPayload();
            $recurrenceRule  = $request->getRecurrenceRule();

            if ($sessionsPayload !== null) {
                $normalized = $this->normalizeSessionKeys($sessionsPayload);
                $normalized = array_map(
                    fn (array $s) => array_merge(['trainer_id' => $course->trainer_id], $s),
                    $normalized
                );
                $warnings = $this->sessionService->syncSessions($course, $normalized);
            } elseif ($recurrenceRule !== null) {
                $sessions = $this->sessionService->generateFromRecurrence(
                    $this->camelizeRuleKeys($recurrenceRule),
                    $course->trainer_id,
                    $course->max_participants
                );
                $warnings = $this->sessionService->syncSessions($course, $sessions);
            }

            return $course;
        });

        $resource = new CourseResource($course->load(['trainer', 'sessions']));

        if (!empty($warnings)) {
            $resource->additional(['meta' => ['warnings' => $warnings]]);
        }

        return $resource;
    }

    /**
     * Display the specified course.
     *
     * @param Course $course
     * @return CourseResource
     */
    public function show(Course $course): CourseResource
    {
        $this->authorize('view', $course);

        return new CourseResource($course->load(['trainer', 'sessions']));
    }

    /**
     * Update the specified course.
     *
     * @param UpdateCourseRequest $request
     * @param Course $course
     * @return CourseResource
     */
    public function update(UpdateCourseRequest $request, Course $course): CourseResource
    {
        $this->authorize('update', $course);

        $warnings = [];

        DB::transaction(function () use ($request, $course, &$warnings): void {
            $course->update($request->validatedSnakeCase());

            $sessionsPayload = $request->getSessionsPayload();
            $recurrenceRule  = $request->getRecurrenceRule();

            if ($sessionsPayload !== null) {
                $normalized = $this->normalizeSessionKeys($sessionsPayload);
                $normalized = array_map(
                    fn (array $s) => array_merge(['trainer_id' => $course->trainer_id], $s),
                    $normalized
                );
                $warnings = $this->sessionService->syncSessions($course, $normalized);
            } elseif ($recurrenceRule !== null) {
                $sessions = $this->sessionService->generateFromRecurrence(
                    $this->camelizeRuleKeys($recurrenceRule),
                    $course->trainer_id,
                    $course->max_participants
                );
                $warnings = $this->sessionService->syncSessions($course, $sessions);
            }
        });

        $resource = new CourseResource($course->fresh(['trainer', 'sessions']));

        if (!empty($warnings)) {
            $resource->additional(['meta' => ['warnings' => $warnings]]);
        }

        return $resource;
    }

    /**
     * Remove the specified course.
     *
     * @param Course $course
     * @return JsonResponse
     */
    public function destroy(Course $course): JsonResponse
    {
        $this->authorize('delete', $course);

        // Prevent deletion if course has sessions
        if ($course->sessions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete course with existing training sessions.',
            ], 422);
        }

        $course->delete();

        return response()->json(null, 204);
    }

    /**
     * Get all sessions for the specified course.
     *
     * @param Course $course
     * @return AnonymousResourceCollection
     */
    public function sessions(Course $course): AnonymousResourceCollection
    {
        $this->authorize('view', $course);

        return TrainingSessionResource::collection(
            $course->sessions()
                ->with(['trainer', 'bookings'])
                ->orderBy('session_date')
                ->orderBy('start_time')
                ->get()
        );
    }

    /**
     * Store a new training session for the specified course.
     *
     * @param StoreCourseSessionRequest $request
     * @param Course $course
     * @return JsonResponse
     */
    public function storeSession(StoreCourseSessionRequest $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validated();

        $session = TrainingSession::create([
            'course_id'        => $course->id,
            'trainer_id'       => $course->trainer_id,
            'session_date'     => $validated['sessionDate'],
            'start_time'       => $validated['startTime'],
            'end_time'         => $validated['endTime'],
            'location'         => $validated['location'] ?? null,
            'max_participants' => $validated['maxParticipants'] ?? $course->max_participants,
            'status'           => $validated['status'] ?? 'scheduled',
            'notes'            => $validated['notes'] ?? null,
        ]);

        return (new TrainingSessionResource($session->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing training session.
     *
     * If the session has active bookings the update still proceeds, but a
     * warnings entry is included in the response to inform the caller.
     *
     * @param UpdateCourseSessionRequest $request
     * @param Course $course
     * @param TrainingSession $session
     * @return JsonResponse
     */
    public function updateSession(
        UpdateCourseSessionRequest $request,
        Course $course,
        TrainingSession $session
    ): JsonResponse {
        $this->authorize('update', $course);

        if ($session->course_id !== $course->id) {
            abort(404);
        }

        $validated = $request->validated();

        $attributeMap = [
            'sessionDate'     => 'session_date',
            'startTime'       => 'start_time',
            'endTime'         => 'end_time',
            'location'        => 'location',
            'maxParticipants' => 'max_participants',
            'status'          => 'status',
            'notes'           => 'notes',
        ];

        $updateData = [];
        foreach ($attributeMap as $camel => $snake) {
            if (array_key_exists($camel, $validated)) {
                $updateData[$snake] = $validated[$camel];
            }
        }

        $session->update($updateData);

        $bookingCount = $this->sessionService->getBookingCount($session);
        $resource = new TrainingSessionResource($session->fresh());

        if ($bookingCount > 0) {
            $resource->additional([
                'meta' => [
                    'warnings' => [
                        [
                            'type'         => 'booking_conflict',
                            'message'      => "Diese Einheit hat {$bookingCount} aktive Buchungen.",
                            'bookingCount' => $bookingCount,
                        ],
                    ],
                ],
            ]);
        }

        return $resource->response();
    }

    /**
     * Delete a training session.
     *
     * If the session has bookings the session (and its bookings via cascade)
     * is still deleted, but the response is HTTP 200 with a warnings payload
     * instead of HTTP 204.
     *
     * @param Course $course
     * @param TrainingSession $session
     * @return JsonResponse
     */
    public function destroySession(Course $course, TrainingSession $session): JsonResponse
    {
        $this->authorize('update', $course);

        if ($session->course_id !== $course->id) {
            abort(404);
        }

        $bookingCount = $this->sessionService->getBookingCount($session);

        $session->delete();

        if ($bookingCount > 0) {
            return response()->json([
                'deleted'  => true,
                'warnings' => [
                    [
                        'type'         => 'booking_conflict',
                        'message'      => "Diese Einheit hatte {$bookingCount} aktive Buchungen. Die Buchungen wurden storniert.",
                        'bookingCount' => $bookingCount,
                    ],
                ],
            ]);
        }

        return response()->json(null, 204);
    }

    /**
     * Display a course without requiring authentication.
     *
     * Sessions are eager-loaded and ordered ascending by session_date.
     *
     * @param Course $course
     * @return JsonResponse
     */
    public function publicShow(Course $course): JsonResponse
    {
        $course->load([
            'trainer',
            'sessions' => fn ($query) => $query->orderBy('session_date'),
            'runs'     => fn ($query) => $query->where('status', 'active')->orderBy('start_date'),
            'runs.sessions' => fn ($query) => $query->orderBy('session_date'),
        ]);

        return response()->json([
            'data' => [
                'id'              => $course->id,
                'name'            => $course->name,
                'description'     => $course->description,
                'courseType'      => $course->course_type,
                'level'           => $course->level,
                'maxParticipants' => $course->max_participants,
                'startDate'       => $course->start_date?->toDateString(),
                'endDate'         => $course->end_date?->toDateString(),
                'status'          => $course->status,
                'trainer'         => $course->trainer ? [
                    'id'        => $course->trainer->id,
                    'firstName' => $course->trainer->first_name,
                    'lastName'  => $course->trainer->last_name,
                ] : null,
                'sessions' => $course->sessions->map(fn (TrainingSession $s) => [
                    'id'             => $s->id,
                    'sessionDate'    => $s->session_date instanceof \DateTimeInterface
                        ? $s->session_date->toDateString()
                        : $s->session_date,
                    'startTime'      => $s->start_time,
                    'endTime'        => $s->end_time,
                    'location'       => $s->location,
                    'maxParticipants'=> $s->max_participants,
                    'status'         => $s->status,
                ]),
                'runs' => CourseRunResource::collection($course->runs),
            ],
        ]);
    }

    /**
     * Get participant statistics for the specified course.
     *
     * @param Course $course
     * @return JsonResponse
     */
    public function participants(Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        // Get all confirmed bookings for this course's sessions
        $confirmedBookings = \App\Models\Booking::whereIn(
            'training_session_id',
            $course->sessions()->pluck('id')
        )->where('status', 'confirmed')->count();

        // Get total capacity across all sessions
        $totalCapacity = (int) $course->sessions()->sum('max_participants');

        // Get unique participants (customers)
        $uniqueParticipants = \App\Models\Booking::whereIn(
            'training_session_id',
            $course->sessions()->pluck('id')
        )
            ->whereIn('status', ['pending', 'confirmed'])
            ->distinct('customer_id')
            ->count('customer_id');

        return response()->json([
            'courseId' => $course->id,
            'maxParticipants' => $course->max_participants,
            'uniqueParticipants' => $uniqueParticipants,
            'totalBookings' => $confirmedBookings,
            'totalCapacity' => $totalCapacity,
            'sessionsCount' => $course->sessions()->count(),
        ]);
    }

    /**
     * Normalises camelCase keys from getSessionsPayload() to snake_case
     * for CourseSessionService::syncSessions().
     *
     * @param array<int, array<string, mixed>> $sessions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSessionKeys(array $sessions): array
    {
        return array_map(function (array $session): array {
            $normalized = [];
            foreach ($session as $key => $value) {
                $normalized[Str::snake($key)] = $value;
            }
            return $normalized;
        }, $sessions);
    }

    /**
     * Converts snake_case keys from getRecurrenceRule() back to camelCase
     * for CourseSessionService::generateFromRecurrence(), which expects camelCase.
     *
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function camelizeRuleKeys(array $rule): array
    {
        $camelized = [];
        foreach ($rule as $key => $value) {
            $camelized[Str::camel($key)] = $value;
        }
        return $camelized;
    }
}
