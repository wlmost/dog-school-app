<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Training Session Resource
 *
 * @mixin \App\Models\TrainingSession
 */
class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'courseId' => $this->course_id,
            'trainerId' => $this->trainer_id,
            'sessionDate' => $this->session_date?->format('Y-m-d'),
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'duration' => $this->duration,
            'maxParticipants' => $this->max_participants,
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,
            'isPast' => $this->isPast(),
            'isFull' => $this->isFull(),
            'availableSpots' => $this->available_spots,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            'course' => new CourseResource($this->whenLoaded('course')),
            'trainer' => new UserResource($this->whenLoaded('trainer')),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
        ];
    }
}
