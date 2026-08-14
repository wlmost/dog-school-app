<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\InvoiceDunning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
uses()->group('unit', 'invoice');

// add-invoice-dunning-dashboard T01: Regressionstest für Decision D1
// (design.md) — die neue `document_type`-Spalte muss verhindern, dass
// `cancellationInvoice()` ein Mahngebühren-Dokument statt der echten
// Stornorechnung liefert, sobald beide `original_invoice_id` auf
// dieselbe Original-Rechnung setzen.

it('liefert ausschließlich die echte stornorechnung auch wenn zusätzlich ein mahngebühren-dokument existiert', function () {
    $original = Invoice::factory()->create(['status' => 'sent']);

    $cancellation = Invoice::factory()->create([
        'original_invoice_id' => $original->id,
        'document_type' => 'cancellation',
    ]);

    Invoice::factory()->create([
        'original_invoice_id' => $original->id,
        'document_type' => 'dunning_fee',
    ]);

    expect($original->cancellationInvoice->id)->toBe($cancellation->id);
});

it('liefert alle mahngebühren-dokumente über dunningFeeInvoices() gefiltert nach document_type', function () {
    $original = Invoice::factory()->create(['status' => 'sent']);

    Invoice::factory()->create([
        'original_invoice_id' => $original->id,
        'document_type' => 'cancellation',
    ]);

    $feeInvoice = Invoice::factory()->create([
        'original_invoice_id' => $original->id,
        'document_type' => 'dunning_fee',
    ]);

    expect($original->dunningFeeInvoices->pluck('id')->all())->toBe([$feeInvoice->id]);
});

it('liefert next_dunning_level als 1 wenn noch keine mahnung existiert', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->next_dunning_level)->toBe(1);
});

it('liefert next_dunning_level als null wenn die maximale stufe bereits erreicht ist', function () {
    $invoice = Invoice::factory()->create();
    InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'level' => 3]);

    expect($invoice->next_dunning_level)->toBeNull();
});

it('liefert next_dunning_fee_amount passend zur nächsten mahnstufe', function () {
    $invoice = Invoice::factory()->create();
    InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'level' => 1]);

    expect($invoice->next_dunning_fee_amount)->toBe(10.0);
});

it('liefert next_dunning_fee_amount als null wenn die maximale stufe bereits erreicht ist', function () {
    $invoice = Invoice::factory()->create();
    InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'level' => 3]);

    expect($invoice->next_dunning_fee_amount)->toBeNull();
});
