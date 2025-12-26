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
            'birthdate' => $this->birthdate?->toDateString(),
            'age' => $this->age,
            'gender' => $this->gender,
            'neutered' => $this->neutered,
            'weight' => $this->weight,
            'color' => $this->color,
            'microchipNumber' => $this->microchip_number,
            'vetName' => $this->vet_name,
            'vetPhone' => $this->vet_phone,
            'medicalNotes' => $this->medical_notes,
            'behaviorNotes' => $this->behavior_notes,
            'dietaryRestrictions' => $this->dietary_restrictions,
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
