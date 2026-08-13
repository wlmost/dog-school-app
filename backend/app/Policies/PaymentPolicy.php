<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
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
     * Determine whether the user can create a payment for the given invoice.
     *
     * Trainers may only record payments for invoices whose customer is one
     * of their own assigned customers (`Customer::trainer_id === $user->id`)
     * — the same scoping rule `InvoiceController::index()` already applies
     * for trainers, so a trainer cannot record a payment for a foreign
     * customer's invoice via the new payment-entry UI.
     */
    public function create(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTrainer() && $invoice->customer->trainer_id === $user->id;
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
