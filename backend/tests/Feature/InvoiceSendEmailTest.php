<?php

declare(strict_types=1);

use App\Mail\InvoiceSent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);
uses()->group('feature', 'invoice');

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->trainer = User::factory()->trainer()->create();
    $this->customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);

    $this->sendableInvoice = function (string $status = 'sent') {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => $status,
        ]);

        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        return $invoice;
    };
});

it('lässt einen admin eine rechnungs-e-mail für eine versendete rechnung verschicken', function () {
    Mail::fake();

    $invoice = ($this->sendableInvoice)('sent');

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertOk();

    Mail::assertSent(
        InvoiceSent::class,
        fn ($mail) => $mail->hasTo($this->customerUser->email)
    );
});

it('lässt einen trainer eine rechnungs-e-mail für gemahnte und überfällige rechnungen verschicken', function (string $status) {
    Mail::fake();

    $invoice = ($this->sendableInvoice)($status);

    $this->actingAs($this->trainer)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertOk();

    Mail::assertSent(InvoiceSent::class);
})->with(['reminded', 'overdue']);

it('lehnt den versand mit 422 ab wenn der rechnungsstatus dies nicht erlaubt und verschickt keine e-mail', function (string $status) {
    Mail::fake();

    $invoice = ($this->sendableInvoice)($status);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Diese Rechnung kann in ihrem aktuellen Status nicht versendet werden.',
        ]);

    Mail::assertNothingSent();
})->with(['draft', 'paid', 'cancelled']);

it('lehnt den versand mit 422 ab wenn für den kunden keine e-mail-adresse hinterlegt ist', function () {
    Mail::fake();

    $invoice = ($this->sendableInvoice)('sent');
    $this->customerUser->forceFill(['email' => ''])->save();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.',
        ]);

    Mail::assertNothingSent();
});

it('verbietet einem kunden das versenden einer rechnungs-e-mail', function () {
    Mail::fake();

    $invoice = ($this->sendableInvoice)('sent');

    $this->actingAs($this->customerUser)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertStatus(403);

    Mail::assertNothingSent();
});

it('lässt den rechnungsstatus nach erfolgreichem versand unverändert', function () {
    Mail::fake();

    $invoice = ($this->sendableInvoice)('reminded');

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertOk();

    expect($invoice->fresh()->status)->toBe('reminded');
});

it('lässt status und alle übrigen felder der rechnung unverändert nach erfolgreichem versand', function (string $status) {
    Mail::fake();

    $invoice = ($this->sendableInvoice)($status);
    $before = $invoice->fresh()->getAttributes();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertOk();

    $after = $invoice->fresh()->getAttributes();

    expect($after)->toBe($before)
        ->and($after['status'])->toBe($status)
        ->and($after['paid_date'])->toBeNull();
})->with(['reminded', 'overdue']);

it('verschickt bei zweimaligem aufruf zwei separate e-mails und antwortet beide male mit 200', function () {
    Mail::fake();

    $invoice = ($this->sendableInvoice)('sent');

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertOk();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertOk();

    Mail::assertSent(InvoiceSent::class, 2);
    expect($invoice->fresh()->status)->toBe('sent');
});

it('gibt bei einem e-mail-transportfehler 502 mit hinweis auf den manuellen download zurück', function () {
    $invoice = ($this->sendableInvoice)('sent');

    Mail::shouldReceive('to')
        ->once()
        ->andReturnSelf();
    Mail::shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('SMTP connection refused'));

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertStatus(502)
        ->assertJson([
            'message' => 'Die Rechnung konnte nicht per E-Mail versendet werden. Bitte laden Sie das PDF herunter und versenden Sie es manuell.',
        ]);

    expect($invoice->fresh()->status)->toBe('sent');
});

it('fängt einen smtp-timeout wie jeden anderen transportfehler ab und gibt 502 zurück', function () {
    $invoice = ($this->sendableInvoice)('sent');

    Mail::shouldReceive('to')
        ->once()
        ->andReturnSelf();
    Mail::shouldReceive('send')
        ->once()
        ->andThrow(new TransportException('Connection could not be established with host smtp.example.com: Connection timed out'));

    $this->actingAs($this->admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertStatus(502)
        ->assertJson([
            'message' => 'Die Rechnung konnte nicht per E-Mail versendet werden. Bitte laden Sie das PDF herunter und versenden Sie es manuell.',
        ]);

    expect($invoice->fresh()->status)->toBe('sent');
});
