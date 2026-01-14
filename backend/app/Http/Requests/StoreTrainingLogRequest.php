<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dogId' => ['required', 'integer', 'exists:dogs,id'],
            'trainingSessionId' => ['nullable', 'integer', 'exists:training_sessions,id'],
            'trainerId' => ['required', 'integer', 'exists:users,id'],
            'progressNotes' => ['nullable', 'string'],
            'behaviorNotes' => ['nullable', 'string'],
            'homework' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the validated data from the request with snake_case keys.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();
        
        return [
            'dog_id' => $validated['dogId'],
            'training_session_id' => $validated['trainingSessionId'] ?? null,
            'trainer_id' => $validated['trainerId'],
            'progress_notes' => $validated['progressNotes'] ?? null,
            'behavior_notes' => $validated['behaviorNotes'] ?? null,
            'homework' => $validated['homework'] ?? null,
        ];
    }
}
