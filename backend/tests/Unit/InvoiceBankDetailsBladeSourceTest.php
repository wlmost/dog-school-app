<?php

declare(strict_types=1);

uses()->group('unit', 'invoice');

/*
 * Reine Dateisystem-Prüfung der Blade-Quelltexte für "add-invoice-bank-details"
 * T03: Weder das Rechnungs-PDF-Template noch das Rechnungs-E-Mail-Template
 * dürfen die alte Platzhalter-IBAN/BIC noch als hartkodierten String enthalten.
 * Bewusst OHNE Laravel/RefreshDatabase (kein DB-Bezug), analog zum Muster in
 * tests/Unit/Deployment/HtaccessTemplatesTest.php.
 */

beforeEach(function () {
    $this->backendRoot = dirname(__DIR__, 2);
});

it('enthält im pdf-template nicht mehr die hartkodierte platzhalter-iban oder bic', function () {
    $source = file_get_contents($this->backendRoot.'/resources/views/pdf/invoice.blade.php');

    expect($source)->not->toContain('DE89 3704 0044 0532 0130 00');
    expect($source)->not->toContain('COBADEFFXXX');
});

it('lädt im pdf-template die neuen bankdaten- und zahlungsziel-settings', function () {
    $source = file_get_contents($this->backendRoot.'/resources/views/pdf/invoice.blade.php');

    expect($source)->toContain("Setting::get('company_bank_account_holder'");
    expect($source)->toContain("Setting::get('company_bank_name'");
    expect($source)->toContain("Setting::get('company_bank_iban'");
    expect($source)->toContain("Setting::get('company_bank_bic'");
    expect($source)->toContain("Setting::get('company_payment_term_weeks'");
});

it('enthält im rechnungs-mail-template nicht mehr die hartkodierte platzhalter-iban oder bic', function () {
    $source = file_get_contents($this->backendRoot.'/resources/views/emails/invoice-sent.blade.php');

    expect($source)->not->toContain('DE89 3704 0044 0532 0130 00');
    expect($source)->not->toContain('COBADEFFXXX');
});

it('nutzt im rechnungs-mail-template die neuen bankdaten-settings aus dem settings-array', function () {
    $source = file_get_contents($this->backendRoot.'/resources/views/emails/invoice-sent.blade.php');

    expect($source)->toContain("\$settings['company_bank_account_holder']");
    expect($source)->toContain("\$settings['company_bank_name']");
    expect($source)->toContain("\$settings['company_bank_iban']");
    expect($source)->toContain("\$settings['company_bank_bic']");
    expect($source)->toContain("\$settings['company_payment_term_weeks']");
});
