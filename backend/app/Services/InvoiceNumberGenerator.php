<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * InvoiceNumberGenerator
 *
 * Generates the next sequential invoice number in the format
 * `RE-{Jahr}-{4-stellig, zero-padded}` (e.g. `RE-2026-0007`). Numbers are
 * scoped per calendar year and start again at `0001` for a new year.
 *
 * Invoice numbers are only assigned when a draft invoice transitions to
 * an open/cancellation invoice (see `InvoiceController::finalize()` /
 * `cancel()`), never when a draft is first created.
 */
class InvoiceNumberGenerator
{
    /**
     * Generates the next invoice number for the current year.
     *
     * The lookup of the last assigned number runs inside a DB transaction
     * with a row lock ({@see Builder::lockForUpdate()}) on the matching
     * rows, so two nearly-simultaneous calls that read an *existing*
     * highest number for the year cannot observe the same row and derive
     * the same next number. `lockForUpdate()` is a no-op on SQLite (the
     * test driver) but is fully supported by MySQL and PostgreSQL.
     *
     * @see task-T03.notes.md for a documented, empirically confirmed gap
     *      in this locking strategy (an empty result set locks nothing)
     *      that callers/architecture must be aware of.
     */
    public function generate(): string
    {
        $year = date('Y');

        return DB::transaction(function () use ($year): string {
            $lastInvoice = Invoice::where('invoice_number', 'like', "RE-{$year}-%")
                ->orderByDesc('invoice_number')
                ->lockForUpdate()
                ->first();

            $nextNumber = $lastInvoice
                ? ((int) substr($lastInvoice->invoice_number, -4)) + 1
                : 1;

            return sprintf('RE-%s-%04d', $year, $nextNumber);
        });
    }
}
