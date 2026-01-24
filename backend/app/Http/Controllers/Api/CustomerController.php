<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\DatabaseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Customer Controller
 *
 * Handles CRUD operations for customers and their related data.
 */
class CustomerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of customers.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);
        
        $perPage = $request->query('perPage', 15);
        $query = Customer::with(['user', 'dogs', 'trainer']);
        
        // Filter by trainer if user is a trainer
        if ($request->user()->isTrainer()) {
            $query->where('trainer_id', $request->user()->id);
        }
        
        // Filter by search term
        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', DatabaseHelper::caseInsensitiveLike(), "%{$search}%")
                  ->orWhere('last_name', DatabaseHelper::caseInsensitiveLike(), "%{$search}%")
                  ->orWhere('email', DatabaseHelper::caseInsensitiveLike(), "%{$search}%");
            });
        }
        
        // Filter by active credits
        if ($request->query('hasActiveCredits') === 'true') {
            $query->withActiveCredits();
        }
        
        // Sort by
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        
        if (in_array($sortBy, ['created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        } elseif ($sortBy === 'name') {
            $query->join('users', 'customers.user_id', '=', 'users.id')
                  ->orderBy('users.last_name', $sortOrder)
                  ->orderBy('users.first_name', $sortOrder)
                  ->select('customers.*');
        }
        
        $customers = $query->paginate($perPage);
        
        return CustomerResource::collection($customers);
    }

    /**
     * Store a newly created customer.
     *
     * @param StoreCustomerRequest $request
     * @return CustomerResource
     */
    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $data = $request->validatedSnakeCase();
        
        // Auto-assign trainer if user is a trainer and no trainer was specified
        if ($request->user()->isTrainer() && empty($data['trainer_id'])) {
            $data['trainer_id'] = $request->user()->id;
        }
        
        $customer = Customer::create($data);
        $customer->load('user');
        
        return new CustomerResource($customer);
    }

    /**
     * Display the specified customer.
     *
     * @param Customer $customer
     * @return CustomerResource
     */
    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);
        
        $customer->load([
            'user',
            'trainer',
            'dogs',
            'bookings.trainingSession',
            'credits.creditPackage',
            'invoices.items',
        ]);
        
        return new CustomerResource($customer);
    }

    /**
     * Update the specified customer.
     *
     * @param UpdateCustomerRequest $request
     * @param Customer $customer
     * @return CustomerResource
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        // Update customer data
        $customer->update($request->validatedSnakeCase());
        
        // Update user data if provided
        $userData = $request->validatedUserData();
        if (!empty($userData)) {
            $customer->user->update($userData);
        }
        
        $customer->load(['user', 'trainer']);
        
        return new CustomerResource($customer);
    }

    /**
     * Remove the specified customer.
     *
     * @param Customer $customer
     * @return JsonResponse
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        
        // Check if customer has active bookings or credits
        if ($customer->bookings()->where('status', 'confirmed')->exists()) {
            return response()->json([
                'message' => 'Kunde kann nicht gelöscht werden, da aktive Buchungen vorhanden sind.',
            ], 422);
        }
        
        if ($customer->credits()->where('status', 'active')->where('remaining_credits', '>', 0)->exists()) {
            return response()->json([
                'message' => 'Kunde kann nicht gelöscht werden, da aktive Guthaben vorhanden sind.',
            ], 422);
        }
        
        $customer->delete();
        
        return response()->json([
            'message' => 'Kunde erfolgreich gelöscht.',
        ], 200);
    }

    /**
     * Get customer's dogs.
     *
     * @param Customer $customer
     * @return AnonymousResourceCollection
     */
    public function dogs(Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('view', $customer);
        
        return \App\Http\Resources\DogResource::collection(
            $customer->dogs()->with('vaccinations')->get()
        );
    }

    /**
     * Get customer's bookings.
     *
     * @param Customer $customer
     * @return AnonymousResourceCollection
     */
    public function bookings(Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('view', $customer);
        
        return \App\Http\Resources\BookingResource::collection(
            $customer->bookings()
                ->with(['trainingSession.course', 'dog'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * Get customer's invoices.
     *
     * @param Customer $customer
     * @return AnonymousResourceCollection
     */
    public function invoices(Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('view', $customer);
        
        return \App\Http\Resources\InvoiceResource::collection(
            $customer->invoices()
                ->with(['items', 'payments'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * Get customer's credits.
     *
     * @param Customer $customer
     * @return AnonymousResourceCollection
     */
    public function credits(Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('view', $customer);
        
        return \App\Http\Resources\CustomerCreditResource::collection(
            $customer->credits()
                ->with('package')
                ->orderBy('purchase_date', 'desc')
                ->get()
        );
    }
}
