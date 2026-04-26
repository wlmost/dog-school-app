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
 * ContactFormMail
 *
 * Sent to the company's contact address when a visitor submits the public contact form.
 * The reply-to is set to the sender's address so replies go directly to them.
 */
class ContactFormMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $subject,
        public readonly string $contactMessage,
        public readonly ?string $phone = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $settings = Cache::remember('email_settings', 3600, function () {
            return Setting::whereIn('key', ['company_email', 'company_name', 'email_from_address', 'email_from_name'])
                ->pluck('value', 'key')
                ->toArray();
        });

        $fromAddress = $settings['email_from_address']
            ?? $settings['company_email']
            ?? config('mail.from.address', 'info@hundeschule.de');

        $fromName = $settings['email_from_name']
            ?? $settings['company_name']
            ?? config('mail.from.name', 'Hundeschule');

        $toAddress = $settings['company_email']
            ?? $settings['email_from_address']
            ?? config('mail.from.address', 'info@hundeschule.de');

        $companyName = $settings['company_name'] ?? config('mail.from.name', 'Hundeschule');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            to: [new Address($toAddress, $companyName)],
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: 'Kontaktformular: ' . $this->subject,
        );
    }

    public function content(): Content
    {
        $settings = Cache::remember('all_settings', 3600, function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        return new Content(
            view: 'emails.contact-form',
            with: ['settings' => $settings],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
