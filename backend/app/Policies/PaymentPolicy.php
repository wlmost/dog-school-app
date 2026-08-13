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
     *
     * Trainers may only view payments whose invoice belongs to one of their
     * own assigned customers (`Customer::trainer_id === $user->id`) — the
     * same scoping rule already applied to `PaymentController::index()`, so
     * a trainer cannot fetch a foreign customer's payment by guessing/
     * enumerating its id.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Admins can view any payment
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTrainer()) {
            return $payment->invoice->customer->trainer_id === $user->id;
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
     *
     * Same trainer-scoping rule as {@see self::view()}: a trainer may only
     * update payments belonging to their own assigned customers. Customers
     * are never allowed to update payments (unchanged).
     */
    public function update(User $user, Payment $payment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTrainer() && $payment->invoice->customer->trainer_id === $user->id;
    }

    /**
     * Determine whether the user can delete the payment.
     *
     * Only admins can delete payments — trainers have no delete permission
     * at all, so no trainer-scoping is needed here (unlike {@see self::view()}
     * and {@see self::update()}).
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Only admins can delete payments
        return $user->isAdmin();
    }
}
