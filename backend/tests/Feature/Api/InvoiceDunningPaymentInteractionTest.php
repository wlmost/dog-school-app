<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('api', 'invoice', 'payment');

// Holistischer Interaktionstest zwischen dem Mahnwesen (Change 4,
// `InvoiceDunningRecorder`) und der Zahlungserfassung (Change 3,
// `InvoicePaymentRecorder`). Die bestehende
// `InvoicePaymentApiTest.php::'akzeptiert eine zahlung für eine gemahnte
// (reminded) rechnung'` erzeugt die `reminded`-Rechnung ausschließlich
// per Factory (`status => 'reminded'` ohne echte `InvoiceDunning`-
// Datensätze) und prüft nur den Statuswechsel zu `paid` — nicht, ob eine
// tatsächlich über `InvoiceDunningRecorder::trigger()` erzeugte
// Mahnhistorie eine anschließende Zahlung unverändert übersteht. Dieser
// Test schließt genau diese Lücke.

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
});

it('behält die vollständige mahnhistorie bei einer teilzahlung auf eine gemahnte rechnung', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'total_amount' => 200.00,
    ]);

    // Zwei echte Mahnungen über den Trigger-Endpunkt (nicht nur per
    // Factory-Status), damit `invoice_dunnings` + Gebührendokumente
    // tatsächlich existieren.
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    expect($invoice->fresh()->status)->toBe('reminded');
    $this->assertDatabaseCount('invoice_dunnings', 2);

    // Teilzahlung, die den Restbetrag nicht vollständig deckt.
    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 50.00,
            'paymentMethod' => 'cash',
            'status' => 'completed',
        ])
        ->assertCreated();

    $refreshed = $invoice->fresh();

    // Status bleibt weiterhin 'reminded' (Restbetrag > 0,
    // `InvoicePaymentRecorder::syncStatus()` setzt erst bei vollständiger
    // Zahlung auf 'paid' um) und die Mahnhistorie ist unangetastet.
    expect($refreshed->status)->toBe('reminded');
    expect((float) $refreshed->remaining_balance)->toBe(150.0);
    $this->assertDatabaseCount('invoice_dunnings', 2);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertOk();

    $response->assertJsonPath('data.dunningLevel', 2)
        ->assertJsonCount(2, 'data.dunnings')
        ->assertJsonPath('data.nextDunningLevel', 3);
});

it('erhält die mahnhistorie auch nach vollständiger zahlung und wechselt den status auf paid', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'total_amount' => 100.00,
    ]);

    $this->actingAs($this->admin)->postJson("/api/v1/invoices/{$invoice->id}/remind")->assertOk();

    // Vollständige Zahlung deckt den kompletten Restbetrag der
    // Original-Rechnung (die Mahngebühr selbst ist ein eigenständiges
    // Dokument, siehe bindende Entscheidung 1 — sie wird hier bewusst
    // NICHT mitbezahlt, um zu prüfen, dass die Original-Rechnung
    // unabhängig davon korrekt auf 'paid' wechselt).
    $this->actingAs($this->admin)
        ->postJson('/api/v1/payments', [
            'invoiceId' => $invoice->id,
            'paymentDate' => now()->format('Y-m-d'),
            'amount' => 100.00,
            'paymentMethod' => 'bank_transfer',
            'status' => 'completed',
        ])
        ->assertCreated();

    $refreshed = $invoice->fresh();
    expect($refreshed->status)->toBe('paid');

    // Die bereits erfasste Mahnstufe bleibt trotz des Statuswechsels auf
    // 'paid' vollständig sichtbar/abrufbar — kein Datenverlust durch die
    // Zahlungserfassung.
    $this->assertDatabaseCount('invoice_dunnings', 1);
    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertOk();

    $response->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.dunningLevel', 1)
        ->assertJsonCount(1, 'data.dunnings');

    // Eine bezahlte Rechnung ist nicht mehr mahnfähig — weiterer
    // Trigger-Versuch wird mit 422 abgelehnt (Eligibility-Prüfung aus
    // `InvoiceDunningRecorder::trigger()` reagiert korrekt auf den durch
    // die Zahlung ausgelösten Statuswechsel).
    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertStatus(422);

    $this->assertDatabaseCount('invoice_dunnings', 1);
});
