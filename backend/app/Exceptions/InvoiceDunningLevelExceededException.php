<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\InvoiceDunningRecorder;

/**
 * InvoiceDunningLevelExceededException
 *
 * Thrown by {@see InvoiceDunningRecorder::trigger()} when the *locked*
 * invoice's next dunning level (`App\Support\DunningFeeSchedule::nextLevel()`)
 * would exceed `config('invoicing.max_dunning_level')` — i.e. the maximum
 * of 3 App-internal dunning levels (bindende Entscheidung 3, see
 * `design.md`) has already been reached.
 *
 * This is the authoritative upper-bound guard. It is deliberately checked
 * *inside* the row-locked critical section, after `lockForUpdate()`
 * (see `design.md` Decision D3): two nearly-simultaneous `trigger()` calls
 * for the same invoice could otherwise both read the same `dunning_level`
 * before either commits and both compute the same "next" level, producing
 * two dunning-fee documents for what should be a single level transition —
 * the identical TOCTOU race already closed for overpayments by
 * {@see InvoiceOverpaymentException} in `InvoicePaymentRecorder::record()`.
 */
class InvoiceDunningLevelExceededException extends \RuntimeException
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly int $maxDunningLevel
    ) {
        parent::__construct(sprintf(
            'Invoice #%d has already reached the maximum dunning level %d.',
            $invoiceId,
            $maxDunningLevel
        ));
    }
}
