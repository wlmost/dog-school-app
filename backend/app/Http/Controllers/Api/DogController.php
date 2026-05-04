<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\DatabaseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDogRequest;
use App\Http\Requests\UpdateDogRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\DogResource;
use App\Http\Resources\TrainingLogResource;
use App\Http\Resources\VaccinationResource;
use App\Mail\DogDeletedMail;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogDeletionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
                $q->where('name', DatabaseHelper::caseInsensitiveLike(), "%{$search}%")
                  ->orWhere('breed', DatabaseHelper::caseInsensitiveLike(), "%{$search}%")
                  ->orWhere('chip_number', DatabaseHelper::caseInsensitiveLike(), "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('isActive')) {
            $query->where('is_active', $request->boolean('isActive'));
        }

        // Filter by breed
        if ($request->has('breed')) {
            $query->where('breed', DatabaseHelper::caseInsensitiveLike(), "%{$request->input('breed')}%");
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
     * Remove the specified dog (admin only). Sends email notification to customer.
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

        // Cache customer info before deletion
        $customer = $dog->customer()->with('user')->first();
        $dogName  = $dog->name;

        $dog->delete();

        // Notify customer via email
        if ($customer?->user?->email) {
            Mail::to($customer->user->email)
                ->send(new DogDeletedMail($customer->user->first_name, $dogName));
        }

        return response()->json(null, 204);
    }

    /**
     * Upload a profile image for the specified dog.
     *
     * @param Request $request
     * @param Dog $dog
     * @return DogResource
     */
    public function uploadImage(Request $request, Dog $dog): DogResource
    {
        $this->authorize('update', $dog);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (! in_array($extension, $allowedExtensions)) {
            abort(422, 'Invalid file extension');
        }

        // Delete old image if it exists
        if ($dog->profile_image && Storage::disk('public')->exists($dog->profile_image)) {
            Storage::disk('public')->delete($dog->profile_image);
        }

        $filename = 'dog_' . $dog->id . '_' . uniqid() . '.' . $extension;
        $path = $file->storeAs('dog-images', $filename, 'public');

        $dog->update(['profile_image' => $path]);

        return new DogResource($dog->fresh(['customer.user']));
    }

    /**
     * Customer requests deletion of their own dog.
     * Admin reviews the request on the dashboard.
     *
     * @param Dog $dog
     * @param Request $request
     * @return JsonResponse
     */
    public function requestDeletion(Dog $dog, Request $request): JsonResponse
    {
        $this->authorize('view', $dog);

        $user = $request->user();

        if (! $user->isCustomer()) {
            return response()->json(['message' => 'Nur Kunden können Löschanfragen stellen.'], 403);
        }

        $customer = Customer::where('user_id', $user->id)->first();
        if (! $customer) {
            return response()->json(['message' => 'Kein Kundenkonto gefunden.'], 422);
        }

        // Prevent duplicate pending requests
        if (DogDeletionRequest::where('dog_id', $dog->id)->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'Eine Löschanfrage für diesen Hund ist bereits ausstehend.'], 422);
        }

        DogDeletionRequest::create([
            'dog_id'      => $dog->id,
            'customer_id' => $customer->id,
            'dog_name'    => $dog->name,
            'status'      => 'pending',
        ]);

        return response()->json(['message' => 'Löschanfrage wurde weitergeleitet.'], 201);
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
