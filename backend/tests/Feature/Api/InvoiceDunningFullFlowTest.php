<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('api', 'invoice');

// Holistischer End-to-End-Test über den vollständigen Mahn-Flow (Stufe
// 1 -> 2 -> 3 -> abgelehnte 4. Mahnung), im Unterschied zu den bereits in
// `InvoiceDunningApiTest.php` vorhandenen Tests, die jeweils nur isolierte
// Ausschnitte prüfen (z. B. Stufe 1 -> 2 oder direkt drei Trigger gefolgt
// vom abgelehnten vierten). Dieser Test verifiziert zusätzlich, dass die
// vollständige 3-stufige Mahnhistorie sowohl in der Detailansicht
// (`GET /invoices/{id}`, T04/T08-Vorbild) als auch im Dashboard-Widget
// (`GET /dashboard`, T06/T09-Vorbild) konsistent mit korrektem
// `dunningLevel`/`status` ankommt — keine der bestehenden Testdateien
// prüft `dunningLevel === 3` an irgendeiner Stelle.

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
});

it('durchläuft alle drei mahnstufen und spiegelt die vollständige historie in detailansicht und dashboard wider', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'total_amount' => 300.00,
        'due_date' => now()->addDays(5),
    ]);

    // Stufe 1 -> 2 -> 3, jede Mahnung erfolgreich.
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    // Vierter Trigger-Versuch nach Level 3 wird abgelehnt (422), erzeugt
    // keine weitere Mahnung und verschickt keine weitere E-Mail.
    Mail::fake();
    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertStatus(422);
    Mail::assertNothingSent();

    // Genau 3 Mahn-Datensätze und 3 eigenständige Gebührendokumente,
    // total_amount der Original-Rechnung bleibt über alle drei Stufen
    // hinweg unverändert (bindende Entscheidung 1).
    $this->assertDatabaseCount('invoice_dunnings', 3);
    $this->assertDatabaseCount('invoices', 4); // Original + 3 Gebührendokumente
    expect($invoice->fresh()->total_amount)->toEqual('300.00');

    // Detailansicht (T04/T08-Vorbild): vollständige 3-stufige Mahnhistorie,
    // maximale Stufe erreicht (nextDunningLevel/-FeeAmount === null).
    $detailResponse = $this->actingAs($this->admin)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertOk();

    $detailResponse->assertJsonPath('data.status', 'reminded')
        ->assertJsonPath('data.dunningLevel', 3)
        ->assertJsonPath('data.nextDunningLevel', null)
        ->assertJsonPath('data.nextDunningFeeAmount', null)
        ->assertJsonCount(3, 'data.dunnings')
        ->assertJsonPath('data.dunnings.0.level', 1)
        ->assertJsonPath('data.dunnings.1.level', 2)
        ->assertJsonPath('data.dunnings.2.level', 3);

    // `toEqual()` statt `toBe()`, weil PHPs `json_encode()` einen
    // ganzzahligen `float` (z. B. `5.0`) standardmäßig ohne
    // `JSON_PRESERVE_ZERO_FRACTION` als `5` serialisiert — nach dem
    // JSON-Roundtrip käme hier ein `int` an, kein `float` (siehe
    // `task-T04.notes.md`, dieselbe Beobachtung für
    // `nextDunningFeeAmount`). `toEqual()` vergleicht ohne Typzwang.
    $feeAmounts = collect($detailResponse->json('data.dunnings'))->pluck('feeAmount');
    expect($feeAmounts->all())->toEqual([5.0, 10.0, 15.0]);

    // Jedes Mahn-Level referenziert ein eigenes, tatsächlich existierendes
    // Gebührendokument (keine geteilte/fehlende feeInvoiceNumber).
    $feeInvoiceNumbers = collect($detailResponse->json('data.dunnings'))->pluck('feeInvoiceNumber');
    expect($feeInvoiceNumbers->unique())->toHaveCount(3);
    expect($feeInvoiceNumbers->filter())->toHaveCount(3);

    // Dashboard-Widget (T06/T09-Vorbild): dieselbe Rechnung taucht mit
    // dunningLevel 3 und status 'reminded' auf, unabhängig vom Fälligkeitsdatum
    // (das absichtlich in der Zukunft liegt, siehe `due_date` oben) — die
    // Aufnahme in die Liste hängt für gemahnte Rechnungen nur am Status.
    $dashboardResponse = $this->actingAs($this->admin)
        ->getJson('/api/v1/dashboard')
        ->assertOk();

    $entry = collect($dashboardResponse->json('overdueOrRemindedInvoices'))
        ->firstWhere('id', $invoice->id);

    expect($entry)->not->toBeNull()
        ->and($entry['status'])->toBe('reminded')
        ->and($entry['dunningLevel'])->toBe(3);

    // Keines der drei Gebührendokumente selbst taucht im Dashboard auf
    // (`whereNull('document_type')`-Filter aus T06).
    $feeInvoiceIds = Invoice::query()
        ->where('original_invoice_id', $invoice->id)
        ->where('document_type', 'dunning_fee')
        ->pluck('id');
    $dashboardIds = collect($dashboardResponse->json('overdueOrRemindedInvoices'))->pluck('id');
    expect($dashboardIds->intersect($feeInvoiceIds))->toBeEmpty();
});
