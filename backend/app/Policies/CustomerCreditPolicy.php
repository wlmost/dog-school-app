<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerCredit;
use App\Models\User;

/**
 * Customer Credit Policy
 *
 * Defines authorization logic for customer credit operations.
 */
class CustomerCreditPolicy
{
    /**
     * Determine whether the user can view any customer credits.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view customer credits
        return true;
    }

    /**
     * Determine whether the user can view the customer credit.
     */
    public function view(User $user, CustomerCredit $customerCredit): bool
    {
        // Admins and trainers can view any customer credit
        if ($user->isAdminOrTrainer()) {
            return true;
        }

        // Customers can only view their own credits
        return $user->isCustomer() && $customerCredit->customer->user_id === $user->id;
    }

    /**
     * Determine whether the user can create customer credits.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create customer credits
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the customer credit.
     */
    public function update(User $user, CustomerCredit $customerCredit): bool
    {
        // Only admins and trainers can update customer credits
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can delete the customer credit.
     */
    public function delete(User $user, CustomerCredit $customerCredit): bool
    {
        // Only admins can delete customer credits
        return $user->isAdmin();
    }
}
