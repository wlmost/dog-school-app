<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anamnesis Question Resource
 *
 * @mixin \App\Models\AnamnesisQuestion
 */
class AnamnesisQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'templateId' => $this->template_id,
            'questionText' => $this->question_text,
            'questionType' => $this->question_type,
            'options' => $this->options ? (is_array($this->options) ? $this->options : json_decode($this->options, true)) : null,
            'isRequired' => $this->is_required,
            'order' => $this->order,
            'helpText' => $this->help_text ?? null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
