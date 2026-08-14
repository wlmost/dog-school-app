<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('api', 'invoice');

// Deckt eine in `task-T04.notes.md` ("Offene Punkte für Reviewer/Tester")
// explizit dokumentierte Lücke: der 502-Pfad von
// `InvoiceController::remind()` (E-Mail-Versand schlägt fehl, nachdem die
// Mahnung bereits erfasst wurde, siehe design.md Decision D7 "kein
// Rollback der Datenmutation") hatte bislang keinen dedizierten Test.
// 1:1 nach dem bereits etablierten Muster von
// `InvoiceSendEmailTest.php::'gibt bei einem e-mail-transportfehler 502
// ... zurück'`.

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);
});

it('gibt bei einem e-mail-transportfehler beim mahnen 502 zurück, behält aber die bereits erfasste mahnstufe', function () {
    $invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'sent',
        'total_amount' => 90.00,
    ]);

    Mail::shouldReceive('to')
        ->once()
        ->andReturnSelf();
    Mail::shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('SMTP connection refused'));

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/remind")
        ->assertStatus(502)
        ->assertJson([
            'message' => 'Die Mahnung wurde erfasst, aber die Benachrichtigungs-E-Mail konnte nicht versendet werden. Bitte laden Sie das Gebührendokument herunter und versenden Sie es manuell.',
        ]);

    // Die Datenmutation (Gebührendokument + Statuswechsel + Mahn-Datensatz)
    // bleibt trotz des Mail-Fehlers bestehen — kein Rollback, konsistent
    // mit `sendEmail()`s etabliertem Verhalten (design.md Decision D7).
    $refreshed = $invoice->fresh();
    expect($refreshed->status)->toBe('reminded');
    expect($refreshed->dunning_level)->toBe(1);

    $this->assertDatabaseCount('invoice_dunnings', 1);
    $this->assertDatabaseHas('invoices', [
        'original_invoice_id' => $invoice->id,
        'document_type' => 'dunning_fee',
        'total_amount' => 5.00,
    ]);
});
