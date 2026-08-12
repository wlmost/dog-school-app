<?php

declare(strict_types=1);

use App\Models\AnamnesisResponse;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('pdf', 'anamnesis');

beforeEach(function () {
    $this->response = AnamnesisResponse::factory()->create();
    $this->response->load(['dog.customer.user', 'template.questions', 'completedBy', 'answers.question']);
});

it('rendert das anamnese-pdf ohne php-fehler wenn keine company-settings existieren', function () {
    $html = view('pdf.anamnesis', ['response' => $this->response])->render();

    expect($html)->toContain('<h1>Hundeschule</h1>');
});

it('zeigt firmenname firmenadresse und kontaktdaten aus den einstellungen im kopf des anamnese-pdfs', function () {
    Setting::set('company_name', 'Hundeschule Testfall', 'string', group: 'company');
    Setting::set('company_street', 'Teststraße 42', 'string', group: 'company');
    Setting::set('company_zip', '99999', 'string', group: 'company');
    Setting::set('company_city', 'Teststadt', 'string', group: 'company');
    Setting::set('company_phone', '+49 111 222333', 'string', group: 'company');
    Setting::set('company_email', 'info@testfall.de', 'string', group: 'company');

    $html = view('pdf.anamnesis', ['response' => $this->response])->render();

    expect($html)->toContain('<h1>Hundeschule Testfall</h1>');
    expect($html)->toContain('Teststraße 42 • 99999 Teststadt');
    expect($html)->toContain('Tel: +49 111 222333 • E-Mail: info@testfall.de');
});

it('zeigt firmenname firmenadresse und ust-idnr aus den einstellungen im fuß des anamnese-pdfs', function () {
    Setting::set('company_name', 'Hundeschule Testfall', 'string', group: 'company');
    Setting::set('company_street', 'Teststraße 42', 'string', group: 'company');
    Setting::set('company_zip', '99999', 'string', group: 'company');
    Setting::set('company_city', 'Teststadt', 'string', group: 'company');
    Setting::set('company_tax_id', 'DE999999999', 'string', group: 'company');

    $html = view('pdf.anamnesis', ['response' => $this->response])->render();

    expect($html)->toContain('Hundeschule Testfall • Teststraße 42 • 99999 Teststadt');
    expect($html)->toContain('USt-IdNr: DE999999999');
});

it('enthält nicht mehr die alten hartkodierten platzhalterwerte für firmenname adresse und ust-idnr', function () {
    Setting::set('company_name', 'Hundeschule Testfall', 'string', group: 'company');
    Setting::set('company_street', 'Teststraße 42', 'string', group: 'company');
    Setting::set('company_zip', '99999', 'string', group: 'company');
    Setting::set('company_city', 'Teststadt', 'string', group: 'company');
    Setting::set('company_phone', '+49 111 222333', 'string', group: 'company');
    Setting::set('company_email', 'info@testfall.de', 'string', group: 'company');
    Setting::set('company_tax_id', 'DE999999999', 'string', group: 'company');

    $html = view('pdf.anamnesis', ['response' => $this->response])->render();

    expect($html)->not->toContain('Hundeschule Max Mustermann');
    expect($html)->not->toContain('Musterstraße 123');
    expect($html)->not->toContain('12345 Musterstadt');
    expect($html)->not->toContain('hundeschule-mustermann.de');
    expect($html)->not->toContain('DE123456789');
});

it('zeigt die erstellt-am-zeile weiterhin nach den firmenzeilen im fuß', function () {
    Setting::set('company_name', 'Hundeschule Testfall', 'string', group: 'company');
    Setting::set('company_tax_id', 'DE999999999', 'string', group: 'company');

    $html = view('pdf.anamnesis', ['response' => $this->response])->render();

    $taxIdPosition = strpos($html, 'USt-IdNr: DE999999999');
    $createdAtPosition = strpos($html, 'Erstellt am:');

    expect($taxIdPosition)->not->toBeFalse();
    expect($createdAtPosition)->not->toBeFalse();
    expect($createdAtPosition)->toBeGreaterThan($taxIdPosition);
});
