<?php

declare(strict_types=1);

use App\Mail\InvoiceDunningNotice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('api', 'invoice');

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);

    $this->remindableInvoice = function (string $status = 'sent') {
        return Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => $status,
            'total_amount' => 123.45,
        ]);
    };
});

it('lässt einen admin eine mahnung auslösen und wechselt den status auf reminded', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertOk();

    $response->assertJsonPath('data.status', 'reminded')
        ->assertJsonPath('data.dunningLevel', 1);

    expect($invoice->fresh()->status)->toBe('reminded');

    Mail::assertSent(
        InvoiceDunningNotice::class,
        fn ($mail) => $mail->hasTo($this->customerUser->email)
    );
});

it('lässt einen trainer eine mahnung für gemahnte und überfällige rechnungen auslösen', function (string $status) {
    Mail::fake();

    $invoice = ($this->remindableInvoice)($status);

    $this->actingAs($this->trainer)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertOk();

    Mail::assertSent(InvoiceDunningNotice::class);
})->with(['reminded', 'overdue']);

it('verbietet einem kunden das auslösen einer mahnung', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');

    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertStatus(403);

    Mail::assertNothingSent();
    expect($invoice->fresh()->status)->toBe('sent');
});

it('lehnt die mahnung mit 422 ab wenn der rechnungsstatus dies nicht erlaubt', function (string $status) {
    Mail::fake();

    $invoice = ($this->remindableInvoice)($status);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'nicht gemahnt werden'));

    Mail::assertNothingSent();
    $this->assertDatabaseCount('invoice_dunnings', 0);
})->with(['draft', 'paid', 'cancelled']);

it('lehnt die vierte mahnung nach erreichter maximalstufe mit 422 und spezifischer nachricht ab', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');

    // Drei Mahnungen bis zur Maximalstufe auslösen.
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    Mail::fake();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Für diese Rechnung wurde bereits die maximale Mahnstufe 3 erreicht.',
        ]);

    Mail::assertNothingSent();
    $this->assertDatabaseCount('invoice_dunnings', 3);
});

it('lässt total_amount der original-rechnung nach dem auslösen einer mahnung unverändert', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');
    $totalAmountBefore = $invoice->total_amount;

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertOk();

    expect($invoice->fresh()->total_amount)->toEqual($totalAmountBefore);
});

it('liefert die nächste mahnstufe und den zugehörigen gebührenbetrag korrekt in der antwort', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertOk();

    $response->assertJsonPath('data.nextDunningLevel', 2)
        ->assertJsonPath('data.nextDunningFeeAmount', 10);
});

it('liefert null für die nächste mahnstufe und den gebührenbetrag wenn stufe 3 bereits erreicht ist', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');

    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertOk();

    $response->assertJsonPath('data.nextDunningLevel', null)
        ->assertJsonPath('data.nextDunningFeeAmount', null);
});

it('liefert die vollständige mahnhistorie inklusive gebührenrechnungsnummer in der antwort', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');

    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertOk();

    $response->assertJsonCount(2, 'data.dunnings')
        ->assertJsonPath('data.dunnings.0.level', 1)
        ->assertJsonPath('data.dunnings.1.level', 2);

    $dunnings = $response->json('data.dunnings');

    $feeInvoiceNumbers = Invoice::query()
        ->where('original_invoice_id', $invoice->id)
        ->where('document_type', 'dunning_fee')
        ->orderBy('id')
        ->pluck('invoice_number');

    expect($dunnings[0]['feeInvoiceNumber'])->toBe($feeInvoiceNumbers[0])
        ->and($dunnings[1]['feeInvoiceNumber'])->toBe($feeInvoiceNumbers[1]);
});

it('erzeugt bei jeder mahnung ein eigenständiges gebührendokument als kind-rechnung', function () {
    Mail::fake();

    $invoice = ($this->remindableInvoice)('sent');

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertOk();

    $this->assertDatabaseHas('invoices', [
        'original_invoice_id' => $invoice->id,
        'document_type' => 'dunning_fee',
        'total_amount' => 5.00,
    ]);
});
