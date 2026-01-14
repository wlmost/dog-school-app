<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Http\Resources\TrainingSessionResource;
use App\Models\Course;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Course Controller
 *
 * Handles course management operations.
 */
class CourseController extends Controller
{
    use AuthorizesRequests;

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
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
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

        $course = Course::create($request->validatedSnakeCase());

        return new CourseResource($course->load('trainer'));
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

        $course->update($request->validatedSnakeCase());

        return new CourseResource($course->fresh(['trainer', 'sessions']));
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
        $totalCapacity = $course->sessions()->sum('max_participants');

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
}
