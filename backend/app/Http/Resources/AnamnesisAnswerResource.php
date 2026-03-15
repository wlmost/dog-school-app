<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Anamnesis Answer Resource
 *
 * @mixin \App\Models\AnamnesisAnswer
 */
class AnamnesisAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'responseId' => $this->response_id,
            'questionId' => $this->question_id,
            'questionText' => $this->question?->question_text ?? null,
            'answerValue' => $this->answer_value,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'question' => new AnamnesisQuestionResource($this->whenLoaded('question')),
        ];
    }
}
