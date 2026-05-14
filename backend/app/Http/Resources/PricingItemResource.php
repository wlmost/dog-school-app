<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PricingItem Resource
 *
 * @mixin \App\Models\PricingItem
 */
class PricingItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'category'     => $this->category,
            'title'        => $this->title,
            'price'        => $this->price,
            'unit'         => $this->unit,
            'description'  => $this->description,
            'isFromPrice'  => $this->is_from_price,
            'createdAt'    => $this->created_at?->toISOString(),
            'updatedAt'    => $this->updated_at?->toISOString(),
        ];
    }
}
