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
});

test('admin can list all invoices', function () {
    Invoice::factory()->count(5)->create();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'customer',
                    'invoiceNumber',
                    'status',
                    'totalAmount',
                    'issueDate',
                    'dueDate',
                    'items',
                    'payments',
                ],
            ],
        ]);
});

test('customer can list their own invoices', function () {
    Invoice::factory()->count(2)->create(['customer_id' => $this->customer->id]);
    Invoice::factory()->count(3)->create(); // Other invoices

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices?customerId=' . $this->customer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('invoices can be filtered by status', function () {
    Invoice::factory()->count(2)->create(['status' => 'paid']);
    Invoice::factory()->count(3)->create(['status' => 'sent']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices?status=paid')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can get unpaid invoices', function () {
    Invoice::factory()->count(2)->create(['status' => 'sent']);
    Invoice::factory()->count(3)->create(['status' => 'paid']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices?unpaidOnly=true')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

test('can get overdue invoices', function () {
    // Overdue
    Invoice::factory()->count(3)->create([
        'status' => 'sent',
        'due_date' => now()->subDays(10),
    ]);
    
    // Not overdue
    Invoice::factory()->count(2)->create([
        'status' => 'sent',
        'due_date' => now()->addDays(30),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/overdue/list')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('trainer can view any invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->trainer)
        ->getJson('/api/v1/invoices/' . $invoice->id)
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);
});

test('customer can view their own invoice', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/' . $invoice->id)
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);
});

test('customer cannot view other customers invoice', function () {
    $otherInvoice = Invoice::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/' . $otherInvoice->id)
        ->assertForbidden();
});

test('trainer can create invoice', function () {
    $data = [
        'customerId' => $this->customer->id,
        'issueDate' => now()->format('Y-m-d'),
        'dueDate' => now()->addDays(30)->format('Y-m-d'),
        'notes' => 'Test invoice',
        'items' => [
            [
                'description' => 'Training Session',
                'quantity' => 2,
                'unitPrice' => 50.00,
                'taxRate' => 19,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices', $data)
        ->assertCreated();

    expect($response->json('data.invoiceNumber'))->toStartWith('RE-');
    expect($response->json('data.status'))->toBe('draft');

    $this->assertDatabaseHas('invoices', [
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);
});

test('can create invoice with items', function () {
    $data = [
        'customerId' => $this->customer->id,
        'invoiceNumber' => 'INV-2026-002',
        'totalAmount' => 300.00,
        'issueDate' => now()->format('Y-m-d'),
        'dueDate' => now()->addDays(30)->format('Y-m-d'),
        'items' => [
            [
                'description' => 'Training Session',
                'quantity' => 2,
                'unitPrice' => 50.00,
            ],
            [
                'description' => 'Group Course',
                'quantity' => 1,
                'unitPrice' => 200.00,
            ],
        ],
    ];

    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices', $data)
        ->assertCreated();

    $invoiceId = $response->json('data.id');
    
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoiceId,
        'description' => 'Training Session',
        'quantity' => 2,
        'unit_price' => 50.00,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $invoiceId,
        'description' => 'Group Course',
        'quantity' => 1,
        'unit_price' => 200.00,
    ]);
});

test('customer cannot create invoice', function () {
    $data = [
        'customerId' => $this->customer->id,
        'invoiceNumber' => 'INV-TEST',
        'totalAmount' => 100.00,
        'issueDate' => now()->format('Y-m-d'),
        'dueDate' => now()->addDays(30)->format('Y-m-d'),
    ];

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/invoices', $data)
        ->assertForbidden();
});

test('invoice creation validates required fields', function () {
    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customerId', 'issueDate', 'dueDate', 'items']);
});

test('items field must contain at least one item', function () {
    $data = [
        'customerId' => $this->customer->id,
        'issueDate' => now()->format('Y-m-d'),
        'dueDate' => now()->addDays(30)->format('Y-m-d'),
        'items' => [],
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('due date must be after or equal to issue date', function () {
    $data = [
        'customerId' => $this->customer->id,
        'invoiceNumber' => 'INV-TEST',
        'totalAmount' => 100.00,
        'issueDate' => now()->format('Y-m-d'),
        'dueDate' => now()->subDays(5)->format('Y-m-d'),
    ];

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['dueDate']);
});

test('trainer can update invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/invoices/' . $invoice->id, [
            'status' => 'sent',
            'notes' => 'Updated notes',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.notes', 'Updated notes');

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => 'sent',
        'notes' => 'Updated notes',
    ]);
});

test('customer cannot update invoice', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/invoices/' . $invoice->id, [
            'status' => 'paid',
        ])
        ->assertForbidden();
});

test('trainer can mark invoice as paid', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'sent',
        'paid_date' => null,
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/' . $invoice->id . '/mark-paid')
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date)->not->toBeNull();
});

test('cannot mark already paid invoice as paid', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'paid',
        'paid_date' => now(),
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/' . $invoice->id . '/mark-paid')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Rechnung ist bereits bezahlt.');
});

test('admin can delete invoice without payments', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/invoices/' . $invoice->id)
        ->assertNoContent();

    expect(Invoice::find($invoice->id))->toBeNull();
});

test('cannot delete invoice with completed payments', function () {
    $invoice = Invoice::factory()->create();
    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'status' => 'completed',
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/invoices/' . $invoice->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Rechnung kann nicht gelöscht werden, da bereits Zahlungen vorhanden sind.');
});

test('trainer cannot delete invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/invoices/' . $invoice->id)
        ->assertForbidden();
});
