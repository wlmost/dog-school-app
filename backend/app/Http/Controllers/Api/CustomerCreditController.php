<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerCreditRequest;
use App\Http\Requests\UpdateCustomerCreditRequest;
use App\Http\Resources\CustomerCreditResource;
use App\Models\CustomerCredit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Customer Credit Controller
 *
 * Handles management of customer credit purchases and usage.
 */
class CustomerCreditController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of customer credits with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CustomerCredit::query()->with(['customer.user', 'package']);

        // Filter by customer
        if ($request->has('customerId')) {
            $query->where('customer_id', $request->input('customerId'));
        }

        // Filter by package
        if ($request->has('packageId')) {
            $query->where('credit_package_id', $request->input('packageId'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter active credits
        if ($request->boolean('activeOnly')) {
            $query->active();
        }

        // Filter expired credits
        if ($request->boolean('expiredOnly')) {
            $query->expired();
        }

        return CustomerCreditResource::collection(
            $query->orderBy('purchase_date', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created customer credit (purchase).
     *
     * @param StoreCustomerCreditRequest $request
     * @return CustomerCreditResource
     */
    public function store(StoreCustomerCreditRequest $request): CustomerCreditResource
    {
        $this->authorize('create', CustomerCredit::class);

        $credit = CustomerCredit::create($request->validatedSnakeCase());

        return new CustomerCreditResource($credit->load(['customer.user', 'package']));
    }

    /**
     * Display the specified customer credit.
     *
     * @param CustomerCredit $customerCredit
     * @return CustomerCreditResource
     */
    public function show(CustomerCredit $customerCredit): CustomerCreditResource
    {
        // Load customer for authorization check
        $customerCredit->load(['customer.user', 'package']);
        
        $this->authorize('view', $customerCredit);
        
        return new CustomerCreditResource($customerCredit);
    }

    /**
     * Update the specified customer credit.
     *
     * @param UpdateCustomerCreditRequest $request
     * @param CustomerCredit $customerCredit
     * @return CustomerCreditResource
     */
    public function update(UpdateCustomerCreditRequest $request, CustomerCredit $customerCredit): CustomerCreditResource
    {
        $this->authorize('update', $customerCredit);

        $customerCredit->update($request->validatedSnakeCase());

        return new CustomerCreditResource($customerCredit->fresh(['customer.user', 'package']));
    }

    /**
     * Remove the specified customer credit.
     *
     * @param CustomerCredit $customerCredit
     * @return JsonResponse
     */
    public function destroy(CustomerCredit $customerCredit): JsonResponse
    {
        $this->authorize('delete', $customerCredit);

        // Check if credits have been used
        if ($customerCredit->remaining_credits < $customerCredit->total_credits) {
            return response()->json([
                'message' => 'Guthaben kann nicht gelöscht werden, da bereits Einheiten verwendet wurden.',
            ], 422);
        }

        $customerCredit->delete();

        return response()->json(null, 204);
    }

    /**
     * Use a credit from the customer's balance.
     *
     * @param CustomerCredit $customerCredit
     * @return CustomerCreditResource|JsonResponse
     */
    public function useCredit(CustomerCredit $customerCredit): CustomerCreditResource|JsonResponse
    {
        $this->authorize('update', $customerCredit);

        // Check remaining credits first (more specific error)
        if ($customerCredit->remaining_credits <= 0) {
            return response()->json([
                'message' => 'Keine Einheiten mehr verfügbar.',
            ], 422);
        }

        // Then check if active/not expired
        if ($customerCredit->status !== 'active' || ($customerCredit->expiration_date && $customerCredit->expiration_date->isPast())) {
            return response()->json([
                'message' => 'Guthaben ist nicht aktiv oder abgelaufen.',
            ], 422);
        }

        $customerCredit->useCredit();

        return new CustomerCreditResource($customerCredit->fresh(['customer.user', 'package']));
    }

    /**
     * Get active credits for a customer.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function active(Request $request): AnonymousResourceCollection
    {
        $customerId = $request->input('customerId');

        if (!$customerId) {
            return response()->json([
                'message' => 'Customer ID ist erforderlich.',
            ], 422);
        }

        $credits = CustomerCredit::where('customer_id', $customerId)
            ->active()
            ->with(['package'])
            ->orderBy('expiration_date', 'asc')
            ->get();

        return CustomerCreditResource::collection($credits);
    }
}
