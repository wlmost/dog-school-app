<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Payment Policy
 *
 * Defines authorization logic for payment operations.
 */
class PaymentPolicy
{
    /**
     * Determine whether the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view payments
        return true;
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Admins and trainers can view any payment
        if ($user->isAdminOrTrainer()) {
            return true;
        }

        // Customers can only view their own invoice's payments
        return $user->isCustomer() && $payment->invoice->customer->user_id === $user->id;
    }

    /**
     * Determine whether the user can create payments.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create payments
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Only admins and trainers can update payments
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Only admins can delete payments
        return $user->isAdmin();
    }
}
