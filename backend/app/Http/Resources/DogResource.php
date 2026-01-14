<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dog Resource
 *
 * Transforms dog model into a consistent JSON response format.
 *
 * @mixin \App\Models\Dog
 */
class DogResource extends JsonResource
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
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'neutered' => $this->neutered,
            'weight' => $this->weight,
            'color' => $this->color,
            'chipNumber' => $this->chip_number,
            'veterinarian' => $this->veterinarian,
            'specialNeeds' => $this->special_needs,
            'isActive' => $this->is_active,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
            
            // Conditional relationships
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'vaccinations' => VaccinationResource::collection($this->whenLoaded('vaccinations')),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'anamnesisResponses' => AnamnesisResponseResource::collection($this->whenLoaded('anamnesisResponses')),
            'trainingLogs' => TrainingLogResource::collection($this->whenLoaded('trainingLogs')),
        ];
    }
}
