<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Announcement Resource
 *
 * Transforms announcement model into a consistent JSON response format.
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
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
            'title' => $this->title,
            'body' => $this->body,
            'imageUrl' => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : null,
            'displayDays' => $this->display_days,
            'expiresAt' => $this->expires_at?->toISOString(),
            'isActive' => $this->isActive(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
