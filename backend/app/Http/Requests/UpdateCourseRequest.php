<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesHtmlContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Update Course Request
 *
 * Validates incoming requests to update an existing course.
 */
class UpdateCourseRequest extends FormRequest
{
    use SanitizesHtmlContent;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins and trainers can update courses
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'trainerId' => ['sometimes', 'integer', 'exists:users,id'],
            'courseType' => ['sometimes', 'in:group,individual,workshop'],
            'maxParticipants' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'durationMinutes' => ['sometimes', 'integer', 'min:15', 'max:240'],
            'pricePerSession' => ['sometimes', 'numeric', 'min:0'],
            'totalSessions' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'startDate' => ['sometimes', 'date'],
            'endDate' => ['nullable', 'date', 'after:startDate'],
            'cancellationDeadlineHours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'status' => ['sometimes', 'in:planned,active,completed,cancelled'],

            // Session mode
            'sessionsMode' => ['sometimes', 'nullable', 'in:manual,recurrence'],

            // Manual sessions
            'sessions' => ['sometimes', 'nullable', 'array', 'max:52', 'required_if:sessionsMode,manual'],
            'sessions.*.sessionDate' => ['required', 'date'],
            'sessions.*.startTime' => ['required', 'date_format:H:i'],
            'sessions.*.endTime' => ['required', 'date_format:H:i', 'after:sessions.*.startTime'],
            'sessions.*.location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sessions.*.maxParticipants' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],

            // Recurrence rule
            'recurrenceRule' => ['sometimes', 'nullable', 'array', 'required_if:sessionsMode,recurrence'],
            'recurrenceRule.type' => ['required_with:recurrenceRule', 'in:weekly,monthly'],
            'recurrenceRule.weekday' => ['sometimes', 'required_if:recurrenceRule.type,weekly', 'integer', 'min:0', 'max:6'],
            'recurrenceRule.dayOfMonth' => ['sometimes', 'required_if:recurrenceRule.type,monthly', 'integer', 'min:1', 'max:28'],
            'recurrenceRule.startTime' => ['required_with:recurrenceRule', 'date_format:H:i'],
            'recurrenceRule.endTime' => ['required_with:recurrenceRule', 'date_format:H:i'],
            'recurrenceRule.startDate' => ['required_with:recurrenceRule', 'date'],
            'recurrenceRule.count' => ['required_with:recurrenceRule', 'integer', 'min:1', 'max:52'],
            'recurrenceRule.location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recurrenceRule.maxParticipants' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
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

        // Sanitize HTML in description to prevent XSS
        if (isset($snakeCase['description']) && is_string($snakeCase['description'])) {
            $snakeCase['description'] = $this->sanitizeHtmlDescription($snakeCase['description']);
        }

        // Exclude session fields — handled separately via getSessionsPayload() / getRecurrenceRule()
        unset($snakeCase['sessions'], $snakeCase['recurrence_rule'], $snakeCase['sessions_mode']);

        return $snakeCase;
    }

    /**
     * Returns the manual sessions payload from the request, or null if not present.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function getSessionsPayload(): ?array
    {
        $sessions = $this->validated()['sessions'] ?? null;

        if (!is_array($sessions) || empty($sessions)) {
            return null;
        }

        return $sessions;
    }

    /**
     * Returns the recurrence rule from the request with keys converted from camelCase to snake_case,
     * or null if not present.
     *
     * E.g. recurrenceRule.startDate → start_date, recurrenceRule.dayOfMonth → day_of_month
     *
     * @return array<string, mixed>|null
     */
    public function getRecurrenceRule(): ?array
    {
        $rule = $this->validated()['recurrenceRule'] ?? null;

        if (!is_array($rule) || empty($rule)) {
            return null;
        }

        $converted = [];
        foreach ($rule as $key => $value) {
            $converted[Str::snake((string) $key)] = $value;
        }

        return $converted;
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

