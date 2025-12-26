<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\TrainingSessionResource;
use App\Models\TrainingSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Training Session Controller
 *
 * Handles operations for training sessions including availability checks.
 */
class TrainingSessionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of training sessions with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TrainingSession::class);

        $query = TrainingSession::query()->with(['course', 'trainer']);

        // Filter by course
        if ($request->has('courseId')) {
            $query->where('course_id', $request->input('courseId'));
        }

        // Filter by trainer
        if ($request->has('trainerId')) {
            $query->where('trainer_id', $request->input('trainerId'));
        }

        // Filter by date range
        if ($request->has('startDate')) {
            $query->where('session_date', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('session_date', '<=', $request->input('endDate'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Only show available sessions (with free spots)
        if ($request->boolean('availableOnly')) {
            $query->where('status', 'scheduled')
                ->where('session_date', '>=', now())
                ->whereRaw('(SELECT COUNT(*) FROM bookings WHERE bookings.training_session_id = training_sessions.id AND bookings.status IN (?, ?)) < training_sessions.max_participants', ['pending', 'confirmed']);
        }

        return TrainingSessionResource::collection(
            $query->orderBy('session_date')
                ->orderBy('start_time')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Display the specified training session.
     *
     * @param TrainingSession $trainingSession
     * @return TrainingSessionResource
     */
    public function show(TrainingSession $trainingSession): TrainingSessionResource
    {
        $this->authorize('view', $trainingSession);

        return new TrainingSessionResource($trainingSession->load(['course', 'trainer', 'bookings']));
    }

    /**
     * Get bookings for a specific training session.
     *
     * @param TrainingSession $trainingSession
     * @return AnonymousResourceCollection
     */
    public function bookings(TrainingSession $trainingSession): AnonymousResourceCollection
    {
        $this->authorize('view', $trainingSession);

        return BookingResource::collection(
            $trainingSession->bookings()
                ->with(['customer.user', 'dog'])
                ->orderBy('booking_date')
                ->get()
        );
    }

    /**
     * Get availability information for a training session.
     *
     * @param TrainingSession $trainingSession
     * @return \Illuminate\Http\JsonResponse
     */
    public function availability(TrainingSession $trainingSession): \Illuminate\Http\JsonResponse
    {
        $confirmedBookings = $trainingSession->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $availableSpots = max(0, $trainingSession->max_participants - $confirmedBookings);

        return response()->json([
            'sessionId' => $trainingSession->id,
            'maxParticipants' => $trainingSession->max_participants,
            'currentBookings' => $confirmedBookings,
            'availableSpots' => $availableSpots,
            'isFull' => $availableSpots === 0,
            'isAvailable' => $availableSpots > 0 && $trainingSession->status === 'scheduled',
        ]);
    }
}
