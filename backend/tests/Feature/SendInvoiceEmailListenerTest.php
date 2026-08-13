<?php

declare(strict_types=1);

use App\Events\InvoiceWasSent;
use App\Listeners\SendInvoiceEmail;
use App\Mail\InvoiceSent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);
uses()->group('feature', 'invoice');

beforeEach(function () {
    $customerUser = User::factory()->customer()->create();
    $customerRecord = Customer::factory()->create(['user_id' => $customerUser->id]);

    $this->invoice = Invoice::factory()->create([
        'customer_id' => $customerRecord->id,
        'status' => 'sent',
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $this->invoice->id]);
});

it('sendet die rechnungs-mail synchron statt sie in die warteschlange zu stellen', function () {
    Mail::fake();

    (new SendInvoiceEmail)->handle(new InvoiceWasSent($this->invoice));

    Mail::assertSent(InvoiceSent::class);
    Mail::assertNothingQueued();
});

it('implementiert nicht mehr shouldqueue', function () {
    expect(new SendInvoiceEmail)->not->toBeInstanceOf(ShouldQueue::class);
});
