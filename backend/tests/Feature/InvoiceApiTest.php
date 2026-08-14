<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceDunning;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

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
    // T06: customers only see non-draft invoices, so use an explicit status.
    Invoice::factory()->count(2)->create(['customer_id' => $this->customer->id, 'status' => 'sent']);
    Invoice::factory()->count(3)->create(); // Other invoices

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices?customerId='.$this->customer->id)
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
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);
});

test('customer can view their own invoice', function () {
    // T06: customers only see non-draft invoices, so use an explicit status.
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id, 'status' => 'sent']);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);
});

test('customer cannot view other customers invoice', function () {
    $otherInvoice = Invoice::factory()->create();

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/'.$otherInvoice->id)
        ->assertForbidden();
});

test('customer cannot view their own draft invoice', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id, 'status' => 'draft']);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertForbidden();
});

test('customer cannot view their own cancelled invoice', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id, 'status' => 'cancelled']);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertForbidden();
});

test('customer does not see draft or cancelled invoices in the list', function () {
    Invoice::factory()->create(['customer_id' => $this->customer->id, 'status' => 'draft']);
    Invoice::factory()->create(['customer_id' => $this->customer->id, 'status' => 'cancelled']);
    Invoice::factory()->create(['customer_id' => $this->customer->id, 'status' => 'sent']);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices?customerId='.$this->customer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('sent');
});

test('admin and trainer still see invoices of every status', function () {
    $trainerCustomer = Customer::factory()->create(['trainer_id' => $this->trainer->id]);

    foreach (['draft', 'sent', 'paid', 'overdue', 'cancelled', 'reminded'] as $status) {
        Invoice::factory()->create(['customer_id' => $trainerCustomer->id, 'status' => $status]);
    }

    $adminResponse = $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices')
        ->assertOk();
    expect($adminResponse->json('data'))->toHaveCount(6);

    $trainerResponse = $this->actingAs($this->trainer)
        ->getJson('/api/v1/invoices')
        ->assertOk();
    expect($trainerResponse->json('data'))->toHaveCount(6);
});

test('customer can view and list their own overdue invoice', function () {
    // T06: InvoicePolicy::view()/InvoiceController::index() whitelist
    // 'overdue' for customers alongside 'sent'/'paid'/'reminded' (the legacy,
    // no-longer-actively-written status value, see design.md Decision D3).
    $invoice = Invoice::factory()->overdue()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id);

    $response = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices?customerId='.$this->customer->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('overdue');
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

    expect($response->json('data.invoiceNumber'))->toBeNull();
    expect($response->json('data.status'))->toBe('draft');

    $this->assertDatabaseHas('invoices', [
        'customer_id' => $this->customer->id,
        'invoice_number' => null,
        'status' => 'draft',
    ]);
});

test('status field in the request body is ignored when creating an invoice', function () {
    $data = [
        'customerId' => $this->customer->id,
        'issueDate' => now()->format('Y-m-d'),
        'dueDate' => now()->addDays(30)->format('Y-m-d'),
        'status' => 'paid',
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

    expect($response->json('data.status'))->toBe('draft');
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
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/invoices/'.$invoice->id, [
            'notes' => 'Updated notes',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.notes', 'Updated notes');

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => 'draft',
        'notes' => 'Updated notes',
    ]);
});

test('status field in update payload is ignored', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    $this->actingAs($this->trainer)
        ->putJson('/api/v1/invoices/'.$invoice->id, [
            'status' => 'sent',
            'notes' => 'Notes unrelated to status',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => 'draft',
    ]);
});

test('customer cannot update invoice', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->customerUser)
        ->putJson('/api/v1/invoices/'.$invoice->id, [
            'status' => 'paid',
        ])
        ->assertForbidden();
});

test('admin cannot update a non-draft invoice', function () {
    $invoice = Invoice::factory()->create(['status' => 'sent']);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/invoices/'.$invoice->id, [
            'notes' => 'Should not be applied',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('invoices', [
        'id' => $invoice->id,
        'notes' => 'Should not be applied',
    ]);
});

test('admin cannot delete a non-draft invoice', function () {
    $invoice = Invoice::factory()->create(['status' => 'sent']);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/invoices/'.$invoice->id)
        ->assertForbidden();

    expect(Invoice::find($invoice->id))->not->toBeNull();
});

test('admin can delete invoice without payments', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/invoices/'.$invoice->id)
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
        ->deleteJson('/api/v1/invoices/'.$invoice->id)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Rechnung kann nicht gelöscht werden, da bereits Zahlungen vorhanden sind.');
});

test('trainer cannot delete invoice', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($this->trainer)
        ->deleteJson('/api/v1/invoices/'.$invoice->id)
        ->assertForbidden();
});

test('trainer can finalize a draft invoice', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/finalize')
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath(
            'data.invoiceNumber',
            fn (?string $invoiceNumber) => (bool) preg_match('/^RE-\d{4}-\d{4}$/', (string) $invoiceNumber)
        );

    $invoice->refresh();
    expect($invoice->status)->toBe('sent');
    expect($invoice->invoice_number)->toMatch('/^RE-\d{4}-\d{4}$/');
});

test('finalizing an invoice that is not a draft returns 422 and keeps the number unchanged', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    $firstResponse = $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/finalize')
        ->assertOk();

    $assignedInvoiceNumber = $firstResponse->json('data.invoiceNumber');

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/finalize')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Nur Entwürfe können freigegeben werden.');

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => 'sent',
        'invoice_number' => $assignedInvoiceNumber,
    ]);
});

test('customer cannot finalize an invoice', function () {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/finalize')
        ->assertForbidden();
});

test('finalizing an invoice does not send an email', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create(['status' => 'draft']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/finalize')
        ->assertOk();

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

test('finalize retries invoice number generation after a unique constraint collision', function () {
    $year = now()->format('Y');
    $collidingNumber = "RE-{$year}-0001";
    $freeNumber = "RE-{$year}-0002";

    // An already assigned number that a concurrent finalize() call could
    // have handed out first, causing InvoiceNumberGenerator::generate()
    // to (incorrectly, per the documented gap in task-T03.notes.md) return
    // the same number on the next attempt too, before yielding a free one.
    Invoice::factory()->create([
        'status' => 'sent',
        'invoice_number' => $collidingNumber,
    ]);

    $draft = Invoice::factory()->create(['status' => 'draft']);

    $this->mock(InvoiceNumberGenerator::class, function ($mock) use ($collidingNumber, $freeNumber) {
        $mock->shouldReceive('generate')->once()->andReturn($collidingNumber);
        $mock->shouldReceive('generate')->once()->andReturn($freeNumber);
    });

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$draft->id.'/finalize')
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.invoiceNumber', $freeNumber);

    $this->assertDatabaseHas('invoices', [
        'id' => $draft->id,
        'status' => 'sent',
        'invoice_number' => $freeNumber,
    ]);
});

test('finalize gives up after exhausting all retry attempts on repeated collisions', function () {
    $year = now()->format('Y');
    $collidingNumber = "RE-{$year}-0001";

    Invoice::factory()->create([
        'status' => 'sent',
        'invoice_number' => $collidingNumber,
    ]);

    $draft = Invoice::factory()->create(['status' => 'draft']);

    $this->mock(InvoiceNumberGenerator::class, function ($mock) use ($collidingNumber) {
        $mock->shouldReceive('generate')->times(3)->andReturn($collidingNumber);
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$draft->id.'/finalize'))
        ->toThrow(UniqueConstraintViolationException::class);

    $draft->refresh();
    expect($draft->status)->toBe('draft');
});

test('trainer can cancel a sent invoice, creating a negated cancellation invoice', function () {
    $invoice = Invoice::factory()->create(['status' => 'sent', 'total_amount' => 150.00]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => 3,
        'unit_price' => 50.00,
        'tax_rate' => 19.00,
        'amount' => 150.00,
    ]);

    $response = $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.items.0.quantity', -3)
        // T06: InvoiceResource now exposes originalInvoiceId/-Number, which
        // T05 could not yet assert against the JSON body (see
        // task-T05.notes.md "Bekannte, dokumentierte Lücke").
        ->assertJsonPath('data.originalInvoiceId', $invoice->id)
        ->assertJsonPath('data.originalInvoiceNumber', $invoice->invoice_number);

    expect((float) $response->json('data.totalAmount'))->toBe(-150.0);
    expect((float) $response->json('data.items.0.totalPrice'))->toBe(-150.0);

    $cancellationInvoiceId = $response->json('data.id');

    $this->assertDatabaseHas('invoices', [
        'id' => $cancellationInvoiceId,
        'original_invoice_id' => $invoice->id,
        'status' => 'sent',
        'total_amount' => -150.00,
        'notes' => "Storno zu Rechnung {$invoice->invoice_number}",
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('cancelled');
    expect((float) $invoice->total_amount + (float) Invoice::find($cancellationInvoiceId)->total_amount)->toBe(0.0);
});

test('customer cannot cancel an invoice', function () {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'sent',
    ]);

    $this->actingAs($this->customerUser)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel')
        ->assertForbidden();

    $invoice->refresh();
    expect($invoice->status)->toBe('sent');
});

test('cancelling a draft invoice returns 403', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel')
        ->assertForbidden();
});

test('cancelling an already cancelled invoice returns 403', function () {
    $invoice = Invoice::factory()->create(['status' => 'cancelled']);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel')
        ->assertForbidden();
});

test('cancelling a cancellation invoice itself returns 403', function () {
    $original = Invoice::factory()->create(['status' => 'cancelled']);
    $cancellationInvoice = Invoice::factory()->create([
        'status' => 'sent',
        'original_invoice_id' => $original->id,
    ]);

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$cancellationInvoice->id.'/cancel')
        ->assertForbidden();
});

test('cancel retries invoice number generation after a unique constraint collision', function () {
    $year = now()->format('Y');
    $collidingNumber = "RE-{$year}-0001";
    $freeNumber = "RE-{$year}-0002";

    Invoice::factory()->create([
        'status' => 'sent',
        'invoice_number' => $collidingNumber,
    ]);

    $invoice = Invoice::factory()->create(['status' => 'sent', 'total_amount' => 100.00]);

    $this->mock(InvoiceNumberGenerator::class, function ($mock) use ($collidingNumber, $freeNumber) {
        $mock->shouldReceive('generate')->once()->andReturn($collidingNumber);
        $mock->shouldReceive('generate')->once()->andReturn($freeNumber);
    });

    $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.invoiceNumber', $freeNumber);

    $invoice->refresh();
    expect($invoice->status)->toBe('cancelled');
});

test('cancel gives up after exhausting all retry attempts and leaves the original invoice untouched', function () {
    $year = now()->format('Y');
    $collidingNumber = "RE-{$year}-0001";

    Invoice::factory()->create([
        'status' => 'sent',
        'invoice_number' => $collidingNumber,
    ]);

    $invoice = Invoice::factory()->create(['status' => 'sent', 'total_amount' => 100.00]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    $this->mock(InvoiceNumberGenerator::class, function ($mock) use ($collidingNumber) {
        $mock->shouldReceive('generate')->times(3)->andReturn($collidingNumber);
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel'))
        ->toThrow(UniqueConstraintViolationException::class);

    $invoice->refresh();
    expect($invoice->status)->toBe('sent');
    expect($invoice->items)->toHaveCount(1);
});

test('after cancelling, the customer sees the cancellation invoice but not the now-cancelled original, with cross-references intact', function () {
    // End-to-end regression for the full storno chain (design.md Decision
    // D5/D8): POST .../cancel must not only flip the original invoice's
    // status server-side, but the customer-facing GET endpoints must
    // reflect the resulting visibility change immediately, and both
    // resources must reference each other correctly.
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id, 'status' => 'sent']);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    $cancelResponse = $this->actingAs($this->trainer)
        ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel')
        ->assertOk();

    $cancellationInvoiceId = $cancelResponse->json('data.id');

    // (a) Stornorechnung ist für den Kunden sichtbar (Liste).
    $listResponse = $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices?customerId='.$this->customer->id)
        ->assertOk();

    $visibleIds = collect($listResponse->json('data'))->pluck('id');
    expect($visibleIds)->toContain($cancellationInvoiceId);
    // (b) Original-Rechnung (jetzt 'cancelled') ist für den Kunden NICHT
    // mehr sichtbar, weder in der Liste noch per direktem Abruf.
    expect($visibleIds)->not->toContain($invoice->id);

    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertForbidden();

    // (c) originalInvoiceNumber/cancellationInvoiceNumber sind in beiden
    // Resources korrekt verknüpft — auch aus Kundensicht auf die
    // Stornorechnung.
    $this->actingAs($this->customerUser)
        ->getJson('/api/v1/invoices/'.$cancellationInvoiceId)
        ->assertOk()
        ->assertJsonPath('data.originalInvoiceId', $invoice->id)
        ->assertJsonPath('data.originalInvoiceNumber', $invoice->invoice_number);
});

test('InvoiceResource exposes cancellation invoice fields on both sides of the relation', function () {
    $original = Invoice::factory()->create(['status' => 'cancelled']);
    $cancellationInvoice = Invoice::factory()->create([
        'status' => 'sent',
        'original_invoice_id' => $original->id,
        // add-invoice-dunning-dashboard T01: cancellationInvoice() filtert
        // seit der document_type-Diskriminator-Spalte (design.md
        // Decision D1) zusätzlich auf 'cancellation', sonst würde ein
        // Kind-Dokument ohne document_type (z. B. ein zukünftiges
        // Mahngebühren-Dokument) fälschlich als Stornorechnung erscheinen.
        'document_type' => 'cancellation',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/'.$original->id)
        ->assertOk()
        ->assertJsonPath('data.originalInvoiceId', null)
        ->assertJsonPath('data.cancellationInvoiceId', $cancellationInvoice->id)
        ->assertJsonPath('data.cancellationInvoiceNumber', $cancellationInvoice->invoice_number);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/'.$cancellationInvoice->id)
        ->assertOk()
        ->assertJsonPath('data.originalInvoiceId', $original->id)
        ->assertJsonPath('data.originalInvoiceNumber', $original->invoice_number)
        ->assertJsonPath('data.cancellationInvoiceId', null);
});

test('InvoiceResource exposes dunningLevel and remindedAt derived from invoice_dunnings', function () {
    $invoice = Invoice::factory()->create(['status' => 'reminded']);
    InvoiceDunning::factory()->create([
        'invoice_id' => $invoice->id,
        'level' => 1,
        'dunning_date' => now()->subDays(10),
    ]);
    $latestDunning = InvoiceDunning::factory()->create([
        'invoice_id' => $invoice->id,
        'level' => 2,
        'dunning_date' => now()->subDays(2),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('data.dunningLevel', 2)
        ->assertJsonPath('data.remindedAt', $latestDunning->dunning_date->toDateString());
});

test('InvoiceResource returns null dunningLevel and remindedAt when no dunning exists', function () {
    $invoice = Invoice::factory()->create(['status' => 'sent']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('data.dunningLevel', null)
        ->assertJsonPath('data.remindedAt', null);
});
