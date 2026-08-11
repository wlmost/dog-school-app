<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('api', 'setting');

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// ======== Bankdaten — gültige Werte werden einzeln akzeptiert ========

it('akzeptiert einen gültigen kontoinhaber', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_account_holder' => 'Hundeschule Beispiel',
        ])
        ->assertOk();
});

it('akzeptiert einen gültigen banknamen', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_name' => 'Musterbank',
        ])
        ->assertOk();
});

it('akzeptiert eine gültige iban', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_iban' => 'DE89370400440532013000',
        ])
        ->assertOk();
});

it('akzeptiert eine gültige bic', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_bic' => 'COBADEFFXXX',
        ])
        ->assertOk();
});

it('akzeptiert ein zahlungsziel im gültigen bereich', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_payment_term_weeks' => 4,
        ])
        ->assertOk();
});

// ======== Bankdaten — alle 5 Felder gemeinsam ========

it('akzeptiert alle fünf bankdaten-felder zusammen', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_account_holder' => 'Hundeschule Beispiel',
            'company_bank_name' => 'Musterbank',
            'company_bank_iban' => 'DE89370400440532013000',
            'company_bank_bic' => 'COBADEFFXXX',
            'company_payment_term_weeks' => 3,
        ])
        ->assertOk();

    $this->assertDatabaseHas('settings', [
        'key' => 'company_bank_iban',
        'value' => 'DE89370400440532013000',
    ]);
});

// ======== IBAN — ungültige Werte werden zurückgewiesen ========

it('weist eine iban mit kleinbuchstaben zurück', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_iban' => 'de89370400440532013000',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_bank_iban']);
});

it('weist eine iban mit leerzeichen zurück', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_iban' => 'DE89 3704 0044 0532 0130 00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_bank_iban']);
});

it('weist eine iban mit falschem präfix zurück', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_iban' => '1234567890',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_bank_iban']);
});

// ======== BIC — ungültige Werte werden zurückgewiesen ========

it('weist eine ungültige bic zurück', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_bank_bic' => 'invalid-bic',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_bank_bic']);
});

// ======== Zahlungsziel — Grenzwerte ========

it('weist ein zahlungsziel von null wochen zurück', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_payment_term_weeks' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_payment_term_weeks']);
});

it('weist ein zahlungsziel von 53 wochen zurück', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_payment_term_weeks' => 53,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_payment_term_weeks']);
});

it('akzeptiert ein zahlungsziel von 52 wochen als obergrenze', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_payment_term_weeks' => 52,
        ])
        ->assertOk();
});

it('akzeptiert ein zahlungsziel von einer woche als untergrenze', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_payment_term_weeks' => 1,
        ])
        ->assertOk();
});

// ======== Optionalität ========

it('verursacht keinen validierungsfehler wenn keines der bankdaten-felder gesendet wird', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_name' => 'Musterhundeschule',
        ])
        ->assertOk();
});

// ======== Typumwandlung ========

it('persistiert das zahlungsziel als integer auch wenn es als string gesendet wird', function () {
    // FormData liefert Werte immer als String (siehe design.md Decision 3),
    // daher wird hier bewusst ein String statt eines nativen int gesendet.
    $this->actingAs($this->admin)
        ->putJson('/api/v1/settings', [
            'company_payment_term_weeks' => '5',
        ])
        ->assertOk();

    $this->assertDatabaseHas('settings', [
        'key' => 'company_payment_term_weeks',
        'type' => 'integer',
    ]);

    Setting::clearCache();

    expect(Setting::get('company_payment_term_weeks'))->toBeInt();
    expect(Setting::get('company_payment_term_weeks'))->toBe(5);
});

// ======== Seeder ========

it('legt beim seeden alle fünf neuen bankdaten-keys mit defaults an', function () {
    (new SettingsSeeder)->run();

    $this->assertDatabaseHas('settings', ['key' => 'company_bank_account_holder']);
    $this->assertDatabaseHas('settings', ['key' => 'company_bank_name']);
    $this->assertDatabaseHas('settings', ['key' => 'company_bank_iban', 'value' => 'DE89370400440532013000']);
    $this->assertDatabaseHas('settings', ['key' => 'company_bank_bic', 'value' => 'COBADEFFXXX']);
    $this->assertDatabaseHas('settings', ['key' => 'company_payment_term_weeks', 'value' => '2', 'type' => 'integer']);
});
