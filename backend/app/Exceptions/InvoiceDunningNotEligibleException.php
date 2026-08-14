<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\InvoiceDunningRecorder;

/**
 * InvoiceDunningNotEligibleException
 *
 * Thrown by {@see InvoiceDunningRecorder::trigger()} when the *locked*
 * invoice is not eligible for a dunning at all — either because it is
 * itself a child document (a cancellation or a previously created
 * dunning-fee invoice, `document_type !== null`, see `design.md` Decision
 * D1) or because its `status` is not one of the dunning-eligible statuses
 * (`sent`, `reminded`, `overdue`; a `draft` has no assigned invoice number
 * yet, and `paid`/`cancelled` invoices no longer owe anything).
 *
 * Checked *inside* the row-locked critical section, after
 * `lockForUpdate()`, rather than as a Controller-side pre-check (see
 * `design.md` Decision D3) — the eligibility of a `document_type`/`status`
 * combination cannot change concurrently the way `dunning_level` can, but
 * keeping the check next to the level check inside the same lock avoids a
 * second read of the invoice and keeps `trigger()` the single authority on
 * "is this invoice mahnfähig".
 */
class InvoiceDunningNotEligibleException extends \RuntimeException
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly ?string $documentType,
        public readonly string $status
    ) {
        $reason = $documentType !== null
            ? sprintf("is itself a '%s' document", $documentType)
            : sprintf("has status '%s'", $status);

        parent::__construct(sprintf(
            'Invoice #%d is not eligible for a dunning: it %s.',
            $invoiceId,
            $reason
        ));
    }
}
