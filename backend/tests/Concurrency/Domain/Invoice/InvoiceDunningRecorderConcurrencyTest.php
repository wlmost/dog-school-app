<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceDunning;
use App\Models\User;
use App\Services\InvoiceDunningRecorder;
use Illuminate\Support\Facades\DB;

uses()->group('concurrency', 'invoice');

/**
 * Dedicated concurrency test for InvoiceDunningRecorder::trigger().
 *
 * Lives outside `tests/Feature/` for the same structural reason as
 * `InvoicePaymentRecorderConcurrencyTest.php` (see that file's docblock
 * and `task-T02.notes.md` of `add-invoice-payment-entry`): the two
 * child processes forked below open their own, independent database
 * connections and would never see a parent-process fixture created
 * inside `RefreshDatabase`'s uncommitted transaction wrapper. Test data
 * is therefore created and torn down explicitly (beforeEach()/afterEach()).
 *
 * This test only exercises real row locking against an MVCC-capable
 * database (PostgreSQL/MySQL) — `lockForUpdate()` is a no-op on SQLite, so
 * it is skipped there. See `task-T02.notes.md` of
 * `add-invoice-dunning-dashboard` for whether/how this was run against a
 * dedicated PostgreSQL test database.
 *
 * What "genau ein Übergang auf Level 1, keine doppelte Stufe" means here:
 * unlike a rejected overpayment, there is no legitimate reason for the
 * *second* caller to fail outright — `DunningFeeSchedule::nextLevel(1)`
 * is a perfectly valid `2`. With `lockForUpdate()` working correctly, the
 * second child blocks until the first commits, then reads the
 * now-committed `dunning_level = 1` and legitimately advances to level 2.
 * Both calls succeed. The bug this test guards against is the two calls
 * racing to read `dunning_level = null` *before* either commits and both
 * computing the same "next" level 1 — which would show up as two
 * `InvoiceDunning` rows at level 1 (and two dunning-fee documents for the
 * same level) instead of one row each at level 1 and level 2.
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
        'invoice_number' => 'RE-2026-9001',
        'status' => 'sent',
        'total_amount' => 100.00,
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        return;
    }

    InvoiceDunning::query()->where('invoice_id', $this->invoice->id)->delete();
    Invoice::query()->where('original_invoice_id', $this->invoice->id)->delete();
    $this->invoice->delete();
    // User uses SoftDeletes — forceDelete() keeps this dedicated test
    // database from accumulating soft-deleted rows across repeated runs.
    $this->invoice->customer->user->forceDelete();
    $this->invoice->customer->delete();
});

it('lässt bei zwei nahezu gleichzeitigen mahnungs-triggern für dieselbe rechnung keine doppelte stufe zu', function () {
    $invoiceId = $this->invoice->id;

    // Both children start their actual work at the same wall-clock
    // instant (~300ms in the future), so the two trigger() calls
    // genuinely race for the Invoice row lock instead of merely running
    // one after another because fork() itself takes a moment.
    $startAt = microtime(true) + 0.3;

    $pids = [];

    // No open connection may survive into pcntl_fork(): both children
    // would otherwise inherit and corrupt the same underlying socket.
    // Each child lazily opens its own fresh connection on first query.
    DB::disconnect();

    for ($i = 0; $i < 2; $i++) {
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
                app(InvoiceDunningRecorder::class)->trigger($invoice);

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

    // Both calls are legitimate (see class docblock): the second one, once
    // it acquires the lock after the first committed, correctly observes
    // the now-advanced level and proceeds to the next one. Both must
    // succeed — a crash (1) would indicate an unexpected failure, not the
    // race this test targets.
    expect($exitStatuses)->toBe([0, 0]);

    $invoice = Invoice::findOrFail($invoiceId);
    $dunnings = InvoiceDunning::where('invoice_id', $invoiceId)->orderBy('level')->get();

    expect($invoice->status)->toBe('reminded');
    expect($dunnings)->toHaveCount(2);
    // The authoritative assertion: no duplicate level. Two nearly
    // simultaneous callers must never both land on level 1 — the row lock
    // serializes them onto distinct, consecutive levels.
    expect($dunnings->pluck('level')->sort()->values()->all())->toBe([1, 2]);
    expect(
        Invoice::where('original_invoice_id', $invoiceId)->where('document_type', 'dunning_fee')->count()
    )->toBe(2);
});
