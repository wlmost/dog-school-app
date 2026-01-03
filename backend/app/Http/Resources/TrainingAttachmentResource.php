<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Training Attachment Resource
 *
 * @mixin \App\Models\TrainingAttachment
 */
class TrainingAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trainingLogId' => $this->training_log_id,
            'fileType' => $this->file_type,
            'filePath' => $this->file_path,
            'fileName' => $this->file_name,
            'uploadedAt' => $this->uploaded_at?->toISOString(),
            'downloadUrl' => route('training-attachments.download', $this->id),
            'trainingLog' => new TrainingLogResource($this->whenLoaded('trainingLog')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
