<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Customer Policy
 *
 * Defines authorization logic for customer-related actions.
 */
class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        // Admins and trainers can view all customers
        return $user->isAdmin() || $user->isTrainer();
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        // Admins and trainers can view any customer
        if ($user->isAdmin() || $user->isTrainer()) {
            return true;
        }
        
        // Customers can only view their own profile
        return $user->customer?->id === $customer->id;
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create customers
        return $user->isAdmin() || $user->isTrainer();
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        // Admins and trainers can update any customer
        if ($user->isAdmin() || $user->isTrainer()) {
            return true;
        }
        
        // Customers can only update their own profile
        return $user->customer?->id === $customer->id;
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        // Only admins can delete customers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the customer.
     */
    public function restore(User $user, Customer $customer): bool
    {
        // Only admins can restore customers
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the customer.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        // Only admins can permanently delete customers
        return $user->isAdmin();
    }
}
