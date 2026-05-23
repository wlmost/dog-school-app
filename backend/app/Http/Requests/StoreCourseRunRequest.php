<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * StoreCourseRunRequest
 *
 * Validates requests to create a new CourseRun for a given Course.
 * Authorization is handled in the controller via CoursePolicy::update().
 */
class StoreCourseRunRequest extends FormRequest
{
    /**
     * Authorization is delegated to the controller via `$this->authorize('update', $course)`.
     * The Form Request itself does not perform policy checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Uses a closure for the endDate comparison to avoid the
     * DateMalformedStringException / XDebug interaction bug with
     * `after_or_equal:fieldName` in PHP 8.3 + Carbon 3.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'startDate' => ['required', 'date'],
            'endDate'   => [
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = $this->input('startDate');
                    if ($value !== null && $start !== null && Carbon::parse($value)->lt(Carbon::parse($start))) {
                        $fail('Das Enddatum muss auf oder nach dem Startdatum liegen.');
                    }
                },
            ],
            'status' => ['nullable', 'string', 'in:active,completed,cancelled'],
        ];
    }

    /**
     * Get custom display names for validation attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'startDate' => 'Startdatum',
            'endDate'   => 'Enddatum',
            'status'    => 'Status',
        ];
    }
}
