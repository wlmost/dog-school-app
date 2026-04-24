<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class InvoiceCreated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Invoice $invoice
    ) {
        //
    }

    /**
     * Get the message envelope.
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
                $settings['company_email'] ?? env('MAIL_FROM_ADDRESS', 'info@hundeschule.de'),
                $settings['company_name'] ?? env('MAIL_FROM_NAME', 'Hundeschule')
            ),
            subject: 'Rechnung ' . $this->invoice->invoice_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $settings = Cache::remember('all_settings', 3600, function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        return new Content(
            view: 'emails.invoice-created',
            with: ['settings' => $settings]
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
