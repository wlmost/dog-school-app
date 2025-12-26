<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Training Log Resource
 *
 * @mixin \App\Models\TrainingLog
 */
class TrainingLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dogId' => $this->dog_id,
            'trainingSessionId' => $this->training_session_id,
            'trainerId' => $this->trainer_id,
            'logDate' => $this->log_date?->toDateString(),
            'notes' => $this->notes,
            'progressRating' => $this->progress_rating,
            'nextSteps' => $this->next_steps,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            'dog' => new DogResource($this->whenLoaded('dog')),
            'trainingSession' => new TrainingSessionResource($this->whenLoaded('trainingSession')),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'attachments' => TrainingAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
