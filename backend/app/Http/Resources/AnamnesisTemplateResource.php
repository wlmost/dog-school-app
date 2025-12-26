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
            'name' => $this->name,
            'description' => $this->description,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            'questions' => AnamnesisQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
