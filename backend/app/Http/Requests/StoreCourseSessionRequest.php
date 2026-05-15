<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Store Course Session Request
 *
 * Validates incoming requests to create a new training session for a course.
 */
class StoreCourseSessionRequest extends FormRequest
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
            'sessionDate'     => ['required', 'date'],
            'startTime'       => ['required', 'date_format:H:i'],
            'endTime'         => ['required', 'date_format:H:i', 'after:startTime'],
            'location'        => ['nullable', 'string', 'max:255'],
            'maxParticipants' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status'          => ['nullable', 'in:scheduled,cancelled,completed'],
            'notes'           => ['nullable', 'string', 'max:1000'],
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
