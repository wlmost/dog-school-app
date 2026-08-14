<?php

declare(strict_types=1);

use App\Exceptions\InvoiceOverpaymentException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoicePaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('domain', 'payment');

beforeEach(function () {
    $customerUser = User::factory()->customer()->create();
    $customer = Customer::factory()->create(['user_id' => $customerUser->id]);

    $this->invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'sent',
        'total_amount' => 100.00,
    ]);

    $this->recorder = app(InvoicePaymentRecorder::class);
});

it('lässt den rechnungsstatus unverändert wenn nur eine teilzahlung erfasst wird', function () {
    $payment = $this->recorder->record($this->invoice, [
        'payment_date' => '2026-08-01',
        'amount' => 40.00,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    expect($payment->amount)->toBe(40.0);
    expect($this->invoice->fresh()->status)->toBe('sent');
    expect($this->invoice->fresh()->paid_date)->toBeNull();
});

it('setzt den rechnungsstatus auf paid wenn die zahlung exakt den gesamtbetrag erreicht', function () {
    $this->recorder->record($this->invoice, [
        'payment_date' => '2026-08-05',
        'amount' => 100.00,
        'payment_method' => 'bank_transfer',
        'status' => 'completed',
    ]);

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date->toDateString())->toBe('2026-08-05');
});

it('lehnt eine teilzahlung ab, wenn sie zusammen mit bereits abgeschlossenen zahlungen den gesamtbetrag übersteigt', function () {
    // Bis zum Fix des TOCTOU-Überzahlungs-Befunds (siehe
    // task-fix-overpayment-race.notes.md und die "Muss"-Feststellung in
    // task-T03.review.md) buchte record() eine solche zweite Teilzahlung
    // noch bedingungslos durch (Summe 110 € bei total_amount 100 €) und
    // setzte die Rechnung trotzdem auf `paid`. Das widersprach der
    // bindenden Anforderung "Überzahlung wird abgelehnt" und wird hier
    // jetzt als Ablehnung erwartet.
    $this->recorder->record($this->invoice, [
        'payment_date' => '2026-08-01',
        'amount' => 60.00,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    expect($this->invoice->fresh()->status)->toBe('sent');

    expect(fn () => $this->recorder->record($this->invoice, [
        'payment_date' => '2026-08-10',
        'amount' => 50.00,
        'payment_method' => 'stripe',
        'status' => 'completed',
    ]))->toThrow(InvoiceOverpaymentException::class);

    $invoice = $this->invoice->fresh();

    expect($invoice->status)->toBe('sent');
    expect($invoice->paid_date)->toBeNull();
    expect((float) $invoice->payments()->completed()->sum('amount'))->toBe(60.0);
    expect($invoice->payments()->count())->toBe(1);
});

it('lässt den rechnungsstatus unverändert wenn eine bestehende zahlung noch nicht abgeschlossen ist', function () {
    Payment::factory()->pending()->create([
        'invoice_id' => $this->invoice->id,
        'amount' => 40.00,
    ]);

    expect($this->invoice->fresh()->status)->toBe('sent');
});

it('setzt den rechnungsstatus auf paid wenn eine bestehende zahlung abgeschlossen wird und dadurch voll bezahlt ist', function () {
    $payment = Payment::factory()->pending()->create([
        'invoice_id' => $this->invoice->id,
        'payment_date' => '2026-08-07',
        'amount' => 100.00,
    ]);

    $this->recorder->completeExisting($payment);

    $invoice = $this->invoice->fresh();

    expect($payment->fresh()->status)->toBe('completed');
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date->toDateString())->toBe('2026-08-07');
});

it('aktualisiert restbetrag und status korrekt nach jeder von drei aufeinanderfolgenden teilzahlungen', function () {
    $this->recorder->record($this->invoice, [
        'payment_date' => '2026-08-01',
        'amount' => 30.00,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    $invoice = $this->invoice->fresh();
    expect($invoice->status)->toBe('sent');
    expect((float) $invoice->remaining_balance)->toBe(70.0);

    $this->recorder->record($this->invoice, [
        'payment_date' => '2026-08-05',
        'amount' => 25.00,
        'payment_method' => 'bank_transfer',
        'status' => 'completed',
    ]);

    $invoice = $this->invoice->fresh();
    expect($invoice->status)->toBe('sent');
    expect((float) $invoice->remaining_balance)->toBe(45.0);

    $this->recorder->record($this->invoice, [
        'payment_date' => '2026-08-10',
        'amount' => 45.00,
        'payment_method' => 'stripe',
        'status' => 'completed',
    ]);

    $invoice = $this->invoice->fresh();
    expect($invoice->status)->toBe('paid');
    expect((float) $invoice->remaining_balance)->toBe(0.0);
    expect($invoice->paid_date->toDateString())->toBe('2026-08-10');
});

it('bleibt idempotent wenn completeExisting für eine bereits abgeschlossene zahlung erneut aufgerufen wird', function () {
    $payment = Payment::factory()->create([
        'invoice_id' => $this->invoice->id,
        'payment_date' => '2026-08-07',
        'amount' => 100.00,
        'status' => 'completed',
    ]);

    $this->recorder->completeExisting($payment);

    $invoice = $this->invoice->fresh();
    expect($payment->fresh()->status)->toBe('completed');
    expect($invoice->status)->toBe('paid');
    expect($invoice->paid_date->toDateString())->toBe('2026-08-07');
    expect((float) $invoice->payments()->completed()->sum('amount'))->toBe(100.0);
    expect($invoice->payments()->count())->toBe(1);
});
