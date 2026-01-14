<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Store Course Request
 *
 * Validates incoming requests to create a new course.
 */
class StoreCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins and trainers can create courses
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trainerId' => ['required', 'integer', 'exists:users,id'],
            'courseType' => ['required', 'in:group,individual,workshop'],
            'maxParticipants' => ['required', 'integer', 'min:1', 'max:50'],
            'durationMinutes' => ['required', 'integer', 'min:15', 'max:240'],
            'pricePerSession' => ['required', 'numeric', 'min:0'],
            'totalSessions' => ['required', 'integer', 'min:1', 'max:100'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after:startDate'],
            'status' => ['sometimes', 'in:planned,active,completed,cancelled'],
        ];
    }

    /**
     * Get validated data with snake_case keys for database.
     *
     * @return array<string, mixed>
     */
    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();
        $snakeCase = [];

        foreach ($validated as $key => $value) {
            $snakeCase[Str::snake($key)] = $value;
        }

        // Set default status if not provided
        if (!isset($snakeCase['status'])) {
            $snakeCase['status'] = 'planned';
        }

        return $snakeCase;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'trainerId' => 'trainer',
            'maxParticipants' => 'maximum participants',
            'startDate' => 'start date',
            'endDate' => 'end date',
        ];
    }
}
