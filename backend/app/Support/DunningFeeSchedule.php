<?php

declare(strict_types=1);

namespace App\Support;

/**
 * DunningFeeSchedule
 *
 * Single source of truth for the fixed dunning fee amounts and the
 * maximum dunning level, backed by `config/invoicing.php` (see design.md
 * Decision D4). Used by `InvoiceDunningRecorder` (fee lookup / upper
 * bound), by `Invoice::getNextDunningLevelAttribute()`/
 * `getNextDunningFeeAmountAttribute()`, and transitively by
 * `InvoiceResource` and the frontend confirmation dialog.
 *
 * Stateless by design (see design.md Decision D4) — both methods are
 * static, mirroring how this class is called throughout the codebase
 * (`DunningFeeSchedule::nextLevel(...)`), rather than requiring
 * instantiation/DI for what is purely a `config('invoicing.*')` lookup.
 */
class DunningFeeSchedule
{
    /**
     * The fixed fee for the given dunning level, or `null` if no fee is
     * configured for it (e.g. a level beyond `max_dunning_level`).
     */
    public static function feeForLevel(int $level): ?float
    {
        $fees = config('invoicing.dunning_fees');

        return isset($fees[$level]) ? (float) $fees[$level] : null;
    }

    /**
     * The next dunning level following `$currentLevel`, or `null` if
     * reaching it would exceed `max_dunning_level` (no further dunning
     * possible via the app).
     */
    public static function nextLevel(?int $currentLevel): ?int
    {
        $next = ($currentLevel ?? 0) + 1;

        return $next <= (int) config('invoicing.max_dunning_level') ? $next : null;
    }
}
