<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vaccination Resource
 *
 * @mixin \App\Models\Vaccination
 */
class VaccinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dogId' => $this->dog_id,
            'vaccinationType' => $this->vaccination_type,
            'vaccinationDate' => $this->vaccination_date?->toDateString(),
            'expirationDate' => $this->expiration_date?->toDateString(),
            'veterinarianName' => $this->veterinarian_name,
            'batchNumber' => $this->batch_number,
            'notes' => $this->notes,
            'isDue' => $this->isDue(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
