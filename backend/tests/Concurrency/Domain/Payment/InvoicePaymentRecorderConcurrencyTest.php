<?php

declare(strict_types=1);

use App\Exceptions\InvoiceOverpaymentException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoicePaymentRecorder;
use Illuminate\Support\Facades\DB;

uses()->group('concurrency', 'payment');

/**
 * Dedicated concurrency test for InvoicePaymentRecorder::record().
 *
 * Lives outside `tests/Feature/` (own `<testsuite>` in `phpunit.xml`,
 * own `pest()->extend(TestCase::class)->in('Concurrency')` binding in
 * the root `tests/Pest.php` — Pest only auto-loads a single top-level
 * `tests/Pest.php`, not per-directory config files) and deliberately
 * does NOT use `RefreshDatabase`: the root config applies
 * `RefreshDatabase` to every test under `tests/Feature/` via
 * `.in('Feature')`, which wraps a test in a single uncommitted
 * transaction on the *parent* process's connection and rolls it back
 * afterwards. The two child processes forked below open their own,
 * independent connections and would never see the parent's uncommitted
 * fixture (verified empirically: keeping this test under
 * `tests/Feature/` made both forked children fail with "No query
 * results for model [App\Models\Invoice]"). Test data is therefore
 * created and torn down explicitly (beforeEach()/afterEach()).
 *
 * This test only exercises real row locking against an MVCC-capable
 * database (PostgreSQL/MySQL) — lockForUpdate() is a no-op on SQLite, so
 * it is skipped there. See task-T02.notes.md for how to run it against a
 * dedicated PostgreSQL test database and for the documented result.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped(
            'Benötigt eine echte MVCC-Datenbank (PostgreSQL/MySQL) für echte Zeilensperren, siehe task-T02.notes.md.'
        );
    }

    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->create(['user_id' => $customerUser->id]);

    $this->invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'sent',
        'total_amount' => 100.00,
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    Payment::query()->where('invoice_id', $this->invoice->id)->delete();
    $this->invoice->delete();
    // User uses SoftDeletes — forceDelete() keeps this dedicated test
    // database from accumulating soft-deleted rows across repeated runs.
    $this->invoice->customer->user->forceDelete();
    $this->invoice->customer->delete();
});

it('verliert keine teilzahlung wenn zwei zahlungen nahezu gleichzeitig für dieselbe rechnung erfasst werden', function () {
    $invoiceId = $this->invoice->id;

    // All children start their actual work at the same wall-clock
    // instant (~300ms in the future), so the two record() calls
    // genuinely race for the Invoice row lock instead of merely running
    // one after another because fork() itself takes a moment.
    $startAt = microtime(true) + 0.3;

    $childAmounts = [50.00, 50.00];
    $pids = [];

    // No open connection may survive into pcntl_fork(): both children
    // would otherwise inherit and corrupt the same underlying socket.
    // Each child lazily opens its own fresh connection on first query.
    // Disconnecting immediately before the fork loop (rather than in
    // beforeEach()) avoids any framework-internal query re-opening the
    // connection in between.
    DB::disconnect();

    foreach ($childAmounts as $amount) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork() ist fehlgeschlagen.');
        }

        if ($pid === 0) {
            while (microtime(true) < $startAt) {
                usleep(1000);
            }

            try {
                $invoice = Invoice::findOrFail($invoiceId);
                app(InvoicePaymentRecorder::class)->record($invoice, [
                    'payment_date' => today(),
                    'amount' => $amount,
                    'payment_method' => 'cash',
                    'status' => 'completed',
                ]);

                exit(0);
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage());
                exit(1);
            }
        }

        $pids[] = $pid;
    }

    $exitStatuses = [];
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $exitStatuses[] = pcntl_wexitstatus($status);
    }

    expect($exitStatuses)->toBe([0, 0]);

    $invoice = Invoice::findOrFail($invoiceId);

    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date)->not->toBeNull();
    expect(Payment::where('invoice_id', $invoiceId)->count())->toBe(2);
    expect((float) Payment::where('invoice_id', $invoiceId)->where('status', 'completed')->sum('amount'))->toBe(100.0);
});

it('lehnt die zweite von zwei nahezu gleichzeitigen zahlungen ab, wenn deren summe den restbetrag übersteigt', function () {
    // Regression test for the TOCTOU overpayment race fixed by
    // InvoiceOverpaymentException (see task-fix-overpayment-race.notes.md
    // and the "Muss"-finding in task-T03.review.md): each child requests
    // 80.00 against a 100.00 invoice — individually well within the
    // remaining balance a naive pre-lock check would see — but their sum
    // (160.00) exceeds it. Only the first child to acquire the row lock
    // may succeed; the second must observe the now-reduced remaining
    // balance (20.00) and be rejected, never both succeed.
    $invoiceId = $this->invoice->id;

    $startAt = microtime(true) + 0.3;

    $childAmounts = [80.00, 80.00];
    $pids = [];

    DB::disconnect();

    foreach ($childAmounts as $amount) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork() ist fehlgeschlagen.');
        }

        if ($pid === 0) {
            while (microtime(true) < $startAt) {
                usleep(1000);
            }

            try {
                $invoice = Invoice::findOrFail($invoiceId);
                app(InvoicePaymentRecorder::class)->record($invoice, [
                    'payment_date' => today(),
                    'amount' => $amount,
                    'payment_method' => 'cash',
                    'status' => 'completed',
                ]);

                exit(0);
            } catch (InvoiceOverpaymentException) {
                // Expected outcome for exactly one of the two children.
                exit(1);
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage());
                exit(2);
            }
        }

        $pids[] = $pid;
    }

    $exitStatuses = [];
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $exitStatuses[] = pcntl_wexitstatus($status);
    }

    // Order of lock acquisition is not deterministic — one child must
    // succeed (0) and the other must be rejected with
    // InvoiceOverpaymentException (1). Neither an unexpected error (2)
    // nor both succeeding (would show as [0, 0]) is acceptable.
    sort($exitStatuses);
    expect($exitStatuses)->toBe([0, 1]);

    $invoice = Invoice::findOrFail($invoiceId);

    expect($invoice->status)->toBe('sent');
    expect(Payment::where('invoice_id', $invoiceId)->count())->toBe(1);
    expect((float) Payment::where('invoice_id', $invoiceId)->where('status', 'completed')->sum('amount'))->toBe(80.0);
});
