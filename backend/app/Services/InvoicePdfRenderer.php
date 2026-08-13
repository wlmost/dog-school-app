<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\Api\InvoiceController;
use App\Mail\InvoiceSent;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * InvoicePdfRenderer
 *
 * Renders an {@see Invoice} into a PDF document using the `pdf.invoice`
 * Blade view. Encapsulates the `Pdf::loadView(...)` configuration so both
 * the PDF-download endpoint ({@see InvoiceController::downloadPdf()})
 * and the outgoing invoice mail ({@see InvoiceSent::attachments()})
 * produce byte-identical output from a single source of truth.
 */
class InvoicePdfRenderer
{
    /**
     * Renders the given invoice into a PDF document.
     *
     * The caller decides what to do with the returned document (stream it
     * as a download, or read its raw bytes via {@see PdfDocument::output()}
     * for a mail attachment).
     */
    public function render(Invoice $invoice): PdfDocument
    {
        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }
}
