<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Settings Resource
 *
 * Transforms Setting model to API response format.
 *
 * @mixin \App\Models\Setting
 */
class SettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $value = $this->value;

        // For file types, return full URL
        if ($this->type === 'file' && $value) {
            $value = Storage::disk('public')->url($value);
        }

        // Cast value based on type
        $value = match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };

        return [
            'key' => $this->key,
            'value' => $value,
            'type' => $this->type,
            'description' => $this->description,
            'group' => $this->group,
        ];
    }
}
