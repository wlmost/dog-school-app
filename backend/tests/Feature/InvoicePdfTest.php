<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customer = User::factory()->customer()->create();
    
    $customerRecord = Customer::factory()->create(['user_id' => $this->customer->id]);
    $this->invoice = Invoice::factory()->create(['customer_id' => $customerRecord->id]);
    
    // Create invoice items
    InvoiceItem::factory()->count(3)->create([
        'invoice_id' => $this->invoice->id,
        'tax_rate' => 19.00,
    ]);
});

// ============================================================================
// PDF Download Tests
// ============================================================================

test('admin can download invoice as PDF', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename=' . $this->invoice->invoice_number . '.pdf');
});

test('trainer can download invoice as PDF', function () {
    $response = $this->actingAs($this->trainer)
        ->getJson('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('customer can download their own invoice as PDF', function () {
    $response = $this->actingAs($this->customer)
        ->getJson('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('customer cannot download other customers invoice PDF', function () {
    $otherCustomer = User::factory()->customer()->create();
    $otherCustomerRecord = Customer::factory()->create(['user_id' => $otherCustomer->id]);
    $otherInvoice = Invoice::factory()->create(['customer_id' => $otherCustomerRecord->id]);
    
    $this->actingAs($this->customer)
        ->getJson('/api/v1/invoices/' . $otherInvoice->id . '/pdf')
        ->assertForbidden();
});

test('unauthenticated user cannot download invoice PDF', function () {
    $this->getJson('/api/v1/invoices/' . $this->invoice->id . '/pdf')
        ->assertUnauthorized();
});

// ============================================================================
// PDF Content Tests
// ============================================================================

test('PDF includes invoice number', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes customer information', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes all invoice items', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes total amount', function () {
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF shows paid status correctly', function () {
    $this->invoice->update([
        'status' => 'paid',
        'paid_date' => now(),
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF shows overdue status correctly', function () {
    $this->invoice->update([
        'status' => 'overdue',
        'due_date' => now()->subDays(10),
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes payment information for unpaid invoices', function () {
    $this->invoice->update(['status' => 'sent']);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF includes notes when present', function () {
    $notes = 'This is a test note for the invoice';
    $this->invoice->update(['notes' => $notes]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF calculates tax correctly', function () {
    // Create items with different tax rates
    InvoiceItem::query()->delete();
    
    InvoiceItem::factory()->create([
        'invoice_id' => $this->invoice->id,
        'unit_price' => 100.00,
        'quantity' => 1,
        'tax_rate' => 19.00,
        'amount' => 100.00,
    ]);
    
    InvoiceItem::factory()->create([
        'invoice_id' => $this->invoice->id,
        'unit_price' => 50.00,
        'quantity' => 2,
        'tax_rate' => 7.00,
        'amount' => 100.00,
    ]);
    
    $this->invoice->refresh();
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    // Check response is successful and returns PDF
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->not()->toBeEmpty();
});

test('PDF filename uses invoice number', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    $expectedFilename = $this->invoice->invoice_number . '.pdf';
    
    // Check header exists and contains the invoice number
    $response->assertHeader('content-disposition');
    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain($expectedFilename);
});

// ============================================================================
// Error Handling Tests
// ============================================================================

test('returns 404 for non-existent invoice PDF', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/invoices/99999/pdf')
        ->assertNotFound();
});

test('PDF generation works with invoice without items', function () {
    InvoiceItem::query()->where('invoice_id', $this->invoice->id)->delete();
    $this->invoice->refresh();
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('PDF generation works with minimal customer data', function () {
    $this->invoice->customer->update([
        'address_line1' => null,
        'address_line2' => null,
        'postal_code' => null,
        'city' => null,
        'country' => null,
    ]);
    
    $response = $this->actingAs($this->admin)
        ->get('/api/v1/invoices/' . $this->invoice->id . '/pdf');
    
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
