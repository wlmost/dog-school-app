<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Update Booking Request
 *
 * Validates incoming requests to update an existing booking.
 */
class UpdateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins and trainers can update bookings
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
            'status' => ['sometimes', 'in:pending,confirmed,cancelled'],
            'attended' => ['nullable', 'boolean'],
            'cancellationReason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'cancellationReason' => 'cancellation reason',
        ];
    }
}
