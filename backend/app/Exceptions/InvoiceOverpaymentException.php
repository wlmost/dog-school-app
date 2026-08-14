<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\InvoicePaymentRecorder;

/**
 * InvoiceOverpaymentException
 *
 * Thrown by {@see InvoicePaymentRecorder::record()} when a
 * payment amount exceeds the invoice's remaining balance *as observed
 * inside the row-locked critical section* — i.e. after `lockForUpdate()`
 * has serialized concurrent callers and all previously committed
 * payments for the invoice are already reflected in
 * `Invoice::getRemainingBalanceAttribute()`.
 *
 * This is the authoritative overpayment guard. `PaymentController::store()`
 * additionally performs the same comparison *before* calling `record()` as
 * a fail-fast UX shortcut for the common (non-racing) case, but only the
 * check that raises this exception closes the TOCTOU race between two
 * nearly-simultaneous payment requests for the same invoice — see
 * `task-fix-overpayment-race.notes.md` of `add-invoice-payment-entry` for
 * the full rationale and the review finding this fixes.
 */
class InvoiceOverpaymentException extends \RuntimeException
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly float $attemptedAmount,
        public readonly float $remainingBalance
    ) {
        parent::__construct(sprintf(
            'Payment amount %.2f exceeds remaining balance %.2f for invoice #%d.',
            $attemptedAmount,
            $remainingBalance,
            $invoiceId
        ));
    }
}
