<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\DogRegistrationRequest;
use App\Models\User;

/**
 * DogRegistrationRequest Policy
 *
 * Defines authorization rules for dog registration request operations.
 * Admins may manage all requests; customers may only manage their own.
 */
class DogRegistrationRequestPolicy
{
    /**
     * Determine whether the user can list registration requests.
     *
     * Admins see all requests; customers see only their own (filtered in controller).
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCustomer();
    }

    /**
     * Determine whether the user can view a specific registration request.
     *
     * @param User $user
     * @param DogRegistrationRequest $dogRegistrationRequest
     * @return bool
     */
    public function view(User $user, DogRegistrationRequest $dogRegistrationRequest): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Customers may only view their own requests
        $customer = Customer::where('user_id', $user->id)->first();

        return $user->isCustomer()
            && $customer !== null
            && $dogRegistrationRequest->customer_id === $customer->id;
    }

    /**
     * Determine whether the user can create a registration request.
     *
     * Only customers may submit requests.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    /**
     * Determine whether the user can approve a registration request.
     *
     * Only admins may approve requests.
     *
     * @param User $user
     * @param DogRegistrationRequest $dogRegistrationRequest
     * @return bool
     */
    public function approve(User $user, DogRegistrationRequest $dogRegistrationRequest): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reject a registration request.
     *
     * Only admins may reject requests.
     *
     * @param User $user
     * @param DogRegistrationRequest $dogRegistrationRequest
     * @return bool
     */
    public function reject(User $user, DogRegistrationRequest $dogRegistrationRequest): bool
    {
        return $user->isAdmin();
    }
}
