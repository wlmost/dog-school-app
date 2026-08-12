<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\InvoiceDunning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
uses()->group('unit', 'invoice');

// Diese Datei schließt eine in `task-T01.notes.md` dokumentierte Lücke:
// die dortigen Akzeptanzkriterien zu `InvoiceDunning::create()`, der
// `dunnings()`-Relation sowie den Attributen `dunning_level`/`reminded_at`
// wurden laut Notes nur manuell per `artisan tinker` verifiziert, nicht
// durch einen automatisierten Test.

it('legt einen mahnungs-datensatz per factory an und liest ihn über die invoice()-relation zurück', function () {
    $invoice = Invoice::factory()->create();
    $dunning = InvoiceDunning::factory()->create([
        'invoice_id' => $invoice->id,
        'level' => 1,
        'dunning_date' => now()->subDays(3),
        'fee_amount' => 5.50,
    ]);

    expect($dunning->invoice->id)->toBe($invoice->id);
});

it('castet dunning_date als datum und fee_amount als dezimalzahl', function () {
    $dunning = InvoiceDunning::factory()->create([
        'dunning_date' => '2026-08-01',
        'fee_amount' => 12.5,
    ]);

    expect($dunning->dunning_date)->toBeInstanceOf(Carbon::class);
    expect($dunning->dunning_date->toDateString())->toBe('2026-08-01');
    expect($dunning->fee_amount)->toBe('12.50');
});

it('liefert alle mahnungen einer rechnung über die dunnings()-relation', function () {
    $invoice = Invoice::factory()->create();
    InvoiceDunning::factory()->count(2)->create(['invoice_id' => $invoice->id]);
    InvoiceDunning::factory()->create(); // andere Rechnung, darf nicht mitgezählt werden

    expect($invoice->dunnings)->toHaveCount(2);
});

it('liefert dunning_level als null wenn keine mahnungen existieren', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->dunning_level)->toBeNull();
});

it('liefert dunning_level als höchste vorhandene stufe', function () {
    $invoice = Invoice::factory()->create();
    InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'level' => 1]);
    InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'level' => 3]);
    InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'level' => 2]);

    expect($invoice->dunning_level)->toBe(3);
});

it('liefert reminded_at als null wenn keine mahnungen existieren', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->reminded_at)->toBeNull();
});

it('liefert reminded_at als datum der jüngsten mahnung', function () {
    $invoice = Invoice::factory()->create();
    InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'dunning_date' => now()->subDays(10)]);
    $latest = InvoiceDunning::factory()->create(['invoice_id' => $invoice->id, 'dunning_date' => now()->subDays(1)]);

    expect($invoice->reminded_at->toDateString())->toBe($latest->dunning_date->toDateString());
});
