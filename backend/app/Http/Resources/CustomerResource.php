<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer Resource
 *
 * Transforms customer model into a consistent JSON response format.
 *
 * @mixin \App\Models\Customer
 */
class CustomerResource extends JsonResource
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
            'userId' => $this->user_id,
            'trainerId' => $this->trainer_id,
            'addressLine1' => $this->address_line1,
            'addressLine2' => $this->address_line2,
            'postalCode' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
            'emergencyContact' => $this->emergency_contact,
            'notes' => $this->notes,
            'fullAddress' => $this->full_address,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            // Conditional relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'dogs' => DogResource::collection($this->whenLoaded('dogs')),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'credits' => CustomerCreditResource::collection($this->whenLoaded('credits')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            
            // Counts
            'dogsCount' => $this->when(
                $this->relationLoaded('dogs'),
                fn() => $this->dogs->count()
            ),
            'bookingsCount' => $this->when(
                $this->relationLoaded('bookings'),
                fn() => $this->bookings->count()
            ),
        ];
    }
}
