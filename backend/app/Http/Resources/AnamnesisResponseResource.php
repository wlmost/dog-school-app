<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anamnesis Response Resource
 *
 * @mixin \App\Models\AnamnesisResponse
 */
class AnamnesisResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dogId' => $this->dog_id,
            'templateId' => $this->template_id,
            'completedAt' => $this->completed_at?->toISOString(),
            'completedBy' => $this->completed_by,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'dog' => new DogResource($this->whenLoaded('dog')),
            'template' => new AnamnesisTemplateResource($this->whenLoaded('template')),
            'completedByUser' => new UserResource($this->whenLoaded('completedBy')),
            'answers' => AnamnesisAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
