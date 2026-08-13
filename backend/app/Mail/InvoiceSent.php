<?php

declare(strict_types=1);

namespace App\Mail;

use App\Listeners\SendInvoiceEmail;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoicePdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class InvoiceSent extends Mailable
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
            subject: 'Rechnung '.$this->invoice->invoice_number,
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
            view: 'emails.invoice-sent',
            with: ['settings' => $settings]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * The {@see InvoicePdfRenderer} is resolved from the container here,
     * inside the method, rather than injected via the constructor: this
     * mailable is instantiated directly (`new InvoiceSent($invoice)`, see
     * {@see SendInvoiceEmail}), not through the container. A
     * constructor-promoted service property would additionally be picked
     * up by `Queueable`/`SerializesModels` and (de)serialized on every
     * queue round-trip, which is unnecessary overhead for a service that
     * has no state of its own.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdfRenderer = app(InvoicePdfRenderer::class);

        return [
            Attachment::fromData(
                fn () => $pdfRenderer->render($this->invoice)->output(),
                $this->invoice->invoice_number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
