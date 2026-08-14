<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('api', 'invoice');

// Holistischer Interaktionstest zwischen dem Mahnwesen (Change 4) und dem
// Stornieren (Change 1, `InvoiceController::cancel()`). `InvoicePolicy::
// cancel()` erlaubt den Status `reminded` explizit
// (`in_array($invoice->status, ['sent', 'paid', 'reminded'], true)`), aber
// keine bestehende Testdatei (weder `InvoiceApiTest.php` noch
// `InvoiceDunningApiTest.php`) storniert tatsächlich eine bereits gemahnte
// Rechnung mit vorhandenen Mahngebühren-Dokumenten und prüft, was mit
// diesen beim Stornieren passiert (design.md Non-Goals: "Keine Korrektur-/
// Storno-Möglichkeit für ein bereits erzeugtes Gebührendokument" — sie
// bleiben unverändert bestehen). Dieser Test schließt genau diese Lücke.

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
});

it('lässt eine bereits gemahnte rechnung stornieren ohne die bestehenden mahngebühren-dokumente zu verändern', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'total_amount' => 150.00,
    ]);

    // Zwei echte Mahnungen, bevor die Original-Rechnung storniert wird.
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    expect($invoice->fresh()->status)->toBe('reminded');

    $feeInvoiceIdsBefore = Invoice::query()
        ->where('original_invoice_id', $invoice->id)
        ->where('document_type', 'dunning_fee')
        ->pluck('id', 'invoice_number');

    expect($feeInvoiceIdsBefore)->toHaveCount(2);

    $cancelResponse = $this->actingAs($this->trainer)
        ->postJson("/api/v1/invoices/{$invoice->id}/cancel")
        ->assertOk();

    $cancellationInvoiceId = $cancelResponse->json('data.id');

    expect($invoice->fresh()->status)->toBe('cancelled');

    $this->assertDatabaseHas('invoices', [
        'id' => $cancellationInvoiceId,
        'original_invoice_id' => $invoice->id,
        'document_type' => 'cancellation',
        'total_amount' => -150.00,
    ]);

    // Die beiden Mahngebühren-Dokumente bleiben unverändert bestehen —
    // weder gelöscht noch inhaltlich verändert, weiterhin `status =
    // 'sent'` und `document_type = 'dunning_fee'` (design.md Non-Goals:
    // "Keine Korrektur-/Storno-Möglichkeit für ein bereits erzeugtes
    // Gebührendokument").
    foreach ($feeInvoiceIdsBefore as $feeInvoiceNumber => $feeInvoiceId) {
        $this->assertDatabaseHas('invoices', [
            'id' => $feeInvoiceId,
            'invoice_number' => $feeInvoiceNumber,
            'document_type' => 'dunning_fee',
            'status' => 'sent',
            'original_invoice_id' => $invoice->id,
        ]);
    }

    // `invoice_dunnings` bleiben ebenfalls unangetastet.
    $this->assertDatabaseCount('invoice_dunnings', 2);

    // D1-Regression im echten Storno-nach-Mahnung-Flow: `cancellationInvoice()`
    // liefert weiterhin ausschließlich die echte Stornorechnung, nicht eines
    // der beiden gleichzeitig existierenden Gebührendokumente mit
    // ebenfalls gesetztem `original_invoice_id`.
    $detailResponse = $this->actingAs($this->admin)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertOk();

    $detailResponse->assertJsonPath('data.cancellationInvoiceId', $cancellationInvoiceId)
        ->assertJsonCount(2, 'data.dunnings');

    expect($feeInvoiceIdsBefore->values()->all())->not->toContain($cancellationInvoiceId);
});

it('verbietet das stornieren eines mahngebühren-dokuments selbst', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'total_amount' => 80.00,
    ]);

    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    $feeInvoice = Invoice::query()
        ->where('original_invoice_id', $invoice->id)
        ->where('document_type', 'dunning_fee')
        ->firstOrFail();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$feeInvoice->id}/cancel")
        ->assertForbidden();

    expect($feeInvoice->fresh()->status)->toBe('sent');
});
