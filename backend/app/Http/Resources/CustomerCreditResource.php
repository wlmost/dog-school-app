<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer Credit Resource
 *
 * Transforms customer credit model into a consistent JSON response format.
 *
 * @mixin \App\Models\CustomerCredit
 */
class CustomerCreditResource extends JsonResource
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
            'creditPackageId' => $this->credit_package_id,
            'totalCredits' => $this->total_credits,
            'remainingCredits' => $this->remaining_credits,
            'status' => $this->status,
            'purchaseDate' => $this->purchase_date?->toDateString(),
            'expirationDate' => $this->expiration_date?->toDateString(),
            'isActive' => $this->isActive(),
            'isExpired' => $this->isExpired(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            // Conditional relationships
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'package' => new CreditPackageResource($this->whenLoaded('package')),
        ];
    }
}
