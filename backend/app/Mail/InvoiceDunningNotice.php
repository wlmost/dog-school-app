<?php

declare(strict_types=1);

namespace App\Mail;

use App\Listeners\SendInvoiceDunningEmail;
use App\Models\InvoiceDunning;
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

class InvoiceDunningNotice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public InvoiceDunning $dunning
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
            subject: 'Zahlungserinnerung – Mahnung Stufe '.$this->dunning->level.' zu Rechnung '.$this->dunning->invoice->invoice_number,
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
            view: 'emails.invoice-dunning-notice',
            with: ['settings' => $settings]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * The fee document (not the original invoice, see `design.md`
     * Decision D7) is rendered as a PDF attachment. The
     * {@see InvoicePdfRenderer} is resolved from the container here,
     * inside the method, rather than injected via the constructor: this
     * mailable is instantiated directly (`new InvoiceDunningNotice($dunning)`,
     * see {@see SendInvoiceDunningEmail}), not through the container. A
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
        $feeInvoice = $this->dunning->feeInvoice;

        return [
            Attachment::fromData(
                fn () => $pdfRenderer->render($feeInvoice)->output(),
                $feeInvoice->invoice_number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
