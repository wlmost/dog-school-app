<?php

declare(strict_types=1);

use App\Mail\InvoiceSent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('feature', 'invoice');

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

it('rendert die rechnungs-mail ohne php-fehler wenn keine bankdaten-settings existieren', function () {
    $mail = new InvoiceSent($this->invoice);

    $mail->assertSeeInHtml('Bitte überweisen Sie den Betrag innerhalb von 2 Wochen auf folgendes Konto:', false);
});

it('zeigt die konfigurierten bankdaten und das zahlungsziel im überweisungstext der rechnungs-mail', function () {
    Setting::set('company_bank_account_holder', 'Hundeschule Beispiel', 'string', group: 'company');
    Setting::set('company_bank_name', 'Musterbank', 'string', group: 'company');
    Setting::set('company_bank_iban', 'DE89370400440532013000', 'string', group: 'company');
    Setting::set('company_bank_bic', 'COBADEFFXXX', 'string', group: 'company');
    Setting::set('company_payment_term_weeks', 4, 'integer', group: 'company');

    $mail = new InvoiceSent($this->invoice);

    $mail->assertSeeInHtml('Bitte überweisen Sie den Betrag innerhalb von 4 Wochen auf folgendes Konto:', false)
        ->assertSeeInHtml('Hundeschule Beispiel')
        ->assertSeeInHtml('Musterbank')
        ->assertSeeInHtml('DE89370400440532013000')
        ->assertSeeInHtml('COBADEFFXXX');
});

it('enthält weiterhin die zahlungsziel-zeile mit dem fälligkeitsdatum zusätzlich zum überweisungstext', function () {
    $mail = new InvoiceSent($this->invoice);

    $mail->assertSeeInHtml('Zahlungsziel:')
        ->assertSeeInHtml($this->invoice->due_date->format('d.m.Y'))
        ->assertSeeInHtml('Bitte überweisen Sie den Betrag innerhalb von', false);
});

it('enthält nicht mehr die alte hartkodierte platzhalter-iban und bic in der rechnungs-mail', function () {
    $mail = new InvoiceSent($this->invoice);

    $mail->assertDontSeeInHtml('DE89 3704 0044 0532 0130 00')
        ->assertDontSeeInHtml('COBADEFFXXX');
});

it('hängt das rechnungs-pdf als anhang an', function () {
    $mail = new InvoiceSent($this->invoice);

    $attachments = $mail->attachments();

    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->mime)->toBe('application/pdf')
        ->and($attachments[0]->as)->toBe($this->invoice->invoice_number.'.pdf');
});
