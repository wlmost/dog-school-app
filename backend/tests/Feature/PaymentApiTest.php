<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->trainer = User::factory()->create(['role' => 'trainer']);
    $this->customerUser = User::factory()->create(['role' => 'customer']);
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
    $this->invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'total_amount' => 200.00,
        'status' => 'sent',
    ]);
});

test('admin can list all payments', function () {
    Payment::factory()->count(5)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/payments')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice',
                    'paymentDate',
                    'amount',
                    'paymentMethod',
                    'status',
                    'transactionId',
                ],
            ],
        ]);
});

test('customer can list payments for their invoices', function () {
    Payment::factory()->count(2)->create(['invoice_id' => $this->invoice->id]);
    Payment::factory()->count(3)->create(); // Other payments

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/payments?invoiceId=' . $this->invoice->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('payments can be filtered by payment method', function () {
    Payment::factory()->count(2)->create(['payment_method' => 'cash']);
    Payment::factory()->count(3)->create(['payment_method' => 'stripe']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/payments?paymentMethod=cash')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('payments can be filtered by status', function () {
    Payment::factory()->count(2)->create(['status' => 'completed']);
    Payment::factory()->count(3)->create(['status' => 'pending']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/payments?status=completed')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can get only completed payments', function () {
    Payment::factory()->count(3)->create(['status' => 'completed']);
    Payment::factory()->count(2)->create(['status' => 'pending']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/payments?completedOnly=true')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('trainer can view any payment', function () {
    $payment = Payment::factory()->create();

    $this->actingAs($this->trainer)
        ->getJson('/api/v1/payments/' . $payment->id)
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id);
});

test('customer can view payment for their invoice', function () {
    $payment = Payment::factory()->create(['invoice_id' => $this->invoice->id]);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/payments/' . $payment->id)
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id);
});

test('customer cannot view payment for other invoice', function () {
    $otherPayment = Payment::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/payments/' . $otherPayment->id)
        ->assertForbidden();
});

test('trainer can create payment', function () {
    $data = [
        'invoiceId' => $this->invoice->id,
        'paymentDate' => now()->format('Y-m-d'),
        'amount' => 100.00,
        'paymentMethod' => 'bank_transfer',
        'transactionId' => 'TXN-123456',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', $data)
        ->assertCreated()
        ->assertJsonPath('data.amount', 100)
        ->assertJsonPath('data.paymentMethod', 'bank_transfer')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $this->invoice->id,
        'amount' => 100.00,
        'payment_method' => 'bank_transfer',
        'transaction_id' => 'TXN-123456',
        'status' => 'pending',
    ]);
});

test('invoice status updates to paid when fully paid', function () {
    $invoice = Invoice::factory()->create([
        'total_amount' => 200.00,
        'status' => 'sent',
    ]);

    // First payment - partial
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 100.00,
        'status' => 'completed',
    ]);

    // Second payment - completes payment
    $data = [
        'invoiceId' => $invoice->id,
        'paymentDate' => now()->format('Y-m-d'),
        'amount' => 100.00,
        'paymentMethod' => 'cash',
        'status' => 'completed',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', $data)
        ->assertCreated();

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date)->not->toBeNull();
});

test('customer cannot create payment', function () {
    $data = [
        'invoiceId' => $this->invoice->id,
        'paymentDate' => now()->format('Y-m-d'),
        'amount' => 100.00,
        'paymentMethod' => 'cash',
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/payments', $data)
        ->assertForbidden();
});

test('payment creation validates required fields', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoiceId', 'paymentDate', 'amount', 'paymentMethod']);
});

test('payment amount must be positive', function () {
    $data = [
        'invoiceId' => $this->invoice->id,
        'paymentDate' => now()->format('Y-m-d'),
        'amount' => -50.00,
        'paymentMethod' => 'cash',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('payment date cannot be in future', function () {
    $data = [
        'invoiceId' => $this->invoice->id,
        'paymentDate' => now()->addDays(5)->format('Y-m-d'),
        'amount' => 100.00,
        'paymentMethod' => 'cash',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['paymentDate']);
});

test('payment method must be valid', function () {
    $data = [
        'invoiceId' => $this->invoice->id,
        'paymentDate' => now()->format('Y-m-d'),
        'amount' => 100.00,
        'paymentMethod' => 'invalid_method',
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['paymentMethod']);
});

test('trainer can update payment', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/payments/' . $payment->id, [
            'status' => 'completed',
            'transactionId' => 'TXN-UPDATED',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.transactionId', 'TXN-UPDATED');

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => 'completed',
        'transaction_id' => 'TXN-UPDATED',
    ]);
});

test('customer cannot update payment', function () {
    $payment = Payment::factory()->create(['invoice_id' => $this->invoice->id]);

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/payments/' . $payment->id, [
            'status' => 'completed',
        ])
        ->assertForbidden();
});

test('trainer can mark payment as completed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments/' . $payment->id . '/mark-completed')
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'status' => 'completed',
    ]);
});

test('cannot mark already completed payment', function () {
    $payment = Payment::factory()->create(['status' => 'completed']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments/' . $payment->id . '/mark-completed')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Zahlung ist bereits abgeschlossen.');
});

test('marking payment completed updates invoice status if fully paid', function () {
    $invoice = Invoice::factory()->create([
        'total_amount' => 150.00,
        'status' => 'sent',
    ]);

    $payment = Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'amount' => 150.00,
        'status' => 'pending',
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/payments/' . $payment->id . '/mark-completed')
        ->assertOk();

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date)->not->toBeNull();
});

test('admin can delete pending payment', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/payments/' . $payment->id)
        ->assertNoContent();

    expect(Payment::find($payment->id))->toBeNull();
});

test('cannot delete completed payment', function () {
    $payment = Payment::factory()->create(['status' => 'completed']);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/payments/' . $payment->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Abgeschlossene Zahlungen können nicht gelöscht werden.');
});

test('trainer cannot delete payment', function () {
    $payment = Payment::factory()->create();

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/payments/' . $payment->id)
        ->assertForbidden();
});
