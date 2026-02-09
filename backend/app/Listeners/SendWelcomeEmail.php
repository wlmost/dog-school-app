<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
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
    public function handle(UserRegistered $event): void
    {
        // Send welcome email to new user
        Mail::to($event->user->email)
            ->send(new WelcomeEmail($event->user, $event->temporaryPassword));
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(UserRegistered $event): bool
    {
        // Only queue if user was created successfully
        return $event->user->exists;
    }

    /**
     * Handle a job failure.
     */
    public function failed(UserRegistered $event, \Throwable $exception): void
    {
        // Log the error
        logger()->error('Failed to send welcome email', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
