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
     *
     * Customers may only view their own invoices, and only once they have
     * left `draft` state: a draft has no assigned invoice number yet and is
     * not meant to be visible to the customer, and a `cancelled` invoice is
     * superseded by its cancellation invoice. `overdue` remains in the
     * whitelist for any pre-existing legacy rows (see `design.md` Decision
     * D3) even though it is no longer actively written.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Admins and trainers can view any invoice
        if ($user->isAdminOrTrainer()) {
            return true;
        }

        // Customers can only view their own invoices
        if (! $user->isCustomer() || $invoice->customer->user_id !== $user->id) {
            return false;
        }

        return in_array($invoice->status, ['sent', 'paid', 'overdue', 'reminded'], true);
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
     *
     * Only draft invoices may be updated via `PUT` — once an invoice has
     * been finalized (assigned a number, sent, paid, ...), its content is
     * considered fixed; corrections happen via {@see self::cancel()}
     * instead.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Only admins and trainers can update draft invoices
        return $user->isAdminOrTrainer() && $invoice->status === 'draft';
    }

    /**
     * Determine whether the user can delete the invoice.
     *
     * Only draft invoices may be deleted — a finalized invoice has already
     * been assigned a number and must be storno'd (see
     * {@see self::cancel()}), not removed.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Only admins can delete draft invoices
        return $user->isAdmin() && $invoice->status === 'draft';
    }

    /**
     * Determine whether the user can finalize (release) the invoice.
     *
     * Deliberately role-only (no `status === 'draft'` check here, see
     * `task-T04.notes.md` "Abweichungen von der Task-Beschreibung"): the
     * draft-state business rule is enforced in
     * `InvoiceController::finalize()`, which returns a 422 with an
     * explanatory message for a non-draft invoice. If the status check
     * were duplicated here, `authorize()` would reject a repeated
     * finalize() call on an already-open invoice with a bare 403 before
     * the controller's explicit 422 branch is ever reached — matching
     * neither the acceptance criteria (HTTP 422 with a message) nor the
     * established house pattern for state conflicts (see
     * {@see self::send()}, which follows the same split: policy =
     * "may this role act at all", controller = "is this action valid given
     * the invoice's current state").
     */
    public function finalize(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrTrainer();
    }

    /**
     * Determine whether the user can cancel (storno) the invoice.
     *
     * Unlike {@see self::finalize()}, the status/state rule *is* enforced
     * here rather than in the controller: `tasks.md` T05's acceptance
     * criteria require **HTTP 403** (not 422) for every rejection reason
     * (draft, already-cancelled, cancellation-of-a-cancellation, wrong
     * role) — i.e. all rejection paths share the same status code, so
     * there is no 403-vs-422 ambiguity to resolve by splitting the check
     * across policy and controller as `finalize()` had to (see
     * `task-T04.notes.md` "Abweichungen von der Task-Beschreibung"). The
     * `original_invoice_id === null` check also prevents storno of an
     * already-created cancellation invoice (D5/Non-Goals in `design.md`).
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrTrainer()
            && in_array($invoice->status, ['sent', 'paid', 'reminded'], true)
            && $invoice->original_invoice_id === null;
    }

    /**
     * Determine whether the user can (re-)send the invoice by email.
     *
     * Deliberately role-only, same split as {@see self::finalize()}: the
     * status whitelist (`sent`/`reminded`/`overdue`) and the "customer has
     * an email address" check are both
     * enforced as HTTP 422 in `InvoiceController::sendEmail()`, not here
     * — "policy = may this role act at all, controller = is this action
     * valid given the invoice's current state" (see `design.md` Decision
     * D7).
     */
    public function send(User $user, Invoice $invoice): bool
    {
        return $user->isAdminOrTrainer();
    }
}
