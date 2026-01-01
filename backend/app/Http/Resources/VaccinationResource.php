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
            'dog' => $this->whenLoaded('dog', fn() => new DogResource($this->dog)),
            'vaccinationType' => $this->vaccination_type,
            'vaccinationDate' => $this->vaccination_date?->toDateString(),
            'nextDueDate' => $this->next_due_date?->toDateString(),
            'veterinarian' => $this->veterinarian,
            'documentPath' => $this->document_path,
            'isDue' => $this->isDue(),
            'isOverdue' => $this->isOverdue(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
