<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * Invoice Policy
 *
 * Defines authorization logic for invoice operations.
 */
class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view invoices
        return true;
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Admins and trainers can view any invoice
        if ($user->isAdminOrTrainer()) {
            return true;
        }

        // Customers can only view their own invoices
        return $user->isCustomer() && $invoice->customer->user_id === $user->id;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        // Only admins and trainers can create invoices
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Only admins and trainers can update invoices
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Only admins can delete invoices
        return $user->isAdmin();
    }
}
