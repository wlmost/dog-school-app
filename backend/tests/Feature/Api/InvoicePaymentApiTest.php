<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'payment');

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create([
        'user_id' => $this->customerUser->id,
        'trainer_id' => $this->trainer->id,
    ]);
    $this->invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'total_amount' => 200.00,
        'status' => 'sent',
    ]);
});

it('lehnt eine zahlung für eine draft-rechnung mit 422 ab', function () {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
        'status' => 'draft',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 50.00,
            'paymentMethod' => 'cash',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Für diese Rechnung können aktuell keine Zahlungen erfasst werden.');
});

it('lehnt eine zahlung für eine cancelled-rechnung mit 422 ab', function () {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
        'status' => 'cancelled',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 50.00,
            'paymentMethod' => 'cash',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Für diese Rechnung können aktuell keine Zahlungen erfasst werden.');
});

it('lehnt eine zahlung über dem restbetrag mit 422 und formatiertem betrag in der nachricht ab', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $this->invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 250.00,
            'paymentMethod' => 'cash',
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Der Zahlungsbetrag übersteigt den offenen Restbetrag von 200,00 €.'
        );
});

it('akzeptiert eine zahlung exakt in höhe des restbetrags und setzt den status auf paid', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $this->invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 200.00,
            'paymentMethod' => 'bank_transfer',
            'status' => 'completed',
        ])
        ->assertCreated();

    $invoice = $this->invoice->fresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date)->not->toBeNull();
});

it('lehnt eine zahlung ab, die den restbetrag nur um einen cent übersteigt', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $this->invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 200.01,
            'paymentMethod' => 'cash',
        ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Der Zahlungsbetrag übersteigt den offenen Restbetrag von 200,00 €.'
        );

    $this->assertDatabaseMissing('payments', [
        'invoice_id' => $this->invoice->id,
        'amount' => 200.01,
    ]);
});

it('akzeptiert eine zahlung für eine gemahnte (reminded) rechnung', function () {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
        'status' => 'reminded',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 100.00,
            'paymentMethod' => 'cash',
            'status' => 'completed',
        ])
        ->assertCreated();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('akzeptiert eine zahlung für eine überfällige (overdue) rechnung', function () {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
        'status' => 'overdue',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 60.00,
            'paymentMethod' => 'cash',
            'status' => 'completed',
        ])
        ->assertCreated();

    $refreshed = $invoice->fresh();
    expect($refreshed->status)->toBe('overdue');
    expect((float) $refreshed->remaining_balance)->toBe(40.0);
});

it('weist eine zahlung eines trainers für die rechnung eines fremden kunden mit 403 zurück', function () {
    $foreignCustomer = Customer::factory()->create();
    $foreignInvoice = Invoice::factory()->create([
        'customer_id' => $foreignCustomer->id,
        'total_amount' => 100.00,
        'status' => 'sent',
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $foreignInvoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 50.00,
            'paymentMethod' => 'cash',
        ])
        ->assertForbidden();
});

it('erlaubt einem trainer eine zahlung für einen eigenen zugewiesenen kunden zu erfassen', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $this->invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 100.00,
            'paymentMethod' => 'cash',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $this->invoice->id,
        'amount' => 100.00,
    ]);
});

it('erlaubt einem admin eine zahlung für jede rechnung zu erfassen', function () {
    $foreignCustomer = Customer::factory()->create();
    $foreignInvoice = Invoice::factory()->create([
        'customer_id' => $foreignCustomer->id,
        'total_amount' => 100.00,
        'status' => 'sent',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $foreignInvoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 100.00,
            'paymentMethod' => 'cash',
        ])
        ->assertCreated();
});

it('setzt den rechnungsstatus über den service auf paid wenn ein paypal-webhook eine zahlung abschließt', function () {
    $payment = Payment::factory()->pending()->create([
        'invoice_id' => $this->invoice->id,
        'amount' => 200.00,
        'payment_method' => 'paypal',
        'transaction_id' => 'CAPTURE-XYZ',
    ]);

    // Note: this route is registered with a duplicated `/api` prefix
    // (`routes/api.php` already runs under the framework's `api/` prefix,
    // and the route definition itself additionally hardcodes `/api/v1/...`)
    // — a pre-existing quirk, not something to fix as part of this task.
    $this->postJson('/api/api/v1/payments/paypal/webhook', [
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAPTURE-XYZ'],
    ])->assertOk();

    expect($payment->fresh()->status)->toBe('completed');

    $invoice = $this->invoice->fresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date)->not->toBeNull();
});
