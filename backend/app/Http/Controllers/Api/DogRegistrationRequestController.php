<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDogRegistrationRequest;
use App\Http\Resources\DogRegistrationRequestResource;
use App\Http\Resources\DogResource;
use App\Mail\DogRegistrationApproved;
use App\Mail\DogRegistrationReceived;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\DogRegistrationRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * DogRegistrationRequest Controller
 *
 * Handles the lifecycle of customer-submitted dog registration requests:
 * - Customers submit requests that await admin review.
 * - Admins can approve (which creates a Dog record) or reject a pending request.
 */
class DogRegistrationRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a paginated listing of dog registration requests.
     *
     * Admins see all requests with optional ?status= filter.
     * Customers see only their own requests.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DogRegistrationRequest::class);

        $user  = $request->user();
        $query = DogRegistrationRequest::query()->with(['customer.user']);

        if ($user->isCustomer()) {
            $customer = Customer::where('user_id', $user->id)->first();

            if ($customer) {
                $query->where('customer_id', $customer->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        // Admin sees everything; optionally filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return DogRegistrationRequestResource::collection(
            $query->orderBy('created_at', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a new pending dog registration request submitted by a customer.
     *
     * After creation, all admin users are notified by email.
     *
     * @param StoreDogRegistrationRequest $request
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function store(StoreDogRegistrationRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $this->authorize('create', DogRegistrationRequest::class);

        $customer = Customer::where('user_id', $request->user()->id)->first();

        if (! $customer) {
            return response()->json([
                'message' => 'No customer profile found for the authenticated user.',
            ], 422);
        }

        $data = $request->validatedSnakeCase();
        $data['customer_id'] = $customer->id;
        $data['status']      = 'pending';

        $registrationRequest = DogRegistrationRequest::create($data);
        $registrationRequest->load('customer.user');

        // Notify all admins about the new request
        User::where('role', 'admin')->get()->each(
            fn (User $admin) => Mail::to($admin->email)->send(
                new DogRegistrationReceived($registrationRequest)
            )
        );

        return (new DogRegistrationRequestResource($registrationRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified dog registration request.
     *
     * @param DogRegistrationRequest $dogRegistrationRequest
     * @return DogRegistrationRequestResource
     */
    public function show(DogRegistrationRequest $dogRegistrationRequest): DogRegistrationRequestResource
    {
        $this->authorize('view', $dogRegistrationRequest);

        return new DogRegistrationRequestResource(
            $dogRegistrationRequest->load('customer.user')
        );
    }

    /**
     * Approve a pending dog registration request.
     *
     * Creates the corresponding Dog record, marks the request as approved,
     * and sends a confirmation email to the customer.
     *
     * @param Request              $request
     * @param DogRegistrationRequest $dogRegistrationRequest
     * @return JsonResponse|\Illuminate\Http\Response
     */
    public function approve(Request $request, DogRegistrationRequest $dogRegistrationRequest): JsonResponse|\Illuminate\Http\Response
    {
        $this->authorize('approve', $dogRegistrationRequest);

        if (! $dogRegistrationRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $dog = DB::transaction(function () use ($request, $dogRegistrationRequest): Dog {
            // Create the actual Dog record from the request data
            $dog = Dog::create([
                'customer_id'  => $dogRegistrationRequest->customer_id,
                'name'         => $dogRegistrationRequest->name,
                'breed'        => $dogRegistrationRequest->breed,
                'gender'       => $dogRegistrationRequest->gender,
                'date_of_birth' => $dogRegistrationRequest->date_of_birth,
                'neutered'     => $dogRegistrationRequest->neutered,
                'chip_number'  => $dogRegistrationRequest->chip_number,
                'is_active'    => true,
            ]);

            // Mark the request as approved
            $dogRegistrationRequest->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return $dog;
        });

        // Load relations needed by email template
        $dogRegistrationRequest->load('customer.user');

        // Notify the customer
        Mail::to($dogRegistrationRequest->customer->user->email)
            ->send(new DogRegistrationApproved($dogRegistrationRequest));

        return (new DogResource($dog->load('customer.user')))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Reject a pending dog registration request.
     *
     * @param Request              $request
     * @param DogRegistrationRequest $dogRegistrationRequest
     * @return JsonResponse|DogRegistrationRequestResource
     */
    public function reject(Request $request, DogRegistrationRequest $dogRegistrationRequest): JsonResponse|DogRegistrationRequestResource
    {
        $this->authorize('reject', $dogRegistrationRequest);

        if (! $dogRegistrationRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending requests can be rejected.',
            ], 422);
        }

        $dogRegistrationRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return new DogRegistrationRequestResource($dogRegistrationRequest->load('customer.user'));
    }
}
