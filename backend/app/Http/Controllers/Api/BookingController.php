<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Models\TrainingSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;

/**
 * Booking Controller
 *
 * Handles booking operations for training sessions.
 */
class BookingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of bookings with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Booking::class);

        $query = Booking::query()->with(['trainingSession.course', 'customer.user', 'dog']);

        $user = $request->user();

        // Role-based filtering
        if ($user->isTrainer()) {
            // Trainer sees only bookings for courses they train
            $trainerCourses = \App\Models\Course::where('trainer_id', $user->id)->pluck('id');
            $query->whereHas('trainingSession', function ($q) use ($trainerCourses) {
                $q->whereIn('course_id', $trainerCourses);
            });
        } elseif ($user->isCustomer()) {
            // Customer sees only their own bookings
            $customer = \App\Models\Customer::where('user_id', $user->id)->first();
            if ($customer) {
                $query->where('customer_id', $customer->id);
            } else {
                // No customer record means no bookings
                $query->whereRaw('1 = 0');
            }
        }
        // Admin sees everything (no filter)

        // Filter by customer
        if ($request->has('customerId')) {
            $query->where('customer_id', $request->input('customerId'));
        }

        // Filter by dog
        if ($request->has('dogId')) {
            $query->where('dog_id', $request->input('dogId'));
        }

        // Filter by training session
        if ($request->has('trainingSessionId')) {
            $query->where('training_session_id', $request->input('trainingSessionId'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by attended status
        if ($request->has('attended')) {
            $query->where('attended', $request->boolean('attended'));
        }

        // Search by customer name, dog name, or course name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer.user', function ($q) use ($search) {
                    $q->where('first_name', 'ILIKE', "%{$search}%")
                      ->orWhere('last_name', 'ILIKE', "%{$search}%");
                })
                ->orWhereHas('dog', function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%");
                })
                ->orWhereHas('trainingSession.course', function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%");
                });
            });
        }

        return BookingResource::collection(
            $query->orderBy('booking_date', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created booking.
     *
     * @param StoreBookingRequest $request
     * @return BookingResource|JsonResponse
     */
    public function store(StoreBookingRequest $request): BookingResource|JsonResponse
    {
        $this->authorize('create', Booking::class);

        $data = $request->validatedSnakeCase();
        
        // Check session capacity
        $session = TrainingSession::findOrFail($data['training_session_id']);
        $currentBookings = $session->bookings()->whereIn('status', ['pending', 'confirmed'])->count();
        
        if ($currentBookings >= $session->max_participants) {
            return response()->json([
                'message' => 'Training session is full. Please join the waiting list.',
                'availableSpots' => 0,
            ], 422);
        }

        // Verify dog belongs to customer
        $dog = \App\Models\Dog::findOrFail($data['dog_id']);
        if ($dog->customer_id !== $data['customer_id']) {
            return response()->json([
                'message' => 'The selected dog does not belong to this customer.',
            ], 422);
        }

        // Check for duplicate booking
        $existingBooking = Booking::where('training_session_id', $data['training_session_id'])
            ->where('dog_id', $data['dog_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($existingBooking) {
            return response()->json([
                'message' => 'This dog is already booked for this session.',
            ], 422);
        }

        $booking = Booking::create($data);
        $booking->load(['trainingSession.course', 'customer.user', 'dog']);

        // Send confirmation email
        Mail::to($booking->customer->user->email)
            ->queue(new BookingConfirmation($booking));

        return new BookingResource($booking);
    }

    /**
     * Display the specified booking.
     *
     * @param Booking $booking
     * @return BookingResource
     */
    public function show(Booking $booking): BookingResource
    {
        $this->authorize('view', $booking);

        return new BookingResource($booking->load(['trainingSession', 'customer.user', 'dog']));
    }

    /**
     * Update the specified booking.
     *
     * @param UpdateBookingRequest $request
     * @param Booking $booking
     * @return BookingResource
     */
    public function update(UpdateBookingRequest $request, Booking $booking): BookingResource
    {
        $this->authorize('update', $booking);

        $booking->update($request->validatedSnakeCase());

        return new BookingResource($booking->fresh(['trainingSession', 'customer.user', 'dog']));
    }

    /**
     * Cancel the specified booking.
     *
     * @param Request $request
     * @param Booking $booking
     * @return BookingResource
     */
    public function cancel(Request $request, Booking $booking): BookingResource
    {
        $this->authorize('cancel', $booking);

        $request->validate([
            'cancellationReason' => ['nullable', 'string', 'max:500'],
        ]);

        $booking->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('cancellationReason'),
        ]);

        return new BookingResource($booking->fresh(['trainingSession', 'customer.user', 'dog']));
    }

    /**
     * Confirm the specified booking.
     *
     * @param Booking $booking
     * @return BookingResource
     */
    public function confirm(Booking $booking): BookingResource
    {
        $this->authorize('update', $booking);

        $booking->update(['status' => 'confirmed']);
        $booking->load(['trainingSession.course', 'customer.user', 'dog']);

        // Send confirmation email
        Mail::to($booking->customer->user->email)
            ->queue(new BookingConfirmation($booking));

        return new BookingResource($booking->fresh(['trainingSession', 'customer.user', 'dog']));
    }

    /**
     * Remove the specified booking.
     *
     * @param Booking $booking
     * @return JsonResponse
     */
    public function destroy(Booking $booking): JsonResponse
    {
        $this->authorize('delete', $booking);

        $booking->delete();

        return response()->json(null, 204);
    }
}
