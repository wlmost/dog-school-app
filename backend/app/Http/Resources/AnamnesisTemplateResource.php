<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anamnesis Template Resource
 *
 * @mixin \App\Models\AnamnesisTemplate
 */
class AnamnesisTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trainerId' => $this->trainer_id,
            'name' => $this->name,
            'description' => $this->description,
            'isDefault' => $this->is_default,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'questions' => AnamnesisQuestionResource::collection($this->whenLoaded('questions')),
            'responsesCount' => $this->when(
                $this->relationLoaded('responses'),
                fn() => $this->responses->count()
            ),
        ];
    }
}
