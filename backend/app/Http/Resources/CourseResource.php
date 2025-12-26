<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Course Resource
 *
 * @mixin \App\Models\Course
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trainerId' => $this->trainer_id,
            'name' => $this->name,
            'description' => $this->description,
            'courseType' => $this->course_type,
            'level' => $this->level,
            'price' => $this->price,
            'maxParticipants' => $this->max_participants,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'isActive' => $this->isActive(),
            'isFull' => $this->isFull(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'sessions' => TrainingSessionResource::collection($this->whenLoaded('sessions')),
        ];
    }
}
