<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payment Resource
 *
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceId' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn() => new InvoiceResource($this->invoice)),
            'amount' => (float) $this->amount,
            'paymentMethod' => $this->payment_method,
            'transactionId' => $this->transaction_id,
            'status' => $this->status,
            'paymentDate' => $this->payment_date?->toDateString(),
            'notes' => $this->notes,
            'isCompleted' => $this->isCompleted(),
            'isPending' => $this->isPending(),
            'isFailed' => $this->isFailed(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
