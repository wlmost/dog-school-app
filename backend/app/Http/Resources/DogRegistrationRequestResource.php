<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DogRegistrationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DogRegistrationRequest Resource
 *
 * Transforms a DogRegistrationRequest model into a consistent JSON response format.
 *
 * @mixin DogRegistrationRequest
 */
class DogRegistrationRequestResource extends JsonResource
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
            'name' => $this->name,
            'breed' => $this->breed,
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            'ownerSince' => $this->owner_since?->toDateString(),
            'ageAtAcquisition' => $this->age_at_acquisition,
            'origin' => $this->origin,
            'neutered' => $this->neutered,
            'chipNumber' => $this->chip_number,
            'notes' => $this->notes,
            'status' => $this->status,
            'reviewedBy' => $this->reviewed_by,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),

            // Conditionally include the customer relationship when eager-loaded
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
