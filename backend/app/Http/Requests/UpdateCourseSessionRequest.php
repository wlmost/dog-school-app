<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Course Session Request
 *
 * Validates incoming requests to update an existing training session.
 * All fields are optional (sometimes), allowing partial updates.
 */
class UpdateCourseSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdminOrTrainer() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sessionDate'     => ['sometimes', 'required', 'date'],
            'startTime'       => ['sometimes', 'required', 'date_format:H:i'],
            'endTime'         => ['sometimes', 'required', 'date_format:H:i', 'after:startTime'],
            'location'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'maxParticipants' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'status'          => ['sometimes', 'nullable', 'in:scheduled,cancelled,completed'],
            'notes'           => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sessionDate'     => 'session date',
            'startTime'       => 'start time',
            'endTime'         => 'end time',
            'maxParticipants' => 'maximum participants',
        ];
    }
}
