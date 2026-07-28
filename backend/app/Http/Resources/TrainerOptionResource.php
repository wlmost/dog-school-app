<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trainer Option Resource
 *
 * Reduced-data representation of a trainer for use in select boxes
 * (e.g. trainer assignment in customer/course creation forms). Deliberately
 * excludes contact and qualification data available on {@see UserResource}.
 *
 * @mixin \App\Models\User
 */
class TrainerOptionResource extends JsonResource
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
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => $this->full_name,
        ];
    }
}
