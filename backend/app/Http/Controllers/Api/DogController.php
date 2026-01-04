<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDogRequest;
use App\Http\Requests\UpdateDogRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\DogResource;
use App\Http\Resources\TrainingLogResource;
use App\Http\Resources\VaccinationResource;
use App\Models\Dog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Dog Controller
 *
 * Handles CRUD operations and related resources for dogs.
 */
class DogController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of dogs with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Dog::class);

        $query = Dog::query()->with(['customer.user']);

        $user = $request->user();

        // Role-based filtering
        if ($user->isTrainer()) {
            // Trainer sees only dogs from assigned customers
            $query->whereHas('customer', function ($q) use ($user) {
                $q->where('trainer_id', $user->id);
            });
        } elseif ($user->isCustomer()) {
            // Customer sees only their own dogs
            $customer = \App\Models\Customer::where('user_id', $user->id)->first();
            if ($customer) {
                $query->where('customer_id', $customer->id);
            } else {
                // No customer record means no dogs
                $query->whereRaw('1 = 0');
            }
        }
        // Admin sees everything (no filter)

        // Filter by customer
        if ($request->has('customerId')) {
            $query->where('customer_id', $request->input('customerId'));
        }

        // Filter by search term (name or breed)
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('breed', 'ilike', "%{$search}%")
                  ->orWhere('chip_number', 'ilike', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('isActive')) {
            $query->where('is_active', $request->boolean('isActive'));
        }

        // Filter by breed
        if ($request->has('breed')) {
            $query->where('breed', 'ilike', "%{$request->input('breed')}%");
        }

        return DogResource::collection(
            $query->orderBy('name')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created dog.
     *
     * @param StoreDogRequest $request
     * @return DogResource
     */
    public function store(StoreDogRequest $request): DogResource
    {
        $dog = Dog::create($request->validatedSnakeCase());

        return new DogResource($dog->load('customer.user'));
    }

    /**
     * Display the specified dog.
     *
     * @param Dog $dog
     * @return DogResource
     */
    public function show(Dog $dog): DogResource
    {
        $this->authorize('view', $dog);

        return new DogResource($dog->load(['customer.user', 'vaccinations', 'trainingLogs']));
    }

    /**
     * Update the specified dog.
     *
     * @param UpdateDogRequest $request
     * @param Dog $dog
     * @return DogResource
     */
    public function update(UpdateDogRequest $request, Dog $dog): DogResource
    {
        $this->authorize('update', $dog);

        $dog->update($request->validatedSnakeCase());

        return new DogResource($dog->fresh(['customer.user']));
    }

    /**
     * Remove the specified dog.
     *
     * @param Dog $dog
     * @return JsonResponse
     */
    public function destroy(Dog $dog): JsonResponse
    {
        $this->authorize('delete', $dog);

        // Check for active bookings
        if ($dog->bookings()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete dog with active bookings.',
            ], 422);
        }

        $dog->delete();

        return response()->json(null, 204);
    }

    /**
     * Get vaccinations for the specified dog.
     *
     * @param Dog $dog
     * @return AnonymousResourceCollection
     */
    public function vaccinations(Dog $dog): AnonymousResourceCollection
    {
        $this->authorize('view', $dog);

        return VaccinationResource::collection(
            $dog->vaccinations()
                ->orderBy('vaccination_date', 'desc')
                ->get()
        );
    }

    /**
     * Get training logs for the specified dog.
     *
     * @param Dog $dog
     * @return AnonymousResourceCollection
     */
    public function trainingLogs(Dog $dog): AnonymousResourceCollection
    {
        $this->authorize('view', $dog);

        return TrainingLogResource::collection(
            $dog->trainingLogs()
                ->with(['session', 'attachments'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * Get bookings for the specified dog.
     *
     * @param Dog $dog
     * @return AnonymousResourceCollection
     */
    public function bookings(Dog $dog): AnonymousResourceCollection
    {
        $this->authorize('view', $dog);

        return BookingResource::collection(
            $dog->bookings()
                ->with(['trainingSession', 'customer'])
                ->orderBy('booking_date', 'desc')
                ->get()
        );
    }
}
