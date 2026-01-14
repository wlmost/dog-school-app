<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainingLogRequest extends FormRequest
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
            'dogId' => ['sometimes', 'integer', 'exists:dogs,id'],
            'trainingSessionId' => ['sometimes', 'nullable', 'integer', 'exists:training_sessions,id'],
            'trainerId' => ['sometimes', 'integer', 'exists:users,id'],
            'progressNotes' => ['sometimes', 'nullable', 'string'],
            'behaviorNotes' => ['sometimes', 'nullable', 'string'],
            'homework' => ['sometimes', 'nullable', 'string'],
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
        $result = [];
        
        if (isset($validated['dogId'])) {
            $result['dog_id'] = $validated['dogId'];
        }
        
        if (isset($validated['trainingSessionId'])) {
            $result['training_session_id'] = $validated['trainingSessionId'];
        }
        
        if (isset($validated['trainerId'])) {
            $result['trainer_id'] = $validated['trainerId'];
        }
        
        if (isset($validated['progressNotes'])) {
            $result['progress_notes'] = $validated['progressNotes'];
        }
        
        if (isset($validated['behaviorNotes'])) {
            $result['behavior_notes'] = $validated['behaviorNotes'];
        }
        
        if (isset($validated['homework'])) {
            $result['homework'] = $validated['homework'];
        }
        
        return $result;
    }
}
