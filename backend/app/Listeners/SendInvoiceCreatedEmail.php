<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoiceWasCreated;
use App\Mail\InvoiceCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendInvoiceCreatedEmail implements ShouldQueue
{
    use InteractsWithQueue;

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
    public function handle(InvoiceWasCreated $event): void
    {
        // Load necessary relationships
        $event->invoice->load([
            'customer.user',
            'items'
        ]);

        // Send invoice created email to customer
        Mail::to($event->invoice->customer->user->email)
            ->send(new InvoiceCreated($event->invoice));
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(InvoiceWasCreated $event): bool
    {
        // Only queue if invoice was created successfully
        return $event->invoice->exists;
    }

    /**
     * Handle a job failure.
     */
    public function failed(InvoiceWasCreated $event, \Throwable $exception): void
    {
        // Log the error
        logger()->error('Failed to send invoice created email', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
            'error' => $exception->getMessage(),
        ]);
    }
}
