<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvoiceOverpaymentException;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * InvoicePaymentRecorder
 *
 * Single point of entry for recording a payment against an invoice and
 * for confirming an already-created (e.g. pending PayPal) payment as
 * completed. Both operations synchronize the invoice's `status`/
 * `paid_date` with the sum of its completed payments atomically.
 *
 * Both public methods run inside their own `DB::transaction()` with an
 * `Invoice::query()->lockForUpdate()->findOrFail()` row lock. This closes
 * the read-then-write race that previously existed at four independent
 * call sites (`PaymentController::store()`, `::markAsCompleted()`,
 * `::handlePaymentCaptureCompleted()`, see `design.md` Decision D2): two
 * nearly-simultaneous payments could otherwise both read a `total_paid`
 * below `total_amount` before either commits, leaving the invoice stuck
 * below `paid` despite being fully paid. Locking an *existing* Invoice
 * row by primary key (unlike the empty-result-set gap documented for
 * `InvoiceNumberGenerator`, see its class docblock and
 * `task-T03.notes.md` of `add-invoice-status-lifecycle`) always locks a
 * concrete row, so `lockForUpdate()` here reliably serializes concurrent
 * callers. `lockForUpdate()` is a no-op on SQLite (the default test
 * driver) but is fully supported by MySQL and PostgreSQL — see
 * `task-T02.notes.md` for the PostgreSQL concurrency verification.
 *
 * `DB::transaction()` calls in this service are safe to nest inside an
 * outer transaction (e.g. a caller that itself wraps `record()` in
 * `DB::transaction()`, or Pest's `RefreshDatabase` transaction wrapper on
 * PostgreSQL): Laravel maps nested `DB::transaction()` calls to SQL
 * `SAVEPOINT`s automatically. Neither method here catches or retries on
 * failure, so — unlike the `finalize()` fix documented in `git log`
 * (commit `4f60e79`) — there is no risk of an unhandled exception
 * poisoning an enclosing PostgreSQL transaction while this service tries
 * to recover from it.
 *
 * `record()` re-validates the payment amount against the *locked*
 * invoice's remaining balance and throws {@see InvoiceOverpaymentException}
 * on overpayment. This closes a second, narrower race than the one
 * described above: `PaymentController::store()` also performs this
 * comparison, but *before* acquiring the lock, purely as a fail-fast UX
 * shortcut. Without the check repeated here, two nearly-simultaneous
 * `store()` calls could both read the same (still unchanged)
 * `remaining_balance`, both pass the controller's pre-check, and then
 * both be recorded here unconditionally — overpaying the invoice despite
 * the pre-check. See `task-fix-overpayment-race.notes.md`.
 */
class InvoicePaymentRecorder
{
    /**
     * Creates a new payment for the given invoice and synchronizes the
     * invoice's status.
     *
     * @param  array<string, mixed>  $paymentData  Payment attributes
     *                                             (`payment_date`, `amount`, `payment_method`, `status`, …).
     *                                             Any `invoice_id` key is ignored — it is always set from the
     *                                             freshly locked invoice.
     *
     * @throws InvoiceOverpaymentException if `amount` exceeds the locked
     *                                     invoice's remaining balance.
     */
    public function record(Invoice $invoice, array $paymentData): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentData): Payment {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $amount = (float) $paymentData['amount'];

            if ($amount > $locked->remaining_balance) {
                throw new InvoiceOverpaymentException($locked->id, $amount, $locked->remaining_balance);
            }

            $payment = Payment::create([...$paymentData, 'invoice_id' => $locked->id]);

            $this->syncStatus($locked, $payment);

            return $payment;
        });
    }

    /**
     * Marks an existing (e.g. previously pending) payment as completed
     * and synchronizes the invoice's status.
     */
    public function completeExisting(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            $payment->update(['status' => 'completed']);

            $this->syncStatus($locked, $payment->fresh());

            return $payment->fresh();
        });
    }

    /**
     * Sets the invoice to `paid` once the sum of its completed payments
     * reaches or exceeds `total_amount`. `paid_date` is set to the
     * payment date of the payment that just completed the invoice — not
     * `now()` — so it reflects when the money was actually received
     * rather than when the record was processed.
     */
    private function syncStatus(Invoice $invoice, Payment $justRecorded): void
    {
        $totalPaid = $invoice->payments()->completed()->sum('amount');

        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update([
                'status' => 'paid',
                'paid_date' => $justRecorded->status === 'completed'
                    ? $justRecorded->payment_date
                    : $invoice->payments()->completed()->max('payment_date'),
            ]);
        }
    }
}
