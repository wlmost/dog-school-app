<?php

declare(strict_types=1);

use App\Events\InvoiceDunningTriggered;
use App\Mail\InvoiceDunningNotice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceDunning;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('feature', 'invoice');

beforeEach(function () {
    $customerUser = User::factory()->customer()->create();
    $this->customer = Customer::factory()->create(['user_id' => $customerUser->id]);
    $this->customerUser = $customerUser;

    $this->invoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'reminded',
    ]);
    InvoiceItem::factory()->create(['invoice_id' => $this->invoice->id]);

    $this->feeInvoice = Invoice::factory()->create([
        'customer_id' => $this->customer->id,
        'original_invoice_id' => $this->invoice->id,
        'document_type' => 'dunning_fee',
        'status' => 'sent',
        'total_amount' => 10.00,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $this->feeInvoice->id,
        'description' => 'Mahngebühr Stufe 2 zu Rechnung '.$this->invoice->invoice_number,
        'quantity' => 1,
        'unit_price' => 10.00,
        'tax_rate' => 0,
        'amount' => 10.00,
    ]);

    $this->dunning = InvoiceDunning::factory()->create([
        'invoice_id' => $this->invoice->id,
        'level' => 2,
        'dunning_date' => now(),
        'fee_amount' => 10.00,
        'fee_invoice_id' => $this->feeInvoice->id,
    ]);
});

it('löst durch das dispatchen von invoicedunningtriggered genau eine mahn-mail an den kunden aus', function () {
    Mail::fake();

    InvoiceDunningTriggered::dispatch($this->dunning);

    Mail::assertSent(
        InvoiceDunningNotice::class,
        1
    );

    Mail::assertSent(
        InvoiceDunningNotice::class,
        fn ($mail) => $mail->hasTo($this->customerUser->email)
    );
});

it('hängt bei der ausgelösten mahn-mail das gebührendokument als pdf an', function () {
    Mail::fake();

    InvoiceDunningTriggered::dispatch($this->dunning);

    Mail::assertSent(
        InvoiceDunningNotice::class,
        function ($mail) {
            $attachments = $mail->attachments();

            return count($attachments) === 1
                && $attachments[0]->mime === 'application/pdf'
                && $attachments[0]->as === $this->feeInvoice->invoice_number.'.pdf';
        }
    );
});

it('rendert die mahn-mail mit stufe, mahngebühr und der rechnungsnummer der original-rechnung', function () {
    $mail = new InvoiceDunningNotice($this->dunning);

    $mail->assertSeeInHtml('Mahnstufe 2')
        ->assertSeeInHtml(number_format(10.00, 2, ',', '.').' €')
        ->assertSeeInHtml($this->invoice->invoice_number);
});
