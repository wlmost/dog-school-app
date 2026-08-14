<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvoiceDunningLevelExceededException;
use App\Exceptions\InvoiceDunningNotEligibleException;
use App\Models\Invoice;
use App\Models\InvoiceDunning;
use App\Support\DunningFeeSchedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * InvoiceDunningRecorder
 *
 * Single point of entry for triggering a dunning against an invoice:
 * creates the standalone dunning-fee document (a regular `Invoice` with
 * its own invoice number, see `design.md` Decision D5), the
 * `InvoiceDunning` record, and moves the original invoice's `status` to
 * `reminded` — all atomically.
 *
 * `trigger()` runs inside a `DB::transaction()` with an
 * `Invoice::query()->lockForUpdate()->findOrFail()` row lock, mirroring
 * {@see InvoicePaymentRecorder}. Unlike a pure status transition, a fresh
 * `invoice_number` must also be assigned (like
 * `InvoiceController::cancel()`), so the number generation + persist step
 * is retried up to {@see self::FEE_INVOICE_MAX_ATTEMPTS} times on a
 * `UniqueConstraintViolationException`, 1:1 mirroring
 * `InvoiceController::createCancellationInvoiceWithRetry()`. Each retry
 * attempt runs in its own **nested** `DB::transaction()` call, which
 * Laravel maps to a SQL `SAVEPOINT` — this matters specifically for
 * PostgreSQL, which poisons the *entire* enclosing transaction on any
 * failed statement, so without a savepoint-scoped rollback the retry
 * itself (and the subsequent `InvoiceDunning::create()`/status update in
 * this method) would fail with "current transaction is aborted" (see
 * `InvoiceController::cancel()`'s docblock for the full rationale).
 *
 * The eligibility check ({@see InvoiceDunningNotEligibleException}) and
 * the dunning-level upper-bound check
 * ({@see InvoiceDunningLevelExceededException}) both run *inside* the
 * row-locked critical section, after `lockForUpdate()` but before any
 * write — see `design.md` Decision D3: two nearly-simultaneous `trigger()`
 * calls for the same invoice could otherwise both read the same
 * `dunning_level` before either commits and both compute the same "next"
 * level, producing two dunning-fee documents for a single level
 * transition. A Controller-side pre-check (like `finalize()`/`send()`
 * use) would leave this exact race window open.
 */
class InvoiceDunningRecorder
{
    /**
     * Maximum number of attempts to generate a fresh invoice number and
     * persist the dunning-fee document, before giving up on a repeated
     * unique-constraint collision. Mirrors
     * `InvoiceController::CANCEL_MAX_ATTEMPTS`.
     */
    private const FEE_INVOICE_MAX_ATTEMPTS = 3;

    /**
     * Invoice statuses eligible for a dunning. Mirrors
     * `InvoiceController::SENDABLE_STATUSES` (a draft has no assigned
     * invoice number yet, and `paid`/`cancelled` invoices no longer owe
     * anything).
     */
    private const ELIGIBLE_STATUSES = ['sent', 'reminded', 'overdue'];

    public function __construct(private readonly InvoiceNumberGenerator $numberGenerator) {}

    /**
     * Triggers the next dunning level for the given invoice: creates a
     * standalone dunning-fee document, records the `InvoiceDunning`, and
     * sets the original invoice's status to `reminded`.
     *
     * @throws InvoiceDunningNotEligibleException if the locked invoice is
     *                                            itself a child document (`document_type !== null`) or has a
     *                                            non-eligible status.
     * @throws InvoiceDunningLevelExceededException if the next dunning
     *                                              level would exceed `config('invoicing.max_dunning_level')`.
     */
    public function trigger(Invoice $invoice): InvoiceDunning
    {
        return DB::transaction(function () use ($invoice): InvoiceDunning {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->document_type !== null || ! in_array($locked->status, self::ELIGIBLE_STATUSES, true)) {
                throw new InvoiceDunningNotEligibleException($locked->id, $locked->document_type, $locked->status);
            }

            $nextLevel = DunningFeeSchedule::nextLevel($locked->dunning_level);

            if ($nextLevel === null) {
                throw new InvoiceDunningLevelExceededException($locked->id, (int) config('invoicing.max_dunning_level'));
            }

            $fee = DunningFeeSchedule::feeForLevel($nextLevel);

            $feeInvoice = $this->createFeeInvoiceWithRetry($locked, $nextLevel, $fee);

            $feeInvoice->items()->create([
                'description' => "Mahngebühr Stufe {$nextLevel} zu Rechnung {$locked->invoice_number}",
                'quantity' => 1,
                'unit_price' => $fee,
                'tax_rate' => 0,
                'amount' => $fee,
            ]);

            $dunning = InvoiceDunning::create([
                'invoice_id' => $locked->id,
                'level' => $nextLevel,
                'dunning_date' => today(),
                'fee_amount' => $fee,
                'fee_invoice_id' => $feeInvoice->id,
            ]);

            $locked->update(['status' => 'reminded']);

            return $dunning->fresh(['invoice', 'feeInvoice']);
        });
    }

    /**
     * Creates the dunning-fee document header (without its `InvoiceItem`
     * line) for {@see self::trigger()}, retrying on a unique-constraint
     * collision of the generated invoice number. See the class docblock
     * for why each attempt runs in its own nested `DB::transaction()`.
     */
    private function createFeeInvoiceWithRetry(Invoice $locked, int $level, float $fee): Invoice
    {
        $feeInvoice = null;

        for ($attempt = 1; $attempt <= self::FEE_INVOICE_MAX_ATTEMPTS; $attempt++) {
            try {
                $feeInvoice = DB::transaction(function () use ($locked, $level, $fee): Invoice {
                    return Invoice::create([
                        'customer_id' => $locked->customer_id,
                        'invoice_number' => $this->numberGenerator->generate(),
                        'original_invoice_id' => $locked->id,
                        'document_type' => 'dunning_fee',
                        'status' => 'sent',
                        'total_amount' => $fee,
                        'issue_date' => today(),
                        'due_date' => today(),
                        'notes' => "Mahngebühr Stufe {$level} zu Rechnung {$locked->invoice_number}",
                    ]);
                });

                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::FEE_INVOICE_MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        return $feeInvoice;
    }
}
