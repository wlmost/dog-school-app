<?php

declare(strict_types=1);

use App\Mail\PaymentReminder;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);
uses()->group('feature', 'invoice');

beforeEach(function () {
    Cache::flush();

    $customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->for($customerUser)->create();
    $this->invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'due_date' => now()->subDays(5),
        'status' => 'sent',
    ]);
});

it('rendert die zahlungserinnerung ohne die hartkodierte platzhalter-iban', function () {
    $html = (new PaymentReminder($this->invoice))->render();

    expect($html)->not->toContain('DE89 3704 0044 0532 0130 00');
});

it('rendert die zahlungserinnerung ohne die hartkodierte platzhalter-bic', function () {
    $html = (new PaymentReminder($this->invoice))->render();

    expect($html)->not->toContain('COBADEFFXXX');
});

it('zeigt die gepflegten bankdaten aus den settings in der zahlungserinnerung', function () {
    Setting::updateOrCreate(['key' => 'company_bank_account_holder'], ['value' => 'Hundeschule Wuffwuff', 'type' => 'string', 'group' => 'company']);
    Setting::updateOrCreate(['key' => 'company_bank_name'], ['value' => 'Sparkasse Musterstadt', 'type' => 'string', 'group' => 'company']);
    Setting::updateOrCreate(['key' => 'company_bank_iban'], ['value' => 'DE12500105170648489890', 'type' => 'string', 'group' => 'company']);
    Setting::updateOrCreate(['key' => 'company_bank_bic'], ['value' => 'INGDDEFFXXX', 'type' => 'string', 'group' => 'company']);
    Setting::clearCache();

    $html = (new PaymentReminder($this->invoice))->render();

    expect($html)->toContain('Hundeschule Wuffwuff')
        ->toContain('Sparkasse Musterstadt')
        ->toContain('DE12500105170648489890')
        ->toContain('INGDDEFFXXX');
});

it('behält den bestehenden einleitungssatz der zahlungserinnerung wortwörtlich unverändert bei', function () {
    $html = (new PaymentReminder($this->invoice))->render();

    expect($html)->toContain('Bitte überweisen Sie den offenen Betrag unter Angabe der Rechnungsnummer auf folgendes Konto:');
    expect($html)->not->toContain('innerhalb von');
});

it('zeigt weiterhin die rechnungsnummer als verwendungszweck in der zahlungserinnerung', function () {
    $html = (new PaymentReminder($this->invoice))->render();

    expect($html)->toContain('Verwendungszweck')
        ->toContain($this->invoice->invoice_number);
});

it('zeigt den überfälligkeits-hinweis weiterhin korrekt neben den neuen bankdaten an', function () {
    // $this->invoice hat laut beforeEach ein due_date 5 Tage in der Vergangenheit,
    // Invoice::isOverdue() muss also true liefern — Regressionscheck für die
    // unveränderte Fälligkeitslogik aus payment-reminder.blade.php:31-36.
    expect($this->invoice->isOverdue())->toBeTrue();

    $html = (new PaymentReminder($this->invoice))->render();

    expect($html)->toContain('ist seit dem '.$this->invoice->due_date->format('d.m.Y').' überfällig');
    expect($html)->toContain('Tage überfällig');
    expect($html)->toContain('Kontoinhaber');
});

it('zeigt den fälligkeits-hinweis ohne überfällig-formulierung für eine noch nicht fällige rechnung', function () {
    $futureInvoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'due_date' => now()->addDays(5),
        'status' => 'sent',
    ]);

    expect($futureInvoice->isOverdue())->toBeFalse();

    $html = (new PaymentReminder($futureInvoice))->render();

    expect($html)->toContain('ist am '.$futureInvoice->due_date->format('d.m.Y').' fällig');
    expect($html)->not->toContain('überfällig');
});

it('rendert die zahlungserinnerung ohne fehler wenn keine bankdaten-settings gepflegt sind', function () {
    Setting::query()->whereIn('key', [
        'company_bank_account_holder',
        'company_bank_name',
        'company_bank_iban',
        'company_bank_bic',
    ])->delete();
    Setting::clearCache();

    $html = (new PaymentReminder($this->invoice))->render();

    expect($html)->toBeString()->not->toBeEmpty();
});
