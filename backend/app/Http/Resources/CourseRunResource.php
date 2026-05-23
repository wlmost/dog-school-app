<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CourseRun Resource
 *
 * Transforms a CourseRun model into a consistent JSON response.
 *
 * @mixin \App\Models\CourseRun
 */
class CourseRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'courseId'  => $this->course_id,
            'startDate' => $this->start_date?->toDateString(),
            'endDate'   => $this->end_date?->toDateString(),
            'status'    => $this->status,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'sessions'  => TrainingSessionResource::collection($this->whenLoaded('sessions')),
        ];
    }
}
