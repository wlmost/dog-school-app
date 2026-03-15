<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BookingConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationEmail implements ShouldQueue
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
    public function handle(BookingCreated $event): void
    {
        // Load necessary relationships
        $event->booking->load([
            'trainingSession.course',
            'dog.customer.user'
        ]);

        // Send booking confirmation email to customer
        Mail::to($event->booking->dog->customer->user->email)
            ->send(new BookingConfirmation($event->booking));
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(BookingCreated $event): bool
    {
        // Only queue if booking was successful
        return $event->booking->exists;
    }

    /**
     * Handle a job failure.
     */
    public function failed(BookingCreated $event, \Throwable $exception): void
    {
        // Log the error
        logger()->error('Failed to send booking confirmation email', [
            'booking_id' => $event->booking->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
