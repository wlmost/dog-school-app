<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoiceDunningTriggered;
use App\Mail\InvoiceDunningNotice;
use Illuminate\Support\Facades\Mail;

class SendInvoiceDunningEmail
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
    public function handle(InvoiceDunningTriggered $event): void
    {
        // Load necessary relationships
        $event->dunning->loadMissing([
            'invoice.customer.user',
            'feeInvoice.items',
        ]);

        // Send dunning notice email to customer
        Mail::to($event->dunning->invoice->customer->user->email)
            ->send(new InvoiceDunningNotice($event->dunning));
    }

    /**
     * Handle a job failure.
     */
    public function failed(InvoiceDunningTriggered $event, \Throwable $exception): void
    {
        // Log the error
        logger()->error('Failed to send invoice dunning notice email', [
            'invoice_dunning_id' => $event->dunning->id,
            'invoice_id' => $event->dunning->invoice_id,
            'level' => $event->dunning->level,
            'error' => $exception->getMessage(),
        ]);
    }
}
