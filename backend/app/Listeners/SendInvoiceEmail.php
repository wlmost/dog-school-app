<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoiceWasSent;
use App\Mail\InvoiceSent;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmail
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoiceWasSent $event): void
    {
        // Load necessary relationships
        $event->invoice->load([
            'customer.user',
            'items',
        ]);

        // Send invoice email to customer
        Mail::to($event->invoice->customer->user->email)
            ->send(new InvoiceSent($event->invoice));
    }

    /**
     * Handle a job failure.
     */
    public function failed(InvoiceWasSent $event, \Throwable $exception): void
    {
        // Log the error
        logger()->error('Failed to send invoice created email', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
            'error' => $exception->getMessage(),
        ]);
    }
}
