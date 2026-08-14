<?php

declare(strict_types=1);

use App\Exceptions\InvoiceDunningLevelExceededException;
use App\Exceptions\InvoiceDunningNotEligibleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceDunningRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('domain', 'invoice');

beforeEach(function () {
    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->create(['user_id' => $customerUser->id]);

    $this->invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'invoice_number' => 'RE-2026-0001',
        'status' => 'sent',
        'total_amount' => 250.00,
    ]);

    $this->recorder = app(InvoiceDunningRecorder::class);
});

it('erzeugt bei der ersten mahnung stufe 1 mit gebührendokument und wechselt den status auf reminded', function () {
    $dunning = $this->recorder->trigger($this->invoice);

    expect($dunning->level)->toBe(1);
    expect((float) $dunning->fee_amount)->toBe(5.0);

    $feeInvoice = $dunning->feeInvoice;
    expect($feeInvoice)->not->toBeNull();
    expect($feeInvoice->document_type)->toBe('dunning_fee');
    expect($feeInvoice->original_invoice_id)->toBe($this->invoice->id);
    expect((float) $feeInvoice->total_amount)->toBe(5.0);
    expect($feeInvoice->items)->toHaveCount(1);
    expect((float) $feeInvoice->items->first()->amount)->toBe(5.0);
    expect($feeInvoice->items->first()->description)->toContain('Stufe 1');
    expect($feeInvoice->items->first()->description)->toContain('RE-2026-0001');

    $this->assertDatabaseHas('invoice_dunnings', [
        'invoice_id' => $this->invoice->id,
        'level' => 1,
        'fee_invoice_id' => $feeInvoice->id,
    ]);

    expect($this->invoice->fresh()->status)->toBe('reminded');
});

it('erzeugt bei der zweiten mahnung auf einer bereits gemahnten rechnung stufe 2', function () {
    $this->recorder->trigger($this->invoice);

    $secondDunning = $this->recorder->trigger($this->invoice->fresh());

    expect($secondDunning->level)->toBe(2);
    expect((float) $secondDunning->fee_amount)->toBe(10.0);
    expect($secondDunning->feeInvoice->document_type)->toBe('dunning_fee');

    expect($this->invoice->fresh()->status)->toBe('reminded');
    expect($this->invoice->fresh()->dunnings)->toHaveCount(2);
});

it('lehnt einen vierten mahnungsversuch nach bereits erreichter stufe 3 ab', function () {
    $this->recorder->trigger($this->invoice);
    $this->recorder->trigger($this->invoice->fresh());
    $this->recorder->trigger($this->invoice->fresh());

    expect($this->invoice->fresh()->dunning_level)->toBe(3);

    expect(fn () => $this->recorder->trigger($this->invoice->fresh()))
        ->toThrow(InvoiceDunningLevelExceededException::class);

    expect($this->invoice->fresh()->dunnings)->toHaveCount(3);
});

it('lehnt eine mahnung auf einer rechnung mit nicht mahnfähigem status ab', function (string $status) {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->invoice->customer_id,
        'status' => $status,
    ]);

    expect(fn () => $this->recorder->trigger($invoice))
        ->toThrow(InvoiceDunningNotEligibleException::class);

    expect($invoice->fresh()->dunnings)->toHaveCount(0);
})->with(['draft', 'paid', 'cancelled']);

it('lehnt eine mahnung auf einem gebührendokument selbst ab', function () {
    $dunning = $this->recorder->trigger($this->invoice);
    $feeInvoice = $dunning->feeInvoice;

    expect(fn () => $this->recorder->trigger($feeInvoice))
        ->toThrow(InvoiceDunningNotEligibleException::class);

    expect($feeInvoice->fresh()->dunnings)->toHaveCount(0);
});

it('lässt den gesamtbetrag der original-rechnung nach mehreren mahnungen unverändert', function () {
    $originalTotal = (float) $this->invoice->total_amount;

    $this->recorder->trigger($this->invoice);
    $this->recorder->trigger($this->invoice->fresh());
    $this->recorder->trigger($this->invoice->fresh());

    expect((float) $this->invoice->fresh()->total_amount)->toBe($originalTotal);
});
