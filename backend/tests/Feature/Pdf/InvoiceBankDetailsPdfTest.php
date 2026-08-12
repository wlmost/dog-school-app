<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('pdf', 'invoice');

beforeEach(function () {
    $customerUser = User::factory()->customer()->create();
    $customerRecord = Customer::factory()->create(['user_id' => $customerUser->id]);

    $this->invoice = Invoice::factory()->create([
        'customer_id' => $customerRecord->id,
        'status' => 'sent',
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $this->invoice->id]);
    $this->invoice->load(['customer.user', 'items']);
});

it('rendert das rechnungs-pdf ohne php-fehler wenn keine bankdaten-settings existieren', function () {
    $html = view('pdf.invoice', ['invoice' => $this->invoice])->render();

    expect($html)->toContain('Zahlungsziel:');
    expect($html)->toContain('Bitte überweisen Sie den Betrag innerhalb von 2 Wochen auf folgendes Konto:');
});

it('zeigt die konfigurierten bankdaten und das zahlungsziel im überweisungstext', function () {
    Setting::set('company_bank_account_holder', 'Hundeschule Beispiel', 'string', group: 'company');
    Setting::set('company_bank_name', 'Musterbank', 'string', group: 'company');
    Setting::set('company_bank_iban', 'DE89370400440532013000', 'string', group: 'company');
    Setting::set('company_bank_bic', 'COBADEFFXXX', 'string', group: 'company');
    Setting::set('company_payment_term_weeks', 4, 'integer', group: 'company');

    $html = view('pdf.invoice', ['invoice' => $this->invoice])->render();

    expect($html)->toContain('Bitte überweisen Sie den Betrag innerhalb von 4 Wochen auf folgendes Konto:');
    expect($html)->toContain('Hundeschule Beispiel');
    expect($html)->toContain('Musterbank');
    expect($html)->toContain('DE89370400440532013000');
    expect($html)->toContain('COBADEFFXXX');
});

it('enthält weiterhin die zahlungsziel-zeile mit dem fälligkeitsdatum zusätzlich zum überweisungstext', function () {
    $html = view('pdf.invoice', ['invoice' => $this->invoice])->render();

    expect($html)->toContain('<strong>Zahlungsziel:</strong> '.$this->invoice->due_date->format('d.m.Y'));
    expect($html)->toContain('Bitte überweisen Sie den Betrag innerhalb von');
});

it('enthält nicht mehr die alte hartkodierte platzhalter-iban und bic', function () {
    $html = view('pdf.invoice', ['invoice' => $this->invoice])->render();

    expect($html)->not->toContain('DE89 3704 0044 0532 0130 00');
    expect($html)->not->toContain('<strong>BIC:</strong> COBADEFFXXX');
});

it('zeigt firmenname firmenadresse und ust-idnr aus den einstellungen statt hartkodierter platzhalterwerte', function () {
    Setting::set('company_name', 'Hundeschule Testfall', 'string', group: 'company');
    Setting::set('company_street', 'Teststraße 42', 'string', group: 'company');
    Setting::set('company_zip', '99999', 'string', group: 'company');
    Setting::set('company_city', 'Teststadt', 'string', group: 'company');
    Setting::set('company_tax_id', 'DE999999999', 'string', group: 'company');

    $html = view('pdf.invoice', ['invoice' => $this->invoice])->render();

    expect($html)->toContain('Hundeschule Testfall');
    expect($html)->toContain('Teststraße 42');
    expect($html)->toContain('99999 Teststadt');
    expect($html)->toContain('USt-IdNr: DE999999999');
    expect($html)->not->toContain('Hundeschule Max Mustermann');
    expect($html)->not->toContain('Musterstraße 123');
    expect($html)->not->toContain('DE123456789');
});
