<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\DogRegistrationRequest;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * DogRegistrationApproved Mailable
 *
 * Sent to the customer when an admin approves their dog registration request.
 */
class DogRegistrationApproved extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param DogRegistrationRequest $registrationRequest The approved registration request.
     */
    public function __construct(
        public readonly DogRegistrationRequest $registrationRequest,
    ) {
    }

    /**
     * Get the message envelope.
     *
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        $settings = Cache::remember('email_settings', 3600, function () {
            return Setting::whereIn('key', ['company_email', 'company_name'])
                ->pluck('value', 'key')
                ->toArray();
        });

        return new Envelope(
            from: new Address(
                $settings['company_email'] ?? config('mail.from.address', 'info@hundeschule.de'),
                $settings['company_name'] ?? config('mail.from.name', 'Hundeschule'),
            ),
            subject: 'Ihr Hund wurde angelegt - ' . $this->registrationRequest->name,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.dog-registration-approved',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
