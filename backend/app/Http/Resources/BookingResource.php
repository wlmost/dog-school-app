<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Booking Resource
 *
 * Transforms booking model into a consistent JSON response format.
 *
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
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
            'trainingSessionId' => $this->training_session_id,
            'customerId' => $this->customer_id,
            'dogId' => $this->dog_id,
            'bookingDate' => $this->booking_date?->toISOString(),
            'status' => $this->status,
            'attended' => $this->attended,
            'cancellationReason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'isConfirmed' => $this->isConfirmed(),
            'isCancelled' => $this->isCancelled(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            // Conditional relationships
            'trainingSession' => new TrainingSessionResource($this->whenLoaded('trainingSession')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'dog' => new DogResource($this->whenLoaded('dog')),
        ];
    }
}
