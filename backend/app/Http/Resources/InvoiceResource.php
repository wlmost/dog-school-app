<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice Resource
 *
 * Transforms invoice model into a consistent JSON response format.
 *
 * @mixin \App\Models\Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'invoiceNumber' => $this->invoice_number,
            'status' => $this->status,
            'totalAmount' => (float) $this->total_amount,
            'totalPaid' => (float) $this->total_paid,
            'remainingBalance' => (float) $this->remaining_balance,
            'issueDate' => $this->issue_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'paidDate' => $this->paid_date?->toDateString(),
            'notes' => $this->notes,
            'isPaid' => $this->isPaid(),
            'isOverdue' => $this->isOverdue(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            // Conditional relationships
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
