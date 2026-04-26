<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * DogDeletedMail Mailable
 *
 * Sent to the customer when their dog is deleted from the system,
 * either directly by an admin or after approving a customer deletion request.
 */
class DogDeletedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param string $customerFirstName  Customer's first name for salutation.
     * @param string $dogName            Name of the deleted dog.
     */
    public function __construct(
        public readonly string $customerFirstName,
        public readonly string $dogName,
    ) {
    }

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
            subject: 'Ihr Hund wurde aus dem System entfernt - ' . $this->dogName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dog-deleted',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
