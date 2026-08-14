<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InvoiceDunning;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * InvoiceDunning Resource
 *
 * Transforms a single dunning (reminder) level into a consistent JSON
 * response format, following the established `PaymentResource` pattern
 * (see `design.md` Decision D6). `feeInvoiceNumber` is only populated when
 * the `feeInvoice` relation has been eager-loaded (see
 * `InvoiceController::index()`/`show()`/`finalize()`/`cancel()`/`remind()`,
 * which load `dunnings.feeInvoice` to avoid an N+1 query per dunning row).
 *
 * @mixin InvoiceDunning
 */
class InvoiceDunningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'dunningDate' => $this->dunning_date?->toDateString(),
            'feeAmount' => (float) $this->fee_amount,
            'feeInvoiceId' => $this->fee_invoice_id,
            'feeInvoiceNumber' => $this->whenLoaded('feeInvoice', fn () => $this->feeInvoice?->invoice_number),
        ];
    }
}
